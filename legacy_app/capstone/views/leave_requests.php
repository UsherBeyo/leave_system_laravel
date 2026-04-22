<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../config/database.php';
require_once '../helpers/Auth.php';
require_once '../helpers/Flash.php';
Auth::requireLogin('login.php');
require_once '../helpers/DateHelper.php';
require_once '../helpers/Pagination.php';

if (!in_array($_SESSION['role'], ['admin','manager','department_head','hr','personnel'], true)) {
    flash_redirect('dashboard.php', 'error', 'Access Denied');
}

$db = (new Database())->connect();

$role = $_SESSION['role'];
$userId = (int)($_SESSION['user_id'] ?? 0);

$isPersonnelOnlyView = ($role === 'personnel');
$isDepartmentHeadView = ($role === 'department_head');
$showPendingDepartmentHead = in_array($role, ['admin','manager','department_head'], true);
$showPendingPersonnel = in_array($role, ['admin','hr','personnel'], true) && $role !== 'department_head';
$showApprovedSection = true;
$showRejectedSection = true;
$showArchivedSection = !$isPersonnelOnlyView && !$isDepartmentHeadView;

$allowedTabs = $isPersonnelOnlyView
    ? ['pending', 'approved', 'rejected']
    : ($isDepartmentHeadView
        ? ['all', 'pending', 'approved', 'rejected']
        : ['all', 'pending', 'approved', 'rejected', 'archived']);

// tab filter controls (all / pending / approved / rejected / archived)
$tab = $_GET['tab'] ?? ($isPersonnelOnlyView ? 'pending' : 'all');
if (!in_array($tab, $allowedTabs, true)) {
    $tab = $isPersonnelOnlyView ? 'pending' : 'all';
}

function safe_h($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function leave_request_department_ids_for_user(PDO $db, int $userId, int $fallbackEmployeeId = 0): array {
    try {
        $stmt = $db->prepare("SELECT dha.department_id FROM department_head_assignments dha JOIN employees e ON e.id = dha.employee_id WHERE e.user_id = ? AND dha.is_active = 1");
        $stmt->execute([$userId]);
        $ids = array_values(array_unique(array_filter(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)))));
        if (!empty($ids)) {
            return $ids;
        }
    } catch (Throwable $t) {
        // ignore and fall back
    }

    if ($fallbackEmployeeId > 0) {
        try {
            $stmt = $db->prepare("SELECT department_id FROM employees WHERE id = ? LIMIT 1");
            $stmt->execute([$fallbackEmployeeId]);
            $deptId = (int)($stmt->fetchColumn() ?: 0);
            if ($deptId > 0) {
                return [$deptId];
            }
        } catch (Throwable $t) {
            // ignore
        }
    }

    return [];
}

function leave_request_filter_date(array $row): string {
    if (!empty($row['start_date']) && $row['start_date'] !== '0000-00-00') {
        return (string)$row['start_date'];
    }
    return substr((string)($row['created_at'] ?? ''), 0, 10);
}

function leave_request_sort_timestamp(array $row, string $sortBy): int {
    $sortBy = strtolower(trim($sortBy));
    $value = '';
    switch ($sortBy) {
        case 'submitted':
            $value = (string)($row['created_at'] ?? '');
            break;
        case 'forwarded':
            $value = (string)($row['department_head_approved_at'] ?? $row['created_at'] ?? '');
            break;
        case 'approved':
            $value = (string)($row['finalized_at'] ?? $row['personnel_checked_at'] ?? $row['department_head_approved_at'] ?? $row['created_at'] ?? '');
            break;
        case 'leave':
        default:
            $value = leave_request_filter_date($row);
            break;
    }
    $ts = strtotime($value);
    return $ts ?: 0;
}

function leave_request_apply_filters(array $rows, int $month, int $year, int $departmentId, bool $applyDepartmentFilter, string $sortBy, string $direction): array {
    $rows = array_values(array_filter($rows, function(array $row) use ($month, $year, $departmentId, $applyDepartmentFilter) {
        if ($applyDepartmentFilter && $departmentId > 0) {
            $rowDeptId = (int)($row['department_id'] ?? 0);
            if ($rowDeptId !== $departmentId) {
                return false;
            }
        }

        $filterDate = leave_request_filter_date($row);
        if ($filterDate === '' || $filterDate === '0000-00-00') {
            return !($month > 0 || $year > 0);
        }

        $ts = strtotime($filterDate);
        if (!$ts) {
            return !($month > 0 || $year > 0);
        }

        if ($month > 0 && (int)date('n', $ts) !== $month) {
            return false;
        }
        if ($year > 0 && (int)date('Y', $ts) !== $year) {
            return false;
        }
        return true;
    }));

    usort($rows, function(array $a, array $b) use ($sortBy, $direction) {
        $ta = leave_request_sort_timestamp($a, $sortBy);
        $tb = leave_request_sort_timestamp($b, $sortBy);
        if ($ta === $tb) {
            $ia = (int)($a['id'] ?? 0);
            $ib = (int)($b['id'] ?? 0);
            return strtolower($direction) === 'asc' ? ($ia <=> $ib) : ($ib <=> $ia);
        }
        return strtolower($direction) === 'asc' ? ($ta <=> $tb) : ($tb <=> $ta);
    });

    return $rows;
}

function trunc3($v): string {
    if ($v === null || $v === '') return '';
    $n = (float)$v;
    $t = floor($n * 1000) / 1000;
    return number_format($t, 3, '.', '');
}

function filter_leave_request_rows(array $rows, string $term): array {
    return pagination_filter_array($rows, $term, [
        function ($r) { return trim(($r['first_name'] ?? '') . ' ' . ($r['middle_name'] ?? '') . ' ' . ($r['last_name'] ?? '')); },
        'email', 'department', 'position', 'leave_type_name', 'leave_type', 'status', 'workflow_status', 'reason', 'manager_comments', 'department_head_comments', 'personnel_comments', 'print_status', 'start_date', 'end_date'
    ]);
}

$signatories = [];

try {
    $stmt = $db->query("SELECT key_name, name, position FROM system_signatories");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $s) {
        $signatories[$s['key_name']] = $s;
    }
} catch (Throwable $t) {
    $signatories = [];
}

$departmentFilterVisible = in_array($role, ['admin', 'personnel'], true);
$filterMonth = max(0, min(12, (int)($_GET['month'] ?? 0)));
$filterYear = max(0, (int)($_GET['year'] ?? 0));
$filterDepartmentId = $departmentFilterVisible ? max(0, (int)($_GET['department_id'] ?? 0)) : 0;
$sortBy = strtolower(trim((string)($_GET['sort_by'] ?? 'leave')));
if (!in_array($sortBy, ['leave', 'submitted', 'forwarded', 'approved'], true)) {
    $sortBy = 'leave';
}
$sortDirection = strtolower(trim((string)($_GET['direction'] ?? 'desc')));
if (!in_array($sortDirection, ['asc', 'desc'], true)) {
    $sortDirection = 'desc';
}
$autoOpenDetailId = max(0, (int)($_GET['open_detail'] ?? 0));

$departmentOptions = [];
try {
    $departmentOptions = $db->query("SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $t) {
    $departmentOptions = [];
}

$availableYears = [];
try {
    $yearRows = $db->query("SELECT DISTINCT YEAR(COALESCE(NULLIF(start_date, '0000-00-00'), DATE(created_at))) AS yr FROM leave_requests HAVING yr IS NOT NULL ORDER BY yr DESC")->fetchAll(PDO::FETCH_COLUMN);
    $availableYears = array_values(array_unique(array_filter(array_map('intval', $yearRows))));
} catch (Throwable $t) {
    $availableYears = [];
}
if ($filterYear > 0 && !in_array($filterYear, $availableYears, true)) {
    array_unshift($availableYears, $filterYear);
    $availableYears = array_values(array_unique($availableYears));
    rsort($availableYears);
}

$departmentHeadDepartmentIds = $isDepartmentHeadView
    ? leave_request_department_ids_for_user($db, $userId, (int)($_SESSION['emp_id'] ?? 0))
    : [];

function columnExists(PDO $db, string $table, string $column): bool {
    try {
        $stmt = $db->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $stmt->execute([$column]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $t) {
        return false;
    }
}

function bh_date(array $r, bool $hasTransDate): string {
    if ($hasTransDate && !empty($r['trans_date'])) return (string)$r['trans_date'];
    return substr((string)($r['created_at'] ?? ''), 0, 10);
}

function buildLeaveCardRows(PDO $db, int $empId, bool $hasTransDate, bool $hasSnapshots): array {
    $rows = [];

    // 1) LEAVE REQUESTS
    $leaveSql = "
        SELECT
            lr.id,
            lr.created_at,
            lr.start_date,
            lr.end_date,
            COALESCE(lt.name, lr.leave_type) AS leave_type,
            lr.status,
            lr.total_days,
            lr.snapshot_annual_balance,
            lr.snapshot_sick_balance,
            lr.snapshot_force_balance,
            lrf.cert_vacation_less_this_application,
            lrf.cert_sick_less_this_application
        FROM leave_requests lr
        LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id
        LEFT JOIN leave_request_forms lrf ON lrf.leave_request_id = lr.id
        WHERE lr.employee_id = ?
        ORDER BY COALESCE(lr.start_date, DATE(lr.created_at)) ASC, lr.created_at ASC, lr.id ASC
    ";
    $leaveStmt = $db->prepare($leaveSql);
    $leaveStmt->execute([$empId]);

    foreach ($leaveStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $leaveType = trim((string)$r['leave_type']);
        $typeLower = strtolower($leaveType);
        $statusRaw = strtolower(trim((string)$r['status']));
        $days = floatval($r['total_days']);

        if ($typeLower === 'undertime') {
            continue;
        }

        $txDate = !empty($r['start_date'])
            ? (string)$r['start_date']
            : substr((string)$r['created_at'], 0, 10);

        $vacEarn = 0.0;
        $sickEarn = 0.0;
        $vacDed = 0.0;
        $sickDed = 0.0;

        if ($statusRaw === 'approved') {
            if ($r['cert_vacation_less_this_application'] !== null || $r['cert_sick_less_this_application'] !== null) {
                $vacDed = floatval($r['cert_vacation_less_this_application'] ?? 0);
                $sickDed = floatval($r['cert_sick_less_this_application'] ?? 0);
            } elseif (in_array($typeLower, ['sick', 'sick leave'], true)) {
                $sickDed = $days;
            } else {
                $vacDed = $days;
            }
        }

        $vacBal = ($r['snapshot_annual_balance'] !== null && $r['snapshot_annual_balance'] !== '')
            ? floatval($r['snapshot_annual_balance'])
            : '';
        $sickBal = ($r['snapshot_sick_balance'] !== null && $r['snapshot_sick_balance'] !== '')
            ? floatval($r['snapshot_sick_balance'])
            : '';

        $rows[] = [
            'date' => $txDate,
            'particulars' => $leaveType . ' Leave',
            'vac_earned' => $vacEarn,
            'vac_deducted' => $vacDed,
            'vac_balance' => $vacBal,
            'sick_earned' => $sickEarn,
            'sick_deducted' => $sickDed,
            'sick_balance' => $sickBal,
            'status' => ucfirst($statusRaw),
            'entry_type' => 'leave',
            '_sort_ts' => strtotime($txDate ?: '1970-01-01'),
            '_sort_seq' => 1,
        ];
    }

    // 2) BUDGET HISTORY
    $budgetSql = "
        SELECT
            id,
            created_at" . ($hasTransDate ? ", trans_date" : "") . ",
            leave_type, action, old_balance, new_balance, notes
        FROM budget_history
        WHERE employee_id = ?
          AND (leave_request_id IS NULL OR leave_request_id = 0)
        ORDER BY " . ($hasTransDate ? "COALESCE(trans_date, DATE(created_at))" : "DATE(created_at)") . " ASC,
                 created_at ASC, id ASC
    ";
    $budgetStmt = $db->prepare($budgetSql);
    $budgetStmt->execute([$empId]);

    foreach ($budgetStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $leaveType = trim((string)$r['leave_type']);
        $typeLower = strtolower($leaveType);
        $actionLower = strtolower(trim((string)$r['action']));
        $notes = (string)($r['notes'] ?? '');

        $txDate = bh_date($r, $hasTransDate);

        $vacEarn = 0.0;
        $sickEarn = 0.0;
        $vacDed = 0.0;
        $sickDed = 0.0;
        $vacBal = '';
        $sickBal = '';

        if ($actionLower === 'undertime_paid' || $actionLower === 'undertime_unpaid') {
            $particulars = 'Undertime';
            $meta = [];

            if (preg_match_all('/([A-Z_]+)=([0-9.]+)/', $notes, $m, PREG_SET_ORDER)) {
                foreach ($m as $pair) {
                    $meta[$pair[1]] = $pair[2];
                }
            }

            if (isset($meta['UT_DEDUCT'])) $vacDed = (float)$meta['UT_DEDUCT'];
            if (isset($meta['VAC'])) $vacBal = (float)$meta['VAC'];
            if (isset($meta['SICK'])) $sickBal = (float)$meta['SICK'];
        } else {
            $old = floatval($r['old_balance']);
            $new = floatval($r['new_balance']);
            $deltaEarn = max(0, $new - $old);
            $deltaDed = max(0, $old - $new);

            if (in_array($actionLower, ['accrual', 'earning'], true)) {
                $particulars = 'Monthly Accrual';
                $vacEarn = $deltaEarn;
                $sickEarn = $deltaEarn;

                if (strpos($typeLower, 'sick') !== false) {
                    $sickBal = $new;
                } else {
                    $vacBal = $new;
                }
            } else {
                $particulars = ucfirst($actionLower) . ' ' . $leaveType;

                if (in_array($typeLower, ['annual', 'vacational', 'vacation', 'force'], true)) {
                    $vacEarn = $deltaEarn;
                    $vacDed = $deltaDed;
                    $vacBal = $new;
                } elseif ($typeLower === 'sick') {
                    $sickEarn = $deltaEarn;
                    $sickDed = $deltaDed;
                    $sickBal = $new;
                }
            }
        }

        $rows[] = [
            'date' => $txDate,
            'particulars' => $particulars,
            'vac_earned' => $vacEarn,
            'vac_deducted' => $vacDed,
            'vac_balance' => ($vacBal === '' ? '' : $vacBal),
            'sick_earned' => $sickEarn,
            'sick_deducted' => $sickDed,
            'sick_balance' => ($sickBal === '' ? '' : $sickBal),
            'status' => ucfirst($actionLower),
            'entry_type' => 'budget',
            '_sort_ts' => strtotime($txDate ?: '1970-01-01'),
            '_sort_seq' => 2,
        ];
    }

    usort($rows, function($a, $b) {
        $ta = $a['_sort_ts'] ?? 0;
        $tb = $b['_sort_ts'] ?? 0;
        if ($ta !== $tb) return $tb <=> $ta; // newest first

        $sa = $a['_sort_seq'] ?? 0;
        $sb = $b['_sort_seq'] ?? 0;
        if ($sa !== $sb) return $sa <=> $sb;

        return strcmp((string)($a['particulars'] ?? ''), (string)($b['particulars'] ?? ''));
    });

    foreach ($rows as &$rr) {
        unset($rr['_sort_ts'], $rr['_sort_seq']);
    }
    unset($rr);

    return array_slice($rows, 0, 8);
}

function normalizeLeaveTypePreviewKey(string $name): string {
    $key = strtolower(trim($name));
    $key = preg_replace('/\s+/', ' ', $key);
    $key = str_replace([' / ', ' /', '/ '], '/', $key);

    $aliases = [
        'vacation' => 'vacation leave',
        'vacational' => 'vacation leave',
        'annual' => 'vacation leave',
        'sick' => 'sick leave',
        'mandatory/force leave' => 'mandatory/forced leave',
        'mandatory force leave' => 'mandatory/forced leave',
        'mandatory/forced leave' => 'mandatory/forced leave',
        'force' => 'mandatory/forced leave',
        'force leave' => 'mandatory/forced leave',
        'forced' => 'mandatory/forced leave',
        'forced leave' => 'mandatory/forced leave',
        'mandatory leave' => 'mandatory/forced leave',
        'mandatory' => 'mandatory/forced leave',
    ];

    return $aliases[$key] ?? $key;
}


function isLateSickForPreview(array $row): bool {
    $type = normalizeLeaveTypePreviewKey((string)($row['leave_type_name'] ?? $row['leave_type'] ?? ''));
    if ($type !== 'sick leave') {
        return false;
    }

    $filingDate = trim((string)($row['filing_date'] ?? ''));
    $endDate = trim((string)($row['end_date'] ?? ''));
    if ($filingDate === '' || $endDate === '') {
        return false;
    }

    try {
        $filing = new DateTime($filingDate);
        $end = new DateTime($endDate);
        $end->modify('+1 month');
        return $filing > $end;
    } catch (Throwable $t) {
        return false;
    }
}


function computeProjectedBalances(array $row): array {
    $annualBefore = floatval($row['annual_balance'] ?? 0);
    $sickBefore   = floatval($row['sick_balance'] ?? 0);
    $forceBefore  = floatval($row['force_balance'] ?? 0);

    $days = floatval($row['total_days'] ?? 0);
    $type = normalizeLeaveTypePreviewKey((string)($row['leave_type_name'] ?? $row['leave_type'] ?? ''));
    $details = decodeLeaveRequestMeta($row['details_json'] ?? null);
    $forceBalanceOnly = !empty($details['force_balance_only']);

    $nonDeductTypes = [
        'maternity leave',
        'paternity leave',
        'special privilege leave',
        'solo parent leave',
        'vawc leave',
        '10-day vawc leave',
        'terminal leave',
        'adoption leave',
    ];

    $projected = [
        'annual_before' => $annualBefore,
        'sick_before'   => $sickBefore,
        'force_before'  => $forceBefore,
        'annual_after'  => $annualBefore,
        'sick_after'    => $sickBefore,
        'force_after'   => $forceBefore,
        'bucket'        => 'none',
    ];

    if (in_array($type, $nonDeductTypes, true)) {
        $projected['bucket'] = 'non_deduct';
    } elseif ($type === 'vacation leave') {
        $projected['annual_after'] = max(0, $annualBefore - $days);
        $projected['bucket'] = 'annual';
    } elseif ($type === 'sick leave') {
        if (isLateSickForPreview($row)) {
            $projected['bucket'] = 'sick_late';
        } else {
            $projected['sick_after'] = max(0, $sickBefore - $days);
            $projected['bucket'] = 'sick';
        }
    } elseif ($type === 'mandatory/forced leave') {
        $projected['force_after'] = max(0, $forceBefore - $days);
        if ($forceBalanceOnly) {
            $projected['bucket'] = 'force_only';
        } else {
            $projected['annual_after'] = max(0, $annualBefore - $days);
            $projected['bucket'] = 'annual_force';
        }
    }

    return $projected;
}


function decodeLeaveRequestMeta($raw): array {
    if ($raw === null || $raw === '') {
        return [];
    }
    $decoded = json_decode((string)$raw, true);
    return is_array($decoded) ? $decoded : [];
}

function leaveRequestLabelForKey(string $key): string {
    $map = [
        'location' => 'Location',
        'illness' => 'Illness / Condition',
        'other_purpose' => 'Other Purpose',
        'expected_delivery' => 'Expected Delivery',
        'calamity_location' => 'Calamity Location',
        'surgery_details' => 'Surgery Details',
        'monetization_reason' => 'Monetization Reason',
        'terminal_reason' => 'Terminal Leave Reason',
    ];

    return $map[$key] ?? ucwords(str_replace('_', ' ', $key));
}

function leaveRequestDetailFields(array $row): array {
    $details = decodeLeaveRequestMeta($row['details_json'] ?? '');
    if (empty($details)) {
        return [];
    }

    $ordered = [
        'location',
        'illness',
        'other_purpose',
        'expected_delivery',
        'calamity_location',
        'surgery_details',
        'monetization_reason',
        'terminal_reason',
    ];

    $out = [];
    foreach ($ordered as $key) {
        if (!array_key_exists($key, $details)) {
            continue;
        }
        $value = trim((string)$details[$key]);
        if ($value === '') {
            continue;
        }
        $out[] = ['label' => leaveRequestLabelForKey($key), 'value' => $value];
        unset($details[$key]);
    }

    foreach ($details as $key => $value) {
        if (is_array($value)) {
            $value = implode(', ', array_filter(array_map('strval', $value), fn($v) => trim($v) !== ''));
        }
        $value = trim((string)$value);
        if ($value === '') {
            continue;
        }
        $out[] = ['label' => leaveRequestLabelForKey((string)$key), 'value' => $value];
    }

    return $out;
}

function leaveRequestSupportFlags(array $row): array {
    $flags = [];

    $supporting = decodeLeaveRequestMeta($row['supporting_documents_json'] ?? '');
    foreach ($supporting as $entry) {
        if (is_array($entry)) {
            $entry = implode(', ', array_filter(array_map('strval', $entry), fn($v) => trim($v) !== ''));
        }
        $entry = trim((string)$entry);
        if ($entry !== '') {
            $flags[] = $entry;
        }
    }

    if (!empty($row['medical_certificate_attached'])) {
        $flags[] = 'Medical certificate attached';
    }
    if (!empty($row['affidavit_attached'])) {
        $flags[] = 'Affidavit attached';
    }
    if (!empty($row['emergency_case'])) {
        $flags[] = 'Emergency case marked';
    }

    return array_values(array_unique($flags));
}


function fetchLeaveAttachmentMap(PDO $db, array $leaveIds): array {
    $ids = array_values(array_unique(array_filter(array_map('intval', $leaveIds), fn($id) => $id > 0)));
    if (empty($ids)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("
        SELECT *
        FROM leave_attachments
        WHERE leave_request_id IN ($placeholders)
        ORDER BY created_at ASC, id ASC
    ");
    try {
        $stmt->execute($ids);
    } catch (Throwable $t) {
        return [];
    }

    $out = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $out[(int)$row['leave_request_id']][] = $row;
    }
    return $out;
}

function leaveRequestStatusChipClass(string $value): string {
    $value = strtolower(trim($value));
    if ($value === '') return 'neutral';
    if (str_contains($value, 'approved') || str_contains($value, 'finalized') || str_contains($value, 'printed')) return 'success';
    if (str_contains($value, 'reject') || str_contains($value, 'return')) return 'danger';
    if (str_contains($value, 'pending')) return 'warning';
    return 'neutral';
}

function renderLeaveRequestDetailModal(array $row, string $modalId, array $options = []): string {
    $badgeText = $options['badge_text'] ?? 'Leave Request Details';
    $projected = $options['projected'] ?? null;
    $previewRows = $options['preview_rows'] ?? [];
    $showBalanceSnapshot = $options['show_balance_snapshot'] ?? true;
    $extraActionHtml = $options['extra_action_html'] ?? '';

    $employeeName = trim(($row['first_name'] ?? '') . ' ' . ($row['middle_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
    $details = leaveRequestDetailFields($row);
    $supportFlags = leaveRequestSupportFlags($row);
    $attachments = is_array($options['attachments'] ?? null) ? $options['attachments'] : [];

    $requestNotes = [
        'Employee Reason' => trim((string)($row['reason'] ?? '')),
        'Department Head Comment' => trim((string)($row['department_head_comments'] ?? '')),
        'Personnel Comment' => trim((string)($row['personnel_comments'] ?? '')),
        'Manager Comment' => trim((string)($row['manager_comments'] ?? '')),
    ];

    ob_start();
    ?>
    <div id="<?= $modalId; ?>" class="modal">
        <div class="modal-content review-modal request-detail-modal">
            <button type="button" class="modal-close" onclick="closeModal('<?= $modalId; ?>')">&times;</button>

            <div class="review-modal-header">
                <div>
                    <h3 style="margin-bottom:6px;"><?= safe_h($employeeName !== '' ? $employeeName : 'Leave Request'); ?></h3>
                    <?php if (!empty($row['email'])): ?>
                        <p class="review-muted"><?= safe_h($row['email']); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($row['department']) || !empty($row['position'])): ?>
                        <p class="review-muted"><?= safe_h($row['department'] ?? ''); ?><?= (!empty($row['department']) && !empty($row['position'])) ? ' • ' : ''; ?><?= safe_h($row['position'] ?? ''); ?></p>
                    <?php endif; ?>
                </div>
                <div class="review-badge"><?= safe_h($badgeText); ?></div>
            </div>

            <div class="review-grid request-detail-grid">
                <div class="review-panel">
                    <h4>Request Summary</h4>
                    <div class="review-kv"><span>Leave Type</span><strong><?= safe_h($row['leave_type_name'] ?? $row['leave_type'] ?? '—'); ?></strong></div>
                    <div class="review-kv"><span>Date Range</span><strong><?= safe_h(app_format_date_range($row['start_date'] ?? '', $row['end_date'] ?? '')); ?></strong></div>
                    <div class="review-kv"><span>Total Days</span><strong><?= trunc3($row['total_days'] ?? 0); ?></strong></div>
                    <div class="review-kv"><span>Subtype</span><strong><?= safe_h($row['leave_subtype'] ?? '—'); ?></strong></div>
                    <div class="review-kv"><span>Commutation</span><strong><?= safe_h($row['commutation_requested'] ?? ($row['commutation'] ?? '—')); ?></strong></div>
                </div>

                <div class="review-panel">
                    <h4>Workflow Status</h4>
                    <div class="review-kv"><span>Status</span><strong><span class="request-chip request-chip-<?= leaveRequestStatusChipClass((string)($row['status'] ?? '')); ?>"><?= safe_h($row['status'] ?? '—'); ?></span></strong></div>
                    <div class="review-kv"><span>Workflow</span><strong><?= safe_h($row['workflow_status'] ?? '—'); ?></strong></div>
                    <div class="review-kv"><span>Print Status</span><strong><?= safe_h($row['print_status'] ?? '—'); ?></strong></div>
                    <div class="review-kv"><span>Submitted</span><strong><?= safe_h(app_format_date($row['created_at'] ?? '')); ?></strong></div>
                    <div class="review-kv"><span>Filed</span><strong><?= safe_h(app_format_date($row['filing_date'] ?? '')); ?></strong></div>
                </div>

                <div class="review-panel">
                    <h4>Employee Information</h4>
                    <div class="review-kv"><span>Employee ID</span><strong><?= safe_h((string)($row['employee_id'] ?? '—')); ?></strong></div>
                    <div class="review-kv"><span>Department</span><strong><?= safe_h($row['department'] ?? '—'); ?></strong></div>
                    <div class="review-kv"><span>Position</span><strong><?= safe_h($row['position'] ?? '—'); ?></strong></div>
                    <div class="review-kv"><span>Approved At</span><strong><?= safe_h(app_format_date($row['department_head_approved_at'] ?? '')); ?></strong></div>
                    <div class="review-kv"><span>Personnel Checked</span><strong><?= safe_h(app_format_date($row['personnel_checked_at'] ?? '')); ?></strong></div>
                </div>

                <?php if ($showBalanceSnapshot): ?>
                <div class="review-panel">
                    <h4>Balance Snapshot</h4>
                    <div class="review-kv"><span>Vacational</span><strong><?= trunc3($row['snapshot_annual_balance'] ?? 0); ?></strong></div>
                    <div class="review-kv"><span>Sick</span><strong><?= trunc3($row['snapshot_sick_balance'] ?? 0); ?></strong></div>
                    <div class="review-kv"><span>Force</span><strong><?= trunc3($row['snapshot_force_balance'] ?? 0); ?></strong></div>
                    <div class="review-kv"><span>Current Annual</span><strong><?= trunc3($row['annual_balance'] ?? 0); ?></strong></div>
                    <div class="review-kv"><span>Current Sick</span><strong><?= trunc3($row['sick_balance'] ?? 0); ?></strong></div>
                </div>
                <?php endif; ?>

                <?php if (is_array($projected)): ?>
                <div class="review-panel">
                    <h4>Projected After Approval</h4>
                    <div class="review-kv"><span>Vacational</span><strong><?= trunc3($projected['annual_after'] ?? 0); ?></strong></div>
                    <div class="review-kv"><span>Sick</span><strong><?= trunc3($projected['sick_after'] ?? 0); ?></strong></div>
                    <div class="review-kv"><span>Force</span><strong><?= trunc3($projected['force_after'] ?? 0); ?></strong></div>
                    <div class="review-kv"><span>Bucket</span><strong><?= safe_h(str_replace('_', ' + ', (string)($projected['bucket'] ?? 'none'))); ?></strong></div>
                </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($details)): ?>
                <div class="review-panel review-panel-full" style="margin-top:18px;">
                    <h4>Leave-Specific Details</h4>
                    <div class="request-detail-list">
                        <?php foreach ($details as $detail): ?>
                            <div class="request-detail-item">
                                <span><?= safe_h($detail['label']); ?></span>
                                <strong><?= safe_h($detail['value']); ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="review-panel review-panel-full" style="margin-top:18px;">
                <h4>Comments & Notes</h4>
                <div class="request-note-grid">
                    <?php $hasNotes = false; foreach ($requestNotes as $label => $value): if ($value === '') continue; $hasNotes = true; ?>
                        <div class="request-note-card">
                            <span><?= safe_h($label); ?></span>
                            <strong><?= nl2br(safe_h($value)); ?></strong>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$hasNotes): ?>
                        <p class="review-muted">No notes or review comments recorded yet.</p>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($supportFlags)): ?>
                <div class="review-panel review-panel-full" style="margin-top:18px;">
                    <h4>Supporting Documents & Flags</h4>
                    <p class="review-muted" style="margin-bottom:10px;">Flags like <strong>travel_authority</strong> are request indicators. Uploaded files, when present, appear separately under <strong>Uploaded Attachments</strong>.</p>
                    <div class="request-chip-list">
                        <?php foreach ($supportFlags as $flag): ?>
                            <span class="request-chip request-chip-neutral"><?= safe_h($flag); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($attachments)): ?>
                <div class="review-panel review-panel-full" style="margin-top:18px;">
                    <h4>Uploaded Attachments</h4>
                    <div class="request-note-grid">
                        <?php foreach ($attachments as $attachment): ?>
                            <?php
                                $fileUrl = Auth::appUrl((string)($attachment['file_path'] ?? ''));
                                $fileSize = (int)($attachment['file_size'] ?? 0);
                                $mimeType = strtolower((string)($attachment['mime_type'] ?? ''));
                                $originalName = (string)($attachment['original_name'] ?? 'Attachment');
                                $isPreviewable = str_starts_with($mimeType, 'image/') || $mimeType === 'application/pdf';
                            ?>
                            <div class="request-note-card">
                                <span><?= safe_h($attachment['document_type'] ?? 'supporting_document'); ?></span>
                                <strong style="word-break:break-word;"><?= safe_h($originalName); ?></strong>
                                <small class="review-muted"><?= $fileSize > 0 ? safe_h(number_format($fileSize / 1024 / 1024, 2) . ' MB') : '—'; ?></small>
                                <div class="attachment-action-row" style="margin-top:8px;">
                                    <?php if ($isPreviewable): ?>
                                        <button
                                            type="button"
                                            class="btn-export"
                                            onclick='openAttachmentPreview(<?= json_encode($fileUrl, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT); ?>, <?= json_encode($originalName, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT); ?>, <?= json_encode($mimeType, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT); ?>)'>
                                            Preview
                                        </button>
                                    <?php endif; ?>
                                    <a class="btn-export" href="<?= safe_h($fileUrl); ?>" target="_blank" rel="noopener">Open File</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($previewRows)): ?>
                <div class="review-panel review-panel-full" style="margin-top:18px;">
                    <div class="review-panel-head">
                        <div>
                            <h4 style="margin-bottom:4px;">Leave Card Preview</h4>
                            <p class="review-muted">Latest employee transactions and balance movement</p>
                        </div>
                        <a href="reports.php?type=leave_card&employee_id=<?= (int)$row['employee_id']; ?>" target="_blank" class="btn-export">Open Full Leave Card</a>
                    </div>
                    <div class="review-leave-card-wrap modern-preview-wrap">
                        <table class="review-leave-card-table modern-preview-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Particulars</th>
                                    <th>Vac Earned</th>
                                    <th>Vac Deducted</th>
                                    <th>Vac Balance</th>
                                    <th>Sick Earned</th>
                                    <th>Sick Deducted</th>
                                    <th>Sick Balance</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($previewRows as $previewRow): ?>
                                    <?php
                                        $vb = $previewRow['vac_balance'] ?? '';
                                        $sb = $previewRow['sick_balance'] ?? '';
                                        $statusTextRaw = (string)($previewRow['status'] ?? '');
                                        $statusText = safe_h($statusTextRaw);
                                        $entryType = $previewRow['entry_type'] ?? '';
                                        $rowClass = $entryType === 'leave' ? 'preview-row-leave' : 'preview-row-budget';
                                        $statusClass = strtolower(preg_replace('/[^a-z0-9]+/', '-', $statusTextRaw));
                                    ?>
                                    <tr class="<?= $rowClass; ?>">
                                        <td><?= safe_h(app_format_date($previewRow['date'] ?? '')); ?></td>
                                        <td class="preview-particulars"><?= safe_h($previewRow['particulars'] ?? ''); ?></td>
                                        <td><?= ((($previewRow['vac_earned'] ?? 0) != 0) ? trunc3($previewRow['vac_earned']) : '—'); ?></td>
                                        <td><?= ((($previewRow['vac_deducted'] ?? 0) != 0) ? trunc3($previewRow['vac_deducted']) : '—'); ?></td>
                                        <td><?= ($vb === '' ? '—' : trunc3($vb)); ?></td>
                                        <td><?= ((($previewRow['sick_earned'] ?? 0) != 0) ? trunc3($previewRow['sick_earned']) : '—'); ?></td>
                                        <td><?= ((($previewRow['sick_deducted'] ?? 0) != 0) ? trunc3($previewRow['sick_deducted']) : '—'); ?></td>
                                        <td><?= ($sb === '' ? '—' : trunc3($sb)); ?></td>
                                        <td><span class="preview-status-badge preview-status-<?= $statusClass !== '' ? $statusClass : 'default'; ?>"><?= $statusText !== '' ? $statusText : '—'; ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <div class="review-modal-actions">
                <?= $extraActionHtml; ?>
                <button type="button" class="btn-secondary" onclick="closeModal('<?= $modalId; ?>')">Close</button>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

$whereDeptHead = "lr.workflow_status = 'pending_department_head' AND lr.status = 'pending'";
$wherePersonnel = "lr.workflow_status = 'pending_personnel' AND lr.status = 'pending'";

if (in_array($role, ['manager','department_head'], true)) {
    $whereDeptHead .= " AND lr.department_head_user_id = " . $userId;
}
if ($isDepartmentHeadView) {
    if (empty($departmentHeadDepartmentIds)) {
        $whereDeptHead .= " AND 1 = 0";
    } else {
        $whereDeptHead .= " AND lr.department_id IN (" . implode(',', array_map('intval', $departmentHeadDepartmentIds)) . ")";
    }
}
if (in_array($role, ['personnel','hr'], true)) {
    // personnel sees only their stage
} elseif ($role !== 'admin') {
    $wherePersonnel .= " AND 1 = 0";
}

$pendingDeptHead = $db->query("
    SELECT lr.*, e.first_name, e.middle_name, e.last_name, e.department, e.position, e.annual_balance, e.sick_balance, e.force_balance, u.email,
           COALESCE(lt.name, lr.leave_type) AS leave_type_name
    FROM leave_requests lr
    JOIN employees e ON lr.employee_id = e.id
    LEFT JOIN users u ON e.user_id = u.id
    LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id
    WHERE {$whereDeptHead}
    ORDER BY lr.start_date DESC
")->fetchAll(PDO::FETCH_ASSOC);

$pendingPersonnel = $db->query("
    SELECT
        lr.*,
        e.first_name,
        e.middle_name,
        e.last_name,
        e.department,
        e.position,
        e.annual_balance,
        e.sick_balance,
        e.force_balance,
        u.email,
        COALESCE(lt.name, lr.leave_type) AS leave_type_name
    FROM leave_requests lr
    JOIN employees e ON lr.employee_id = e.id
    LEFT JOIN users u ON e.user_id = u.id
    LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id
    WHERE {$wherePersonnel}
    ORDER BY lr.start_date DESC
")->fetchAll(PDO::FETCH_ASSOC);

$finalizedWhere = "(lr.workflow_status = 'finalized' OR lr.status = 'approved')";
$returnedWhere = "(lr.workflow_status IN ('rejected_department_head','returned_by_personnel') OR lr.status = 'rejected')";

if ($isPersonnelOnlyView) {
    $finalizedWhere .= " AND COALESCE(lr.print_status, '') IN ('', 'pending_print', 'printed')";
}
if ($isDepartmentHeadView) {
    if (empty($departmentHeadDepartmentIds)) {
        $finalizedWhere .= " AND 1 = 0";
        $returnedWhere .= " AND 1 = 0";
    } else {
        $deptIn = implode(',', array_map('intval', $departmentHeadDepartmentIds));
        $finalizedWhere .= " AND lr.department_id IN ($deptIn)";
        $returnedWhere .= " AND lr.department_id IN ($deptIn)";
    }
}

$finalized = $db->query("
    SELECT lr.*, e.first_name, e.middle_name, e.last_name, e.department, e.position, e.annual_balance, e.sick_balance, e.force_balance, u.email,
           COALESCE(lt.name, lr.leave_type) AS leave_type_name
    FROM leave_requests lr
    JOIN employees e ON lr.employee_id = e.id
    LEFT JOIN users u ON e.user_id = u.id
    LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id
    WHERE {$finalizedWhere}
    ORDER BY lr.start_date DESC
")->fetchAll(PDO::FETCH_ASSOC);

$returnedOrRejected = $db->query("
    SELECT lr.*, e.first_name, e.middle_name, e.last_name, e.department, e.position, e.annual_balance, e.sick_balance, e.force_balance, u.email,
           COALESCE(lt.name, lr.leave_type) AS leave_type_name
    FROM leave_requests lr
    JOIN employees e ON lr.employee_id = e.id
    LEFT JOIN users u ON e.user_id = u.id
    LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id
    WHERE {$returnedWhere}
    ORDER BY lr.start_date DESC
")->fetchAll(PDO::FETCH_ASSOC);

$hasTransDate = columnExists($db, 'budget_history', 'trans_date');
$hasSnapshots = columnExists($db, 'leave_requests', 'snapshot_annual_balance') && columnExists($db, 'leave_requests', 'snapshot_sick_balance');

$leaveCardPreviewMap = [];
$personnelEmployeeIds = array_values(array_unique(array_map(function($r) {
    return (int)$r['employee_id'];
}, $pendingPersonnel)));

foreach ($personnelEmployeeIds as $empId) {
    $leaveCardPreviewMap[$empId] = buildLeaveCardRows($db, $empId, $hasTransDate, $hasSnapshots);
}

// Prepare archived requests for the "Archived" toggle
if ($role === 'manager') {
    $archivedQuery = $db->prepare("
        SELECT lr.*, e.first_name, e.middle_name, e.last_name, e.department, e.position, e.annual_balance, e.sick_balance, e.force_balance, COALESCE(lt.name, lr.leave_type) AS leave_type_name
        FROM leave_requests lr
        JOIN employees e ON lr.employee_id = e.id
        LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id
        WHERE lr.status IN ('approved', 'rejected', 'cancelled') AND e.manager_id = ?
          AND lr.created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
        ORDER BY lr.created_at DESC
        LIMIT 50
    ");
    $archivedQuery->execute([$_SESSION['emp_id'] ?? 0]);
} else {
    $archivedQuery = $db->prepare("
        SELECT lr.*, e.first_name, e.middle_name, e.last_name, e.department, e.position, e.annual_balance, e.sick_balance, e.force_balance, COALESCE(lt.name, lr.leave_type) AS leave_type_name
        FROM leave_requests lr
        JOIN employees e ON lr.employee_id = e.id
        LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id
        WHERE lr.status IN ('approved', 'rejected', 'cancelled')
          AND lr.created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
        ORDER BY lr.created_at DESC
        LIMIT 100
    ");
    $archivedQuery->execute();
}
$archived = $archivedQuery->fetchAll(PDO::FETCH_ASSOC);

$pendingDeptHead = leave_request_apply_filters($pendingDeptHead, $filterMonth, $filterYear, $filterDepartmentId, $departmentFilterVisible, $sortBy, $sortDirection);
$pendingPersonnel = leave_request_apply_filters($pendingPersonnel, $filterMonth, $filterYear, $filterDepartmentId, $departmentFilterVisible, $sortBy, $sortDirection);
$finalized = leave_request_apply_filters($finalized, $filterMonth, $filterYear, $filterDepartmentId, $departmentFilterVisible, $sortBy, $sortDirection);
$returnedOrRejected = leave_request_apply_filters($returnedOrRejected, $filterMonth, $filterYear, $filterDepartmentId, $departmentFilterVisible, $sortBy, $sortDirection);
$archived = leave_request_apply_filters($archived, $filterMonth, $filterYear, $filterDepartmentId, $departmentFilterVisible, $sortBy, $sortDirection);


$pdhSearch = trim((string)($_GET['pdh_q'] ?? ''));
$ppSearch = trim((string)($_GET['pp_q'] ?? ''));
$finSearch = trim((string)($_GET['fin_q'] ?? ''));
$rejSearch = trim((string)($_GET['rej_q'] ?? ''));
$archSearch = trim((string)($_GET['arch_q'] ?? ''));

$pendingDeptHead = filter_leave_request_rows($pendingDeptHead, $pdhSearch);
$pendingPersonnel = filter_leave_request_rows($pendingPersonnel, $ppSearch);
$finalized = filter_leave_request_rows($finalized, $finSearch);
$returnedOrRejected = filter_leave_request_rows($returnedOrRejected, $rejSearch);
$archived = filter_leave_request_rows($archived, $archSearch);

$pendingDeptHeadPagination = paginate_array($pendingDeptHead, (int)($_GET['pdh_page'] ?? 1), 8);
$pendingDeptHead = $pendingDeptHeadPagination['items'];

$pendingPersonnelPagination = paginate_array($pendingPersonnel, (int)($_GET['pp_page'] ?? 1), 8);
$pendingPersonnel = $pendingPersonnelPagination['items'];

$finalizedPagination = paginate_array($finalized, (int)($_GET['fin_page'] ?? 1), 10);
$finalized = $finalizedPagination['items'];

$returnedPagination = paginate_array($returnedOrRejected, (int)($_GET['rej_page'] ?? 1), 10);
$returnedOrRejected = $returnedPagination['items'];

$archivedPagination = paginate_array($archived, (int)($_GET['arch_page'] ?? 1), 10);
$archived = $archivedPagination['items'];

$visibleLeaveIds = array_merge(
    array_column($pendingDeptHead, 'id'),
    array_column($pendingPersonnel, 'id'),
    array_column($finalized, 'id'),
    array_column($returnedOrRejected, 'id'),
    array_column($archived, 'id')
);
$leaveAttachmentMap = fetchLeaveAttachmentMap($db, $visibleLeaveIds);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html>
<head>
    <base href="<?= htmlspecialchars(rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/', ENT_QUOTES, 'UTF-8'); ?>">
    <title>Leave Requests</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <script src="../assets/js/script.js"></script>
</head>
<body>
<?php include __DIR__ . '/partials/sidebar.php'; ?>

<div class="app-main">
    <?php
    $tabBaseParams = [
        'month' => $filterMonth > 0 ? $filterMonth : null,
        'year' => $filterYear > 0 ? $filterYear : null,
        'department_id' => $departmentFilterVisible && $filterDepartmentId > 0 ? $filterDepartmentId : null,
        'sort_by' => $sortBy !== 'leave' ? $sortBy : null,
        'direction' => $sortDirection !== 'desc' ? $sortDirection : null,
    ];
    ?>
    <div class="page-header leave-requests-header">
        <div class="page-title-group">
            <h1>Leave Requests</h1>
            <p class="page-subtitle">Review, track, and manage employee leave submissions</p>
        </div>
        <div class="page-actions leave-requests-header-actions">
            <div class="filter-tabs" id="leaveRequestTabs">
            <?php
            $tabs = $isPersonnelOnlyView
                ? [
                    'pending' => 'Pending',
                    'approved' => 'Approved',
                    'rejected' => 'Rejected',
                ]
                : ($isDepartmentHeadView
                    ? [
                        'all' => 'All',
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]
                    : [
                        'all' => 'All',
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        'archived' => 'Archived',
                    ]);
            foreach ($tabs as $key => $label) {
                $active = ($tab === $key) ? ' is-active' : '';
                $tabUrl = $_SERVER['PHP_SELF'] . '?' . http_build_query(array_filter(array_merge($tabBaseParams, ['tab' => $key]), fn($v) => $v !== null && $v !== ''));
                echo '<a href="' . safe_h($tabUrl) . '" class="filter-tab' . $active . '" data-tab="' . safe_h($key) . '">' . htmlspecialchars($label) . '</a>';
            }
            ?>
            </div>

            <form method="get" class="leave-request-filter-bar leave-request-filter-bar-inline" id="leaveRequestFilterForm" data-auto-submit="1">
                <input type="hidden" name="tab" value="<?= safe_h($tab); ?>">

                <div class="leave-request-filter-grid">
                    <?php if ($departmentFilterVisible): ?>
                    <label>
                        <span>Department</span>
                        <select name="department_id" class="filter-control auto-submit-filter" aria-label="Department filter">
                            <option value="0">All Departments</option>
                            <?php foreach ($departmentOptions as $deptOption): ?>
                                <option value="<?= (int)$deptOption['id']; ?>" <?= $filterDepartmentId === (int)$deptOption['id'] ? 'selected' : ''; ?>><?= safe_h($deptOption['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <?php endif; ?>

                    <label>
                        <span>Month</span>
                        <select name="month" class="filter-control auto-submit-filter" aria-label="Month filter">
                            <option value="0">All Months</option>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?= $m; ?>" <?= $filterMonth === $m ? 'selected' : ''; ?>><?= safe_h(date('F', mktime(0, 0, 0, $m, 1))); ?></option>
                            <?php endfor; ?>
                        </select>
                    </label>

                    <label>
                        <span>Year</span>
                        <select name="year" class="filter-control filter-year-select auto-submit-filter" aria-label="Year filter">
                            <option value="0">Year</option>
                            <?php foreach ($availableYears as $yearOption): ?>
                                <option value="<?= (int)$yearOption; ?>" <?= $filterYear === (int)$yearOption ? 'selected' : ''; ?>><?= (int)$yearOption; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="inline-filter-group inline-filter-group-sortby">
                        <span class="inline-filter-label">Sort by</span>
                        <select name="sort_by" class="filter-control auto-submit-filter" aria-label="Sort by">
                            <option value="leave" <?= $sortBy === 'leave' ? 'selected' : ''; ?>>Date of Leave</option>
                            <option value="submitted" <?= $sortBy === 'submitted' ? 'selected' : ''; ?>>Date Submitted</option>
                            <option value="forwarded" <?= $sortBy === 'forwarded' ? 'selected' : ''; ?>>Date Forwarded</option>
                            <option value="approved" <?= $sortBy === 'approved' ? 'selected' : ''; ?>>Date Approved</option>
                        </select>
                    </label>

                    <label>
                        <span>Direction</span>
                        <select name="direction" class="filter-control auto-submit-filter" aria-label="Sort direction">
                            <option value="desc" <?= $sortDirection === 'desc' ? 'selected' : ''; ?>>Newest First</option>
                            <option value="asc" <?= $sortDirection === 'asc' ? 'selected' : ''; ?>>Oldest First</option>
                        </select>
                    </label>
                </div>

                <div class="leave-request-filter-actions">
                    <button type="submit" class="btn-secondary compact-filter-btn">Apply</button>
                    <a class="btn btn-link-like compact-filter-btn" href="leave_requests.php?tab=<?= safe_h($tab); ?>">Reset</a>
                </div>
            </form>
        </div>
    </div>

<div id="section-pending" style="<?= (($isPersonnelOnlyView && $tab === 'pending') || (!$isPersonnelOnlyView && ($tab === 'all' || $tab === 'pending'))) ? '' : 'display:none;'; ?>">
        <?php if ($showPendingDepartmentHead): ?>
        <div class="ui-card mb-6 ajax-fragment" data-fragment-id="leave-pdh" data-page-param="pdh_page" data-search-param="pdh_q">
            <h3>Pending Department Head Approval</h3>
            <div class="fragment-toolbar">
                <div class="search-input">
                    <input class="form-control live-search-input" type="text" name="pdh_q" value="<?= htmlspecialchars($pdhSearch, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search pending department head requests...">
                </div>
                <div class="fragment-summary">Showing <?= $pendingDeptHeadPagination['from']; ?>–<?= $pendingDeptHeadPagination['to']; ?> of <?= $pendingDeptHeadPagination['total']; ?> requests</div>
            </div>
        <?php if (empty($pendingDeptHead)): ?>
            <p>No requests pending for Department Head approval.</p>
        <?php else: ?>
            <?php $deptActionModalsHtml = ''; ?>
            <div class="table-wrap">
                <table class="ui-table">
                    <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Type</th>
                        <th>Dates</th>
                        <th>Days</th>
                        <th>Reason</th>
                        <th>Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($pendingDeptHead as $r): ?>
                        <?php
                        $deptEmployeeName = trim($r['first_name'].' '.($r['middle_name'] ?? '').' '.$r['last_name']);
                        ob_start();
                        ?>
                        <tr class="personnel-row">
                            <td class="col-employee">
                                <div class="personnel-employee-cell">
                                    <strong><?= safe_h($deptEmployeeName); ?></strong>
                                    <div class="subtext"><?= safe_h($r['email']); ?></div>
                                </div>
                            </td>

                            <td class="col-type">
                                <span class="leave-type-pill"><?= safe_h($r['leave_type_name']); ?></span>
                            </td>

                            <td class="col-dates">
                                <div class="date-stack">
                                    <span><?= safe_h(app_format_date($r['start_date'] ?? '')); ?></span>
                                    <span class="date-arrow">to</span>
                                    <span><?= safe_h(app_format_date($r['end_date'] ?? '')); ?></span>
                                </div>
                            </td>

                            <td class="col-days">
                                <strong><?= trunc3($r['total_days']); ?></strong>
                            </td>

                            <td class="col-comment">
                                <div class="comment-preview" title="<?= safe_h($r['reason'] ?? ''); ?>">
                                    <?= safe_h($r['reason'] ?? '—'); ?>
                                </div>
                            </td>

                            <td class="col-action">
                                <div class="personnel-action-bar">
                                    <button type="button"
                                            class="icon-action-btn labelled"
                                            onclick="openModal('deptDetailModal_<?= (int)$r['id']; ?>')"
                                            title="View full request details">
                                        <span class="action-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 72 72" fill="currentColor" style="flex-shrink:0;"><path d="M 31 11 C 19.973 11 11 19.973 11 31 C 11 42.027 19.973 51 31 51 C 34.974166 51 38.672385 49.821569 41.789062 47.814453 L 54.726562 60.751953 C 56.390563 62.415953 59.088953 62.415953 60.751953 60.751953 C 62.415953 59.087953 62.415953 56.390563 60.751953 54.726562 L 47.814453 41.789062 C 49.821569 38.672385 51 34.974166 51 31 C 51 19.973 42.027 11 31 11 z M 31 19 C 37.616 19 43 24.384 43 31 C 43 37.616 37.616 43 31 43 C 24.384 43 19 37.616 19 31 C 19 24.384 24.384 19 31 19 z"/></svg></span>
                                        <span class="action-label">Details</span>
                                    </button>

                                    <button type="button"
                                            class="icon-action-btn labelled icon-approve"
                                            onclick="openModal('deptActionModal_<?= (int)$r['id']; ?>')"
                                            title="Approve or reject request">
                                        <span class="action-icon">⚙</span>
                                        <span class="action-label">Action</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php
                        $rowHtml = ob_get_clean();
                        echo $rowHtml;

                        ob_start();
                        ?>
                        <div id="deptActionModal_<?= (int)$r['id']; ?>" class="modal">
                            <div class="modal-content floating-action-modal small-action-modal">
                                <button type="button" class="modal-close" onclick="closeModal('deptActionModal_<?= (int)$r['id']; ?>')">&times;</button>
                                <h3 style="margin-bottom:14px;">Department Head Action</h3>
                                <p class="review-muted" style="margin-bottom:14px;"><?= safe_h($deptEmployeeName); ?> • <?= safe_h($r['leave_type_name']); ?></p>

                                <form method="POST" action="../controllers/LeaveController.php" class="mini-action-form">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="leave_id" value="<?= (int)$r['id']; ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit">Approve &amp; Forward</button>
                                </form>

                                <form method="POST" action="../controllers/LeaveController.php" class="mini-action-form">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="leave_id" value="<?= (int)$r['id']; ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <input type="text" name="comments" placeholder="Reason" required>
                                    <button type="submit" class="danger-btn">Reject</button>
                                </form>
                            </div>
                        </div>
                        <?php
                        $deptActionModalsHtml .= renderLeaveRequestDetailModal($r, 'deptDetailModal_' . (int)$r['id'], [
                            'badge_text' => 'Pending Department Head Approval',
                            'show_balance_snapshot' => false,
                            'attachments' => $leaveAttachmentMap[(int)$r['id']] ?? [],
                        ]);
                        $deptActionModalsHtml .= ob_get_clean();
                        ?>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?= pagination_render($pendingDeptHeadPagination, 'pdh_page', ['tab' => $tab]); ?>
            <?= $deptActionModalsHtml; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($showPendingPersonnel): ?>
    <div class="ui-card mb-6 ajax-fragment" data-fragment-id="leave-pp" data-page-param="pp_page" data-search-param="pp_q">
        <h3>Pending Personnel Review</h3>
        <div class="fragment-toolbar">
            <div class="search-input">
                <input class="form-control live-search-input" type="text" name="pp_q" value="<?= htmlspecialchars($ppSearch, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search pending personnel requests...">
            </div>
            <div class="fragment-summary">Showing <?= $pendingPersonnelPagination['from']; ?>–<?= $pendingPersonnelPagination['to']; ?> of <?= $pendingPersonnelPagination['total']; ?> requests</div>
        </div>
        <?php if (empty($pendingPersonnel)): ?>
            <p>No requests pending for personnel review.</p>
        <?php else: ?>
            <?php $personnelModalHtml = ''; ?>
            <div class="table-wrap">
                <table class="ui-table">
                    <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Type</th>
                        <th>Dates</th>
                        <th>Days</th>
                        <th>Balance</th>
                        <th>After Approval</th>
                        <th>Action</th>
                    </tr>
                    </thead>
                    <tbody>

                    <?php foreach ($pendingPersonnel as $r): ?>
                        <?php
                        $employeeName = trim($r['first_name'].' '.($r['middle_name'] ?? '').' '.$r['last_name']);
                        $projected = computeProjectedBalances($r);
                        $modalId = 'reviewModal_' . (int)$r['id'];
                        $previewRows = $leaveCardPreviewMap[(int)$r['employee_id']] ?? [];
                        $personnelModalHtml .= renderLeaveRequestDetailModal($r, $modalId, [
                            'badge_text' => 'Pending Personnel Review',
                            'projected' => $projected,
                            'preview_rows' => $previewRows,
                            'show_balance_snapshot' => true,
                            'attachments' => $leaveAttachmentMap[(int)$r['id']] ?? [],
                        ]);
                        ?>
                        <tr class="personnel-row">
                            <td class="col-employee">
                                <div class="personnel-employee-cell">
                                    <strong><?= safe_h($employeeName); ?></strong>
                                    <div class="subtext"><?= safe_h($r['email']); ?></div>
                                    <div class="subtext"><?= safe_h($r['department'] ?? ''); ?><?= !empty($r['position']) ? ' • '.safe_h($r['position']) : ''; ?></div>
                                </div>
                            </td>

                            <td class="col-type">
                                <span class="leave-type-pill"><?= safe_h($r['leave_type_name']); ?></span>
                            </td>

                            <td class="col-dates">
                                <div class="date-stack">
                                    <span><?= safe_h(app_format_date($r['start_date'] ?? '')); ?></span>
                                    <span class="date-arrow">to</span>
                                    <span><?= safe_h(app_format_date($r['end_date'] ?? '')); ?></span>
                                </div>
                            </td>

                            <td class="col-days">
                                <strong><?= trunc3($r['total_days']); ?></strong>
                            </td>

                            <td class="col-balance">
                                <div class="balance-stack compact">
                                    <span class="balance-chip">Vac: <strong><?= trunc3($projected['annual_before']); ?></strong></span>
                                    <span class="balance-chip">Sick: <strong><?= trunc3($projected['sick_before']); ?></strong></span>
                                    <span class="balance-chip">Force: <strong><?= trunc3($projected['force_before']); ?></strong></span>
                                </div>
                            </td>

                            <td class="col-balance">
                                <div class="balance-stack compact">
                                    <span class="balance-chip <?= in_array($projected['bucket'], ['annual', 'annual_force'], true) ? 'chip-affected' : ''; ?>">Vac: <strong><?= trunc3($projected['annual_after']); ?></strong></span>
                                    <span class="balance-chip <?= $projected['bucket'] === 'sick' ? 'chip-affected' : ''; ?>">Sick: <strong><?= trunc3($projected['sick_after']); ?></strong></span>
                                    <span class="balance-chip <?= in_array($projected['bucket'], ['force', 'annual_force', 'force_only'], true) ? 'chip-affected' : ''; ?>">Force: <strong><?= trunc3($projected['force_after']); ?></strong></span>
                                </div>
                            </td>

                            <td class="col-action">
                                <div class="personnel-action-bar">
                                    <button type="button"
                                            class="icon-action-btn labelled"
                                            onclick="openModal('<?= $modalId; ?>')"
                                            title="Review details">
                                        <span class="action-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 72 72" fill="currentColor" style="flex-shrink:0;"><path d="M 31 11 C 19.973 11 11 19.973 11 31 C 11 42.027 19.973 51 31 51 C 34.974166 51 38.672385 49.821569 41.789062 47.814453 L 54.726562 60.751953 C 56.390563 62.415953 59.088953 62.415953 60.751953 60.751953 C 62.415953 59.087953 62.415953 56.390563 60.751953 54.726562 L 47.814453 41.789062 C 49.821569 38.672385 51 34.974166 51 31 C 51 19.973 42.027 11 31 11 z M 31 19 C 37.616 19 43 24.384 43 31 C 43 37.616 37.616 43 31 43 C 24.384 43 19 37.616 19 31 C 19 24.384 24.384 19 31 19 z"/></svg></span>
                                        <span class="action-label">View</span>
                                    </button>

                                    <a href="reports.php?type=leave_card&employee_id=<?= (int)$r['employee_id']; ?>"
                                       target="_blank"
                                       class="icon-action-btn labelled"
                                       title="Open full leave card">
                                        <span class="action-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 52 52" fill="currentColor" style="flex-shrink:0;"><path d="m36.4 14.8h8.48a1.09 1.09 0 0 0 1.12-1.12 1 1 0 0 0 -.32-.8l-10.56-10.56a1 1 0 0 0 -.8-.32 1.09 1.09 0 0 0 -1.12 1.12v8.48a3.21 3.21 0 0 0 3.2 3.2z"/><path d="m44.4 19.6h-11.2a4.81 4.81 0 0 1 -4.8-4.8v-11.2a1.6 1.6 0 0 0 -1.6-1.6h-16a4.81 4.81 0 0 0 -4.8 4.8v38.4a4.81 4.81 0 0 0 4.8 4.8h30.4a4.81 4.81 0 0 0 4.8-4.8v-24a1.6 1.6 0 0 0 -1.6-1.6zm-32-1.6a1.62 1.62 0 0 1 1.6-1.55h6.55a1.56 1.56 0 0 1 1.57 1.55v1.59a1.63 1.63 0 0 1 -1.59 1.58h-6.53a1.55 1.55 0 0 1 -1.58-1.58zm24 20.77a1.6 1.6 0 0 1 -1.6 1.6h-20.8a1.6 1.6 0 0 1 -1.6-1.6v-1.57a1.6 1.6 0 0 1 1.6-1.6h20.8a1.6 1.6 0 0 1 1.6 1.6zm3.2-9.6a1.6 1.6 0 0 1 -1.6 1.63h-24a1.6 1.6 0 0 1 -1.6-1.6v-1.6a1.6 1.6 0 0 1 1.6-1.6h24a1.6 1.6 0 0 1 1.6 1.6z"/></svg></span>
                                        <span class="action-label">Leave Card</span>
                                    </a>

                                    <button type="button"
                                            class="icon-action-btn labelled icon-approve"
                                            onclick="openModal('personnelActionModal_<?= (int)$r['id']; ?>')"
                                            title="Approve or return request">
                                        <span class="action-icon">⚙</span>
                                        <span class="action-label">Action</span>
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <div id="personnelActionModal_<?= (int)$r['id']; ?>" class="modal">
                            <div class="modal-content floating-action-modal small-action-modal">
                                <button type="button" class="modal-close" onclick="closeModal('personnelActionModal_<?= (int)$r['id']; ?>')">&times;</button>
                                <h3 style="margin-bottom:14px;">Personnel Action</h3>
                                <p class="review-muted" style="margin-bottom:14px;"><?= safe_h($employeeName); ?> • <?= safe_h($r['leave_type_name']); ?></p>

                                <form method="POST" action="../controllers/LeaveController.php" class="mini-action-form">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="leave_id" value="<?= (int)$r['id']; ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <?php if (isLateSickForPreview($r)): ?>
                                        <select name="approval_pay_status" required>
                                            <option value="without_pay">Approve as Without Pay</option>
                                            <option value="with_pay">Approve as With Pay</option>
                                        </select>
                                    <?php endif; ?>
                                    <input type="text" name="comments" placeholder="Optional note">
                                    <button type="submit">Final Approve</button>
                                </form>

                                <form method="POST" action="../controllers/LeaveController.php" class="mini-action-form">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="leave_id" value="<?= (int)$r['id']; ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <input type="text" name="comments" placeholder="Reason" required>
                                    <button type="submit" class="danger-btn">Return / Reject</button>
                                </form>
                            </div>
                        </div>

                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?= pagination_render($pendingPersonnelPagination, 'pp_page', ['tab' => $tab]); ?>

            <?= $personnelModalHtml; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<div id="section-approved" style="<?= ($tab === 'all' || $tab === 'approved') ? '' : 'display:none;'; ?>">
    <div class="ui-card mb-6 ajax-fragment" data-fragment-id="leave-finalized" data-page-param="fin_page" data-search-param="fin_q">
        <h3>Finalized / Approved</h3>
        <div class="fragment-toolbar">
            <div class="search-input">
                <input class="form-control live-search-input" type="text" name="fin_q" value="<?= htmlspecialchars($finSearch, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search approved requests...">
            </div>
            <div class="fragment-summary">Showing <?= $finalizedPagination['from']; ?>–<?= $finalizedPagination['to']; ?> of <?= $finalizedPagination['total']; ?> requests</div>
        </div>

        <?php if (empty($finalized)): ?>
            <p>No finalized requests.</p>
        <?php else: ?>
            <div class="table-container">
                <table width="100%">
                    <tr>
                        <th>Employee</th>
                        <th>Email</th>
                        <th>Type</th>
                        <th>Dates</th>
                        <th>Days</th>
                        <th>Workflow</th>
                        <th>Print Status</th>
                        <th>Details</th>
                        <?php if (in_array($role, ['personnel','hr','admin'], true)): ?>
                            <th>Action</th>
                        <?php endif; ?>
                    </tr>

                    <?php foreach ($finalized as $r): ?>
                        <tr>
                            <td><?= safe_h(trim($r['first_name'].' '.($r['middle_name'] ?? '').' '.$r['last_name'])); ?></td>
                            <td><?= safe_h($r['email']); ?></td>
                            <td><?= safe_h($r['leave_type_name']); ?></td>
                            <td><?= safe_h(app_format_date_range($r['start_date'] ?? '', $r['end_date'] ?? '')); ?></td>
                            <td><?= trunc3($r['total_days']); ?></td>
                            <td><?= safe_h($r['workflow_status'] ?? 'finalized'); ?></td>
                            <td><?= safe_h($r['print_status'] ?? '—'); ?></td>
                            <td>
                                <button type="button" class="icon-action-btn labelled" onclick="openModal('finalDetailModal_<?= (int)$r['id']; ?>')" title="View full request details">
                                    <span class="action-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 72 72" fill="currentColor" style="flex-shrink:0;"><path d="M 31 11 C 19.973 11 11 19.973 11 31 C 11 42.027 19.973 51 31 51 C 34.974166 51 38.672385 49.821569 41.789062 47.814453 L 54.726562 60.751953 C 56.390563 62.415953 59.088953 62.415953 60.751953 60.751953 C 62.415953 59.087953 62.415953 56.390563 60.751953 54.726562 L 47.814453 41.789062 C 49.821569 38.672385 51 34.974166 51 31 C 51 19.973 42.027 11 31 11 z M 31 19 C 37.616 19 43 24.384 43 31 C 43 37.616 37.616 43 31 43 C 24.384 43 19 37.616 19 31 C 19 24.384 24.384 19 31 19 z"/></svg></span>
                                    <span class="action-label">Details</span>
                                </button>
                            </td>

                            <?php if (in_array($role, ['personnel','hr','admin'], true)): ?>
                                <td>
                                    <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                                        <button class="icon-action-btn labelled"
                                                onclick="openModal('printModal_<?= (int)$r['id']; ?>')"
                                                title="Customize signatories and print">
                                            <span class="action-icon">🖨</span>
                                            <span class="action-label">Print</span>
                                        </button>

                                        <?php if (($r['print_status'] ?? '') !== 'printed'): ?>
                                            <form method="POST" action="../controllers/LeaveController.php" style="margin:0;">
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                                                <input type="hidden" name="leave_id" value="<?= (int)$r['id']; ?>">
                                                <input type="hidden" name="action" value="mark_printed">
                                                <button type="submit" class="btn-success" title="Mark this request as printed">Mark as Printed</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="status-badge approved">Printed</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
            <?= pagination_render($finalizedPagination, 'fin_page', ['tab' => $tab]); ?>

            <?php foreach ($finalized as $r): ?>
                <?= renderLeaveRequestDetailModal($r, 'finalDetailModal_' . (int)$r['id'], ['badge_text' => 'Finalized / Approved Request', 'attachments' => $leaveAttachmentMap[(int)$r['id']] ?? []]); ?>
            <?php endforeach; ?>

            <?php foreach ($finalized as $r): ?>
                <div id="printModal_<?= (int)$r['id']; ?>" class="modal">
                    <div class="modal-content review-modal" style="max-width:520px;">
                        <button type="button"
                                class="modal-close"
                                onclick="closeModal('printModal_<?= (int)$r['id']; ?>')">&times;</button>

                        <h3 style="margin-bottom:10px;">Customize Signatories</h3>

                        <p class="review-muted" style="margin-bottom:18px;">
                            <?= safe_h(trim($r['first_name'].' '.($r['middle_name'] ?? '').' '.$r['last_name'])); ?>
                            • <?= safe_h($r['leave_type_name']); ?>
                        </p>

                        <form method="POST"
                              action="../controllers/save_signatories.php"
                              target="_blank">

                            <input type="hidden" name="leave_id" value="<?= (int)$r['id']; ?>">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">

                            <div class="review-panel">
                                <h4>7.A Certification of Leave Credits</h4>

                                <label>Name</label>
                                <input type="text"
                                       name="name_a"
                                       value="<?= safe_h($signatories['certification']['name'] ?? ''); ?>"
                                       required>

                                <label>Position</label>
                                <input type="text"
                                       name="position_a"
                                       value="<?= safe_h($signatories['certification']['position'] ?? ''); ?>"
                                       required>
                            </div>

                            <div class="review-panel" style="margin-top:16px;">
                                <h4>7.C Final Approver</h4>

                                <label>Name</label>
                                <input type="text"
                                       name="name_c"
                                       value="<?= safe_h($signatories['final_approver']['name'] ?? ''); ?>"
                                       required>

                                <label>Position</label>
                                <input type="text"
                                       name="position_c"
                                       value="<?= safe_h($signatories['final_approver']['position'] ?? ''); ?>"
                                       required>
                            </div>

                            <div class="review-modal-actions" style="margin-top:20px;">
                                <button type="submit">Save & Print</button>

                                <button type="button"
                                        class="btn-secondary"
                                        onclick="closeModal('printModal_<?= (int)$r['id']; ?>')">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div id="section-rejected" style="<?= ($tab === 'all' || $tab === 'rejected') ? '' : 'display:none;'; ?>">
    <div class="ui-card ajax-fragment" data-fragment-id="leave-rejected" data-page-param="rej_page" data-search-param="rej_q">
        <h3>Rejected / Returned</h3>
        <div class="fragment-toolbar">
            <div class="search-input">
                <input class="form-control live-search-input" type="text" name="rej_q" value="<?= htmlspecialchars($rejSearch, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search rejected or returned requests...">
            </div>
            <div class="fragment-summary">Showing <?= $returnedPagination['from']; ?>–<?= $returnedPagination['to']; ?> of <?= $returnedPagination['total']; ?> requests</div>
        </div>
        <?php if (empty($returnedOrRejected)): ?>
            <p>No rejected or returned requests.</p>
        <?php else: ?>
            <div class="table-container">
                <table width="100%">
                    <tr>
                        <th>Employee</th>
                        <th>Email</th>
                        <th>Type</th>
                        <th>Dates</th>
                        <th>Status</th>
                        <th>Workflow</th>
                        <th>Comments</th>
                        <th>Details</th>
                    </tr>
                    <?php foreach ($returnedOrRejected as $r): ?>
                        <tr>
                            <td><?= safe_h(trim($r['first_name'].' '.($r['middle_name'] ?? '').' '.$r['last_name'])); ?></td>
                            <td><?= safe_h($r['email']); ?></td>
                            <td><?= safe_h($r['leave_type_name']); ?></td>
                            <td><?= safe_h(app_format_date_range($r['start_date'] ?? '', $r['end_date'] ?? '')); ?></td>
                            <td><?= safe_h($r['status']); ?></td>
                            <td><?= safe_h($r['workflow_status'] ?? '—'); ?></td>
                            <td><?= safe_h($r['personnel_comments'] ?? $r['department_head_comments'] ?? $r['manager_comments'] ?? ''); ?></td>
                            <td>
                                <button type="button" class="icon-action-btn labelled" onclick="openModal('rejectDetailModal_<?= (int)$r['id']; ?>')" title="View full request details">
                                    <span class="action-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 72 72" fill="currentColor" style="flex-shrink:0;"><path d="M 31 11 C 19.973 11 11 19.973 11 31 C 11 42.027 19.973 51 31 51 C 34.974166 51 38.672385 49.821569 41.789062 47.814453 L 54.726562 60.751953 C 56.390563 62.415953 59.088953 62.415953 60.751953 60.751953 C 62.415953 59.087953 62.415953 56.390563 60.751953 54.726562 L 47.814453 41.789062 C 49.821569 38.672385 51 34.974166 51 31 C 51 19.973 42.027 11 31 11 z M 31 19 C 37.616 19 43 24.384 43 31 C 43 37.616 37.616 43 31 43 C 24.384 43 19 37.616 19 31 C 19 24.384 24.384 19 31 19 z"/></svg></span>
                                    <span class="action-label">Details</span>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
            <?= pagination_render($returnedPagination, 'rej_page', ['tab' => $tab]); ?>
            <?php foreach ($returnedOrRejected as $r): ?>
                <?= renderLeaveRequestDetailModal($r, 'rejectDetailModal_' . (int)$r['id'], ['badge_text' => 'Rejected / Returned Request', 'attachments' => $leaveAttachmentMap[(int)$r['id']] ?? []]); ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php if ($showArchivedSection): ?>
<div id="section-archived" style="<?= ($tab === 'all' || $tab === 'archived') ? '' : 'display:none;'; ?>">
    <div id="archiveCard" class="ui-card ajax-fragment" data-fragment-id="leave-archived" data-page-param="arch_page" data-search-param="arch_q">
        <h3>Archived Requests (<?= (int)$archivedPagination['total']; ?>)</h3>
        <div class="fragment-toolbar">
            <div class="search-input">
                <input class="form-control live-search-input" type="text" name="arch_q" value="<?= htmlspecialchars($archSearch, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search archived requests...">
            </div>
            <div class="fragment-summary">Showing <?= $archivedPagination['from']; ?>–<?= $archivedPagination['to']; ?> of <?= $archivedPagination['total']; ?> requests</div>
        </div>

        <?php if (empty($archived)): ?>
            <p>No archived requests found.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="ui-table">
                    <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Type</th>
                        <th>Dates</th>
                        <th>Days</th>
                        <th>Status</th>
                        <th>Reason</th>
                        <th>Details</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach($archived as $r): ?>
                        <tr>
                            <td><?= safe_h(trim($r['first_name'].' '.($r['middle_name'] ?? '').' '.$r['last_name'])); ?></td>
                            <td><?= safe_h($r['leave_type_name'] ?? $r['leave_type']); ?></td>
                            <td><?= safe_h(app_format_date_range($r['start_date'] ?? '', $r['end_date'] ?? '')); ?></td>
                            <td><?= trunc3($r['total_days']); ?></td>
                            <td><?= safe_h(ucfirst($r['status'])); ?></td>
                            <td><?= safe_h($r['manager_comments'] ?? $r['reason'] ?? ''); ?></td>
                            <td>
                                <button type="button" class="icon-action-btn labelled" onclick="openModal('archDetailModal_<?= (int)$r['id']; ?>')" title="View full request details">
                                    <span class="action-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 72 72" fill="currentColor" style="flex-shrink:0;"><path d="M 31 11 C 19.973 11 11 19.973 11 31 C 11 42.027 19.973 51 31 51 C 34.974166 51 38.672385 49.821569 41.789062 47.814453 L 54.726562 60.751953 C 56.390563 62.415953 59.088953 62.415953 60.751953 60.751953 C 62.415953 59.087953 62.415953 56.390563 60.751953 54.726562 L 47.814453 41.789062 C 49.821569 38.672385 51 34.974166 51 31 C 51 19.973 42.027 11 31 11 z M 31 19 C 37.616 19 43 24.384 43 31 C 43 37.616 37.616 43 31 43 C 24.384 43 19 37.616 19 31 C 19 24.384 24.384 19 31 19 z"/></svg></span>
                                    <span class="action-label">Details</span>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?= pagination_render($archivedPagination, 'arch_page', ['tab' => $tab]); ?>
            <?php foreach ($archived as $r): ?>
                <?= renderLeaveRequestDetailModal($r, 'archDetailModal_' . (int)$r['id'], ['badge_text' => 'Archived Request', 'show_balance_snapshot' => false, 'attachments' => $leaveAttachmentMap[(int)$r['id']] ?? []]); ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div id="attachmentPreviewModal" class="modal attachment-preview-modal">
    <div class="modal-content review-modal attachment-preview-modal-content">
        <button type="button" class="modal-close" onclick="closeAttachmentPreview()">&times;</button>
        <div class="review-modal-header">
            <div>
                <span class="review-modal-badge">Attachment Preview</span>
                <h3 id="attachmentPreviewTitle">Preview</h3>
                <p class="review-muted">Quick preview for uploaded request attachments.</p>
            </div>
        </div>
        <div id="attachmentPreviewBody" class="attachment-preview-body"></div>
        <div class="review-modal-actions">
            <button type="button" class="btn-secondary" onclick="closeAttachmentPreview()">Close</button>
            <a id="attachmentPreviewOpenLink" class="btn-export" href="#" target="_blank" rel="noopener">Open in New Tab</a>
        </div>
    </div>
</div>

<script>
function openModal(id) {
    var el = document.getElementById(id);
    if (el) el.classList.add('open');
}

function closeModal(id) {
    var el = document.getElementById(id);
    if (el) el.classList.remove('open');
}

function openAttachmentPreview(url, name, mimeType) {
    var modal = document.getElementById('attachmentPreviewModal');
    var title = document.getElementById('attachmentPreviewTitle');
    var body = document.getElementById('attachmentPreviewBody');
    var openLink = document.getElementById('attachmentPreviewOpenLink');
    if (!modal || !title || !body || !openLink) return;

    title.textContent = name || 'Attachment Preview';
    openLink.href = url || '#';
    body.innerHTML = '';

    if (mimeType === 'application/pdf') {
        var frame = document.createElement('iframe');
        frame.src = url;
        frame.className = 'attachment-preview-frame';
        frame.setAttribute('title', name || 'Attachment Preview');
        body.appendChild(frame);
    } else if (mimeType && mimeType.indexOf('image/') === 0) {
        var img = document.createElement('img');
        img.src = url;
        img.alt = name || 'Attachment Preview';
        img.className = 'attachment-preview-image';
        body.appendChild(img);
    } else {
        var fallback = document.createElement('p');
        fallback.className = 'review-muted';
        fallback.textContent = 'Preview is not available for this file type. Use “Open in New Tab” instead.';
        body.appendChild(fallback);
    }

    modal.classList.add('open');
}

function closeAttachmentPreview() {
    var modal = document.getElementById('attachmentPreviewModal');
    var body = document.getElementById('attachmentPreviewBody');
    if (modal) modal.classList.remove('open');
    if (body) body.innerHTML = '';
}

document.querySelectorAll('.modal').forEach(function(modal) {
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.classList.remove('open');
        }
    });
});

var activeTab = '<?= $tab; ?>';

function setActiveTab(tab) {
    var tabs = document.querySelectorAll('.filter-tab');
    tabs.forEach(function(tabEl) {
        tabEl.classList.toggle('is-active', tabEl.getAttribute('data-tab') === tab);
    });

    var sections = {
        all: ['section-pending', 'section-approved', 'section-rejected', 'section-archived'],
        pending: ['section-pending'],
        approved: ['section-approved'],
        rejected: ['section-rejected'],
        archived: ['section-archived'],
    };

    var visible = sections[tab] || sections.all;

    ['section-pending', 'section-approved', 'section-rejected', 'section-archived'].forEach(function(id) {
        var el = document.getElementById(id);
        if (!el) return;
        el.style.display = visible.includes(id) ? '' : 'none';
    });

    activeTab = tab;

    if (window.history && window.history.replaceState) {
        var url = new URL(window.location.href);
        url.searchParams.set('tab', tab);
        window.history.replaceState({}, '', url.toString());
    }
}

document.querySelectorAll('.filter-tab').forEach(function(tab) {
    tab.addEventListener('click', function(e) {
        e.preventDefault();
        var selected = this.getAttribute('data-tab');
        if (!selected) return;
        setActiveTab(selected);
    });
});

// initialize (in case server-side default differs)
setActiveTab(activeTab);

(function(){
    var requestId = <?= (int)$autoOpenDetailId; ?>;
    if (!requestId) return;
    var activeTab = <?= json_encode($tab); ?>;
    var modalId = null;
    if (activeTab === 'approved') {
        modalId = 'finalDetailModal_' + requestId;
    } else if (activeTab === 'rejected') {
        modalId = 'rejectDetailModal_' + requestId;
    } else if (activeTab === 'archived') {
        modalId = 'archDetailModal_' + requestId;
    } else {
        if (document.getElementById('reviewModal_' + requestId)) {
            modalId = 'reviewModal_' + requestId;
        } else if (document.getElementById('deptDetailModal_' + requestId)) {
            modalId = 'deptDetailModal_' + requestId;
        }
    }
    if (!modalId) return;
    window.setTimeout(function(){
        if (typeof openModal === 'function') {
            openModal(modalId);
        }
    }, 180);
})();
</script>


<script>
(function(){
  var form = document.getElementById('leaveRequestFilterForm');
  if (!form) return;
  var controls = form.querySelectorAll('.auto-submit-filter');
  var submitTimer = null;
  function autoSubmit(){
    if (submitTimer) window.clearTimeout(submitTimer);
    submitTimer = window.setTimeout(function(){
      form.submit();
    }, 80);
  }
  controls.forEach(function(control){
    control.addEventListener('change', autoSubmit);
  });
})();
</script>
</body>
</html>
