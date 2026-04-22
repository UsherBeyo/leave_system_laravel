<?php
if (session_status() === PHP_SESSION_NONE) {
    if (session_status() === PHP_SESSION_NONE) session_start();
}
require_once '../config/database.php';
require_once '../helpers/Auth.php';
require_once '../helpers/Flash.php';
Auth::requireLogin('login.php');
require_once '../helpers/DateHelper.php';
require_once '../helpers/StyledXlsxExport.php';

$db = (new Database())->connect();

function normalizeLeaveTypeKey(string $name): string {
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

function isSickLeaveType(string $name): bool {
    return normalizeLeaveTypeKey($name) === 'sick leave';
}

function isForceLeaveType(string $name): bool {
    return normalizeLeaveTypeKey($name) === 'mandatory/forced leave';
}

function isAccrualLeaveType(string $name): bool {
    return strpos(strtolower(trim($name)), 'accrual') !== false;
}

function parseBudgetHistoryMeta(?string $notes): array {
    $meta = [];
    $notes = (string)$notes;

    if (preg_match_all('/([A-Z_]+)=([0-9.]+)/', $notes, $m, PREG_SET_ORDER)) {
        foreach ($m as $pair) {
            $meta[$pair[1]] = $pair[2];
        }
    }

    return $meta;
}

function safeExportFilename(string $name, string $fallback = 'Leave Card'): string {
    $name = preg_replace('/[\\\\\/:*?"<>|]+/', '', $name);
    $name = preg_replace('/\s+/', ' ', trim((string)$name));
    return $name !== '' ? $name : $fallback;
}

function trunc3($v): string {
    if ($v === null || $v === '') return '';
    $n = (float)$v;
    $t = floor($n * 1000) / 1000;         // TRUNCATE (not round)
    return number_format($t, 3, '.', ''); // always 3 decimals
}

$id = isset($_GET['id']) ? intval($_GET['id']) : ($_SESSION['emp_id'] ?? 0);
if (!$id) { flash_redirect('dashboard.php', 'error', 'Employee not specified.'); }

$stmt = $db->prepare("SELECT e.*, u.email FROM employees e JOIN users u ON e.user_id = u.id WHERE e.id = ?");
$stmt->execute([$id]);
$e = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$e) { flash_redirect('dashboard.php', 'error', 'Employee not found.'); }

// permission: admin/hr/manager or the employee themselves
$role = $_SESSION['role'] ?? '';
$isSelfProfile = ((int)($_SESSION['emp_id'] ?? 0) === $id);
if (!in_array($role, ['admin','manager','hr'], true) && !$isSelfProfile) {
    flash_redirect('dashboard.php', 'error', 'Access Denied');
}

$profilePreviewSrc = !empty($e['profile_pic'])
    ? (string)$e['profile_pic']
    : 'data:image/svg+xml;utf8,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="480" height="480" viewBox="0 0 480 480"><rect width="480" height="480" rx="36" fill="#eff6ff"/><circle cx="240" cy="180" r="88" fill="#93c5fd"/><path d="M92 402c22-79 87-124 148-124s126 45 148 124" fill="#60a5fa"/></svg>');

// detect trans_date column if budget_history exists (needed for budget rows export)
$hasTransDate = false;
try {
    $db->query("SELECT trans_date FROM budget_history LIMIT 1");
    $hasTransDate = true;
} catch (Throwable $t) {
    $hasTransDate = false;
}

// export leave card - merged leave history & budget history with accurate balances
// export leave card - complete transaction history (leave_requests + budget_history)
if (isset($_GET['export']) && $_GET['export'] === 'leave_card' && (
        $_SESSION['role'] === 'admin' ||
        $_SESSION['role'] === 'hr' ||
        $_SESSION['role'] === 'manager' ||
        ($_SESSION['emp_id'] ?? 0) == $id
    )) {

    $empId = $id;
    $rows = [];

    /**
     * ONLY Leave Requests (no budget_history merging)
     */
    $leaveStmt = $db->prepare(
        "SELECT 
            lr.start_date,
            lr.created_at,
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
         ORDER BY lr.start_date ASC, lr.created_at ASC"
    );
    $leaveStmt->execute([$empId]);

    foreach ($leaveStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $leaveType = trim((string)$r['leave_type']);
        $statusRaw = strtolower(trim((string)$r['status']));

                $isAccrual = isAccrualLeaveType($leaveType);

        // Skip undertime rows stored in leave_requests; undertime is represented from budget_history
        if (strtolower(trim($leaveType)) === 'undertime') {
            continue;
        }

        $isSick = isSickLeaveType($leaveType);
        $isForce = isForceLeaveType($leaveType);
        $days = floatval($r['total_days']);

        $vacDed = 0.0; $sickDed = 0.0;
        $vacEarn = 0.0; $sickEarn = 0.0;

        if ($isAccrual) {
            if ($isSick) {
                $sickEarn = $days;
            } else {
                $vacEarn = $days;
            }
            $statusRaw = 'earning';
        } else {
            if ($statusRaw === 'approved') {
                if ($r['cert_vacation_less_this_application'] !== null || $r['cert_sick_less_this_application'] !== null) {
                    $vacDed = floatval($r['cert_vacation_less_this_application'] ?? 0);
                    $sickDed = floatval($r['cert_sick_less_this_application'] ?? 0);
                } elseif ($isSick) {
                    $sickDed = $days;
                } else {
                    $vacDed = $days;
                }
            }
        }

        $vacBal = ($r['snapshot_annual_balance'] !== null && $r['snapshot_annual_balance'] !== '')
            ? floatval($r['snapshot_annual_balance']) : '';
        $sickBal = ($r['snapshot_sick_balance'] !== null && $r['snapshot_sick_balance'] !== '')
            ? floatval($r['snapshot_sick_balance']) : '';

        $particulars = $leaveType;
        if (!$isAccrual && stripos($particulars, 'leave') === false) {
            $particulars .= ' Leave';
        }

        $rows[] = [
            'date' => $r['start_date'] ?: substr((string)$r['created_at'], 0, 10),
            'particulars' => $particulars,
            'vac_earned' => $vacEarn,
            'vac_deducted' => $vacDed,
            'vac_balance' => $vacBal,
            'sick_earned' => $sickEarn,
            'sick_deducted' => $sickDed,
            'sick_balance' => $sickBal,
            'status' => ucfirst($statusRaw)
        ];
    }

    // 2) Budget history rows (earnings, adjustments, undertime, deductions)
    if ($hasTransDate) {
        $budgetStmt = $db->prepare(
            "SELECT id, created_at, trans_date, leave_type, action, old_balance, new_balance, notes
             FROM budget_history
                WHERE employee_id = ?
                AND (leave_request_id IS NULL OR leave_request_id = 0)
             ORDER BY COALESCE(trans_date, DATE(created_at)) ASC, created_at ASC, id ASC"
        );
    } else {
        $budgetStmt = $db->prepare(
            "SELECT id, created_at, leave_type, action, old_balance, new_balance, notes
             FROM budget_history
                WHERE employee_id = ?
                AND (leave_request_id IS NULL OR leave_request_id = 0)
             ORDER BY created_at ASC, id ASC"
        );
    }
    $budgetStmt->execute([$empId]);

        foreach ($budgetStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $leaveType = trim((string)$r['leave_type']);
        $action = strtolower(trim((string)$r['action']));
        $notes = (string)($r['notes'] ?? '');
        $meta = parseBudgetHistoryMeta($notes);

        $vacDed = 0.0; $sickDed = 0.0;
        $vacEarn = 0.0; $sickEarn = 0.0;

        $dateCol = '';
        if ($hasTransDate && !empty($r['trans_date'])) $dateCol = (string)$r['trans_date'];
        else $dateCol = substr((string)$r['created_at'], 0, 10);

        $vacBal = '';
        $sickBal = '';

        if ($action === 'undertime_paid' || $action === 'undertime_unpaid') {
            $deltaDed = max(0, floatval($r['old_balance']) - floatval($r['new_balance']));

            $vacDed = isset($meta['UT_DEDUCT']) ? (float)$meta['UT_DEDUCT'] : $deltaDed;

            if (isset($meta['VAC_NEW'])) {
                $vacBal = (float)$meta['VAC_NEW'];
            } elseif (isset($meta['VAC'])) {
                $vacBal = (float)$meta['VAC'];
            } elseif ($r['new_balance'] !== null && $r['new_balance'] !== '') {
                $vacBal = floatval($r['new_balance']);
            }

            if (isset($meta['SICK'])) {
                $sickBal = (float)$meta['SICK'];
            }

            $particulars = 'Undertime ' . ($action === 'undertime_paid' ? '(With pay)' : '(Without pay)');
        } else {
            $deltaEarn = max(0, floatval($r['new_balance']) - floatval($r['old_balance']));
            $deltaDed  = max(0, floatval($r['old_balance']) - floatval($r['new_balance']));

            $isSick = isSickLeaveType($leaveType);
            $isForce = isForceLeaveType($leaveType);

            if (in_array($action, ['accrual', 'earning'], true)) {
                if ($isSick) {
                    $sickEarn = $deltaEarn;
                    $sickBal = floatval($r['new_balance']);
                } elseif (!$isForce) {
                    $vacEarn = $deltaEarn;
                    $vacBal = floatval($r['new_balance']);
                }
            } else {
                if ($isSick) {
                    $sickEarn = $deltaEarn;
                    $sickDed  = $deltaDed;
                    $sickBal  = floatval($r['new_balance']);
                } elseif (!$isForce) {
                    $vacEarn = $deltaEarn;
                    $vacDed  = $deltaDed;
                    $vacBal  = floatval($r['new_balance']);
                }
            }

            $particulars = ucfirst($action) . ' ' . $leaveType;
            if ($isForce) {
                $particulars .= ' (Force balance entry)';
            }
        }

        $rows[] = [
            'date' => $dateCol,
            'particulars' => $particulars,
            'vac_earned' => $vacEarn,
            'vac_deducted' => $vacDed,
            'vac_balance' => $vacBal,
            'sick_earned' => $sickEarn,
            'sick_deducted' => $sickDed,
            'sick_balance' => $sickBal,
            'status' => ucfirst($action)
        ];
    }
    // ✅ Ensure chronological order by date (and stable tie-breaker)
    usort($rows, function($a, $b) {
        $da = strtotime($a['date'] ?? '') ?: 0;
        $dbb = strtotime($b['date'] ?? '') ?: 0;
        if ($da !== $dbb) return $da <=> $dbb;
        return strcmp((string)($a['particulars'] ?? ''), (string)($b['particulars'] ?? ''));
    });
    $employeeFullName = trim(($e['first_name'] ?? '') . ' ' . ($e['last_name'] ?? ''));
    if ($employeeFullName === '') {
        $employeeFullName = 'Employee ' . $id;
    }

    $leaveCardFilename = safeExportFilename('Leave Card - ' . $employeeFullName);

    $employeeInfoRows = [
        [
            ['ref' => 'A', 'value' => 'Employee ID', 'role' => 'label'],
            ['ref' => 'B', 'value' => (string)$e['id']],
            ['ref' => 'C', 'value' => 'Name', 'role' => 'label'],
            ['ref' => 'D', 'value' => trim(($e['first_name'].' '.$e['last_name']) ?: ($e['name'] ?? ''))],
            ['ref' => 'E', 'value' => 'Position', 'role' => 'label'],
            ['ref' => 'F', 'value' => (string)($e['position'] ?? '')],
            ['ref' => 'G', 'value' => 'Department', 'role' => 'label'],
            ['ref' => 'H', 'value' => (string)($e['department'] ?? '')],
            ['ref' => 'I', 'value' => ''],
        ],
        [
            ['ref' => 'A', 'value' => 'Status', 'role' => 'label'],
            ['ref' => 'B', 'value' => (string)($e['status'] ?? '')],
            ['ref' => 'C', 'value' => 'Civil Status', 'role' => 'label'],
            ['ref' => 'D', 'value' => (string)($e['civil_status'] ?? '')],
            ['ref' => 'E', 'value' => 'Entrance to Duty', 'role' => 'label'],
            ['ref' => 'F', 'value' => (string)($e['entrance_to_duty'] ?? '0000-00-00')],
            ['ref' => 'G', 'value' => 'Unit', 'role' => 'label'],
            ['ref' => 'H', 'value' => (string)($e['unit'] ?? '')],
            ['ref' => 'I', 'value' => ''],
        ],
    ];

    $tableRows = [];
    foreach ($rows as $row) {
        $tableRows[] = [
            app_format_date($row['date'] ?? ''),
            (string)($row['particulars'] ?? ''),
            $row['vac_earned'] != 0 ? trunc3($row['vac_earned']) : '',
            $row['vac_deducted'] != 0 ? trunc3($row['vac_deducted']) : '',
            $row['vac_balance'] === '' ? '' : trunc3($row['vac_balance']),
            $row['sick_earned'] != 0 ? trunc3($row['sick_earned']) : '',
            $row['sick_deducted'] != 0 ? trunc3($row['sick_deducted']) : '',
            $row['sick_balance'] === '' ? '' : trunc3($row['sick_balance']),
            (string)($row['status'] ?? ''),
        ];
    }

    StyledXlsxExport::outputWorkbook([
        'filename' => $leaveCardFilename,
        'sheet_title' => $leaveCardFilename,
        'employee_info_rows' => $employeeInfoRows,
        'table_title' => 'LEAVE CARD TRANSACTIONS',
        'table_headers' => ['Date', 'Particulars', 'Vac Earned', 'Vac Deducted', 'Vac Balance', 'Sick Earned', 'Sick Deducted', 'Sick Balance', 'Status'],
        'table_rows' => $tableRows,
        'column_widths' => [18, 32, 11, 13, 16, 12, 14, 12, 15],
    ]);
}

// export leave history CSV
if (isset($_GET['export']) && ($_SESSION['role'] === 'admin' || $_SESSION['role']==='hr' || ($_SESSION['emp_id'] ?? 0) == $id)) {
    $stmt = $db->prepare("SELECT COALESCE(lt.name, lr.leave_type) AS leave_type_name, lr.start_date, lr.end_date, lr.total_days, lr.status, lr.created_at as 'submitted_date', lr.reason, lr.snapshot_annual_balance, lr.snapshot_sick_balance, lr.snapshot_force_balance FROM leave_requests lr LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id WHERE lr.employee_id = ? ORDER BY lr.start_date");
    $stmt->execute([$id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $employeeFullName = trim(($e['first_name'] ?? '') . ' ' . ($e['last_name'] ?? ''));
    if ($employeeFullName === '') {
        $employeeFullName = 'Employee ' . $id;
    }
    $leaveHistoryFilename = safeExportFilename('Leave History - ' . $employeeFullName, 'Leave History - Employee');

    $employeeInfoRows = [
        [
            ['ref' => 'A', 'value' => 'Employee ID', 'role' => 'label'],
            ['ref' => 'B', 'value' => (string)$e['id']],
            ['ref' => 'C', 'value' => 'Name', 'role' => 'label'],
            ['ref' => 'D', 'value' => trim(($e['first_name'].' '.$e['last_name']) ?: ($e['name'] ?? ''))],
            ['ref' => 'E', 'value' => 'Email', 'role' => 'label'],
            ['ref' => 'F', 'value' => (string)($e['email'] ?? '')],
            ['ref' => 'G', 'value' => 'Department', 'role' => 'label'],
            ['ref' => 'H', 'value' => (string)($e['department'] ?? '')],
            ['ref' => 'I', 'value' => ''],
            ['ref' => 'J', 'value' => ''],
        ],
        [
            ['ref' => 'A', 'value' => 'Position', 'role' => 'label'],
            ['ref' => 'B', 'value' => (string)($e['position'] ?? '')],
            ['ref' => 'C', 'value' => 'Status', 'role' => 'label'],
            ['ref' => 'D', 'value' => (string)($e['status'] ?? '')],
            ['ref' => 'E', 'value' => 'Civil Status', 'role' => 'label'],
            ['ref' => 'F', 'value' => (string)($e['civil_status'] ?? '')],
            ['ref' => 'G', 'value' => 'Entrance', 'role' => 'label'],
            ['ref' => 'H', 'value' => (string)($e['entrance_to_duty'] ?? '')],
            ['ref' => 'I', 'value' => ''],
            ['ref' => 'J', 'value' => ''],
        ],
    ];

    $headers = !empty($rows) ? array_keys($rows[0]) : ['leave_type_name','start_date','end_date','total_days','status','submitted_date','reason','snapshot_annual_balance','snapshot_sick_balance','snapshot_force_balance'];
    $tableHeaders = [];
    foreach ($headers as $h) {
        $tableHeaders[] = ucwords(str_replace('_', ' ', $h));
    }

    $tableRows = [];
    foreach($rows as $r) {
        $line = [];
        foreach($headers as $key) {
            $cell = $r[$key] ?? '';
            if ($key === 'total_days') {
                $cell = trunc3($cell);
            } elseif (in_array($key, ['start_date', 'end_date', 'submitted_date'], true)) {
                $cell = app_format_date((string)$cell);
            }
            $line[] = (string)$cell;
        }
        $tableRows[] = $line;
    }

    StyledXlsxExport::outputWorkbook([
        'filename' => $leaveHistoryFilename,
        'sheet_title' => $leaveHistoryFilename,
        'employee_info_rows' => $employeeInfoRows,
        'table_title' => 'LEAVE HISTORY',
        'table_headers' => $tableHeaders,
        'table_rows' => $tableRows,
        'column_widths' => [18, 18, 18, 14, 14, 18, 34, 18, 18, 18],
    ]);
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// fetch history for display
$stmt = $db->prepare("SELECT lr.*, COALESCE(lt.name, lr.leave_type) AS leave_type_name FROM leave_requests lr LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id WHERE lr.employee_id = ? ORDER BY lr.start_date DESC");
$stmt->execute([$id]);
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// fetch budget history
$budgetHistory = [];
$stmtBudget = $db->prepare("SELECT * FROM budget_history WHERE employee_id = ? ORDER BY created_at DESC LIMIT 30");
$stmtBudget->execute([$id]);
$budgetHistory = $stmtBudget->fetchAll(PDO::FETCH_ASSOC);

$balanceUsage = [
    'annual' => 0.0,
    'sick' => 0.0,
    'force' => 0.0,
];

$stmtUsage = $db->prepare("SELECT leave_type, action, old_balance, new_balance, notes FROM budget_history WHERE employee_id = ? ORDER BY id ASC");
$stmtUsage->execute([$id]);
$usageRows = $stmtUsage->fetchAll(PDO::FETCH_ASSOC);

foreach ($usageRows as $usageRow) {
    $leaveTypeKey = normalizeLeaveTypeKey((string)($usageRow['leave_type'] ?? ''));
    $actionKey = strtolower(trim((string)($usageRow['action'] ?? '')));
    $notesMeta = parseBudgetHistoryMeta((string)($usageRow['notes'] ?? ''));
    $delta = max(0, (float)($usageRow['old_balance'] ?? 0) - (float)($usageRow['new_balance'] ?? 0));

    if (in_array($actionKey, ['undertime_paid', 'undertime_unpaid'], true)) {
        $balanceUsage['annual'] += isset($notesMeta['UT_DEDUCT']) ? (float)$notesMeta['UT_DEDUCT'] : $delta;
        continue;
    }

    if ($actionKey !== 'deduction') {
        continue;
    }

    if ($leaveTypeKey === 'sick leave') {
        $balanceUsage['sick'] += $delta;
    } elseif ($leaveTypeKey === 'mandatory/forced leave') {
        $balanceUsage['annual'] += $delta;
        $balanceUsage['force'] += $delta;
    } else {
        $balanceUsage['annual'] += $delta;
    }
}

$balanceCards = [
    [
        'label' => 'Vacation',
        'remaining' => (float)($e['annual_balance'] ?? 0),
        'used' => (float)$balanceUsage['annual'],
        'hint' => 'Includes approved leave deductions and recorded undertime.',
    ],
    [
        'label' => 'Sick',
        'remaining' => (float)($e['sick_balance'] ?? 0),
        'used' => (float)$balanceUsage['sick'],
        'hint' => 'Based on approved sick-leave deductions.',
    ],
    [
        'label' => 'Force',
        'remaining' => (float)($e['force_balance'] ?? 0),
        'used' => (float)$balanceUsage['force'],
        'hint' => 'Reduced when Mandatory / Forced Leave is approved.',
    ],
];

// fetch all leave types for admin modals
$allTypes = [];
$stmtTypes = $db->query("SELECT * FROM leave_types ORDER BY name");
if ($stmtTypes) {
    $allTypes = $stmtTypes->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= htmlspecialchars(
        $pageTitle ?? 'Employee Profile'
    ); ?></title>
    <base href="<?= htmlspecialchars(rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/', ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .profile-header { display:flex; gap:16px; align-items:center; }
        .profile-pic { width:96px; height:96px; border-radius:50%; object-fit:cover; }
        .small-form input, .small-form select { width: 100%; padding:8px; margin-bottom:8px; border-radius:6px; }
        .employee-avatar-button {
            width: 88px;
            height: 88px;
            border-radius: 999px;
            overflow: hidden;
            border: 3px solid rgba(37, 99, 235, 0.16);
            padding: 0;
            background: #fff;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
            transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
        }
        .employee-avatar-button:hover {
            transform: translateY(-2px) scale(1.02);
            border-color: rgba(37, 99, 235, 0.34);
            box-shadow: 0 12px 28px rgba(15, 23, 42, 0.12);
        }
        .employee-avatar-button img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .employee-avatar-fallback {
            width: 88px;
            height: 88px;
            border-radius: 999px;
            background: linear-gradient(135deg, #eff6ff, #dbeafe);
            display:flex;
            align-items:center;
            justify-content:center;
            font-size:32px;
            border: 3px solid rgba(37, 99, 235, 0.16);
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
        }
        .employee-header-card {
            overflow: hidden;
        }
        .employee-header-meta {
            display:grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 10px;
            margin-top: 12px;
        }
        .employee-header-pill {
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 10px 12px;
            background: linear-gradient(180deg, #ffffff, #f8fafc);
        }
        .employee-header-pill .k {
            display:block;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: var(--muted);
            margin-bottom: 4px;
        }
        .employee-header-pill .v {
            font-size: 14px;
            color: var(--text);
            font-weight: 600;
        }
        .leave-balance-card .progress-meta {
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:12px;
            margin-bottom: 8px;
            font-size:12px;
            color: var(--muted);
        }
        .leave-balance-card .progress-bar-inner.has-balance {
            min-width: 10px;
        }
        .leave-balance-card .balance-hint {
            margin-top: 10px;
            font-size: 12px;
            color: var(--muted);
            line-height: 1.45;
        }
        .profile-image-modal-content {
            width: min(92vw, 760px);
            max-width: min(92vw, 760px);
            padding: 24px;
            position: relative;
            text-align: center;
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.24);
        }
        .profile-image-modal-title {
            margin: 0 0 16px 0;
            font-size: 20px;
            font-weight: 700;
            color: var(--text);
        }
        .profile-image-modal-figure {
            margin: 0;
            border-radius: 18px;
            overflow: hidden;
            background: #f8fafc;
            border: 1px solid var(--border);
        }
        .profile-image-modal-figure img {
            width: 100%;
            max-height: 72vh;
            object-fit: contain;
            display: block;
            background: #f8fafc;
        }
        .profile-image-modal-caption {
            margin-top: 14px;
            font-size: 13px;
            color: var(--muted);
        }
        .profile-picture-form {
            display: grid;
            gap: 12px;
        }
        .profile-picture-upload-row {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
        }
        .profile-picture-help {
            font-size: 13px;
            color: var(--muted);
        }
        .employee-avatar-button-empty {
            padding: 0;
            border: none;
            background: transparent;
        }
        .profile-image-modal-form {
            margin-top: 18px;
            display: grid;
            gap: 12px;
        }
        .profile-image-modal-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }
        @media (max-width: 720px) {
            .employee-header-meta {
                grid-template-columns: 1fr;
            }
            .profile-image-modal-content {
                padding: 18px;
                width: min(94vw, 94vw);
            }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/partials/sidebar.php'; ?>
<div class="app-main">
    <?php
    $title = 'Employee Profile';
    $subtitle = htmlspecialchars(trim(($e['first_name'].' '.$e['last_name']) ?: $e['name']));
    $actions = [];
    if(in_array($_SESSION['role'], ['admin'], true)) {
        $actions[] = '<a href="edit_employee.php?id='.$e['id'].'" class="btn btn-secondary">Edit profile</a>';
    }
    if($isSelfProfile) {
        $actions[] = '<a href="#" onclick="openPasswordModal(); return false;" class="btn btn-secondary">Change Password</a>';
    }
    if($isSelfProfile || in_array($_SESSION['role'], ['admin','hr'], true)) {
        $actions[] = '<a href="employee_profile.php?id='.$e['id'].'&export=1" class="btn btn-ghost">Export history</a>';
        $actions[] = '<a href="employee_profile.php?id='.$e['id'].'&export=leave_card" class="btn btn-ghost">Export leave card</a>';
    }
    if($isSelfProfile || in_array($_SESSION['role'], ['admin','hr','manager'], true)) {
        $actions[] = '<a href="reports.php?type=leave_card&employee_id='.$e['id'].'" class="btn btn-ghost">View Leave Card</a>';
    }
    include __DIR__ . '/partials/ui/page-header.php';
    ?>

    <!-- 1. Employee Header Card -->
    <div class="ui-card employee-header-card">
        <div class="two-column" style="align-items:flex-start;gap:20px;">
            <div>
                <button
                    type="button"
                    class="employee-avatar-button <?= !empty($e['profile_pic']) ? '' : 'employee-avatar-button-empty'; ?>"
                    onclick="openImageModal('<?= htmlspecialchars($profilePreviewSrc, ENT_QUOTES); ?>', '<?= htmlspecialchars(trim(($e['first_name'].' '.$e['last_name']) ?: $e['name']), ENT_QUOTES); ?>', <?= $isSelfProfile ? 'true' : 'false'; ?>)"
                    aria-label="Open profile image"
                >
                    <?php if(!empty($e['profile_pic'])): ?>
                        <img src="<?= htmlspecialchars($e['profile_pic']); ?>" alt="Profile">
                    <?php else: ?>
                        <div class="employee-avatar-fallback">👤</div>
                    <?php endif; ?>
                </button>
            </div>
            <div style="flex:1;min-width:0;">
                <h2 style="margin:0 0 8px 0;"><?= htmlspecialchars(trim(($e['first_name'].' '.$e['last_name']) ?: $e['name'])); ?></h2>
                <p style="margin:0 0 4px 0;font-size:14px;color:#6b7280;word-break:break-word;"><?= htmlspecialchars($e['email']); ?></p>
                <div class="employee-header-meta">
                    <div class="employee-header-pill">
                        <span class="k">Department</span>
                        <span class="v"><?= htmlspecialchars($e['department'] ?? '—'); ?></span>
                    </div>
                    <div class="employee-header-pill">
                        <span class="k">Position</span>
                        <span class="v"><?= htmlspecialchars($e['position'] ?? '—'); ?></span>
                    </div>
                    <div class="employee-header-pill">
                        <span class="k">Entrance to Duty</span>
                        <span class="v"><?= htmlspecialchars(!empty($e['entrance_to_duty']) ? date('F j, Y', strtotime($e['entrance_to_duty'])) : '—'); ?></span>
                    </div>
                    <div class="employee-header-pill">
                        <span class="k">Status</span>
                        <span class="v"><?= htmlspecialchars($e['status'] ?? '—'); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>



    <!-- leave balances cards -->
    <div class="leave-balance-section">
        <h3>Leave Balances</h3>
        <div class="leave-balance-cards">
            <?php foreach($balanceCards as $card): ?>
            <?php
                $remaining = max(0, (float)($card['remaining'] ?? 0));
                $used = max(0, (float)($card['used'] ?? 0));
                $totalTracked = $remaining + $used;
                $pct = $totalTracked > 0 ? max(0, min(100, ($remaining / $totalTracked) * 100)) : 0;
                $pctStyle = $pct > 0 ? number_format($pct, 2, '.', '') : '0';
            ?>
            <div class="leave-balance-card">
                <div class="label"><?= htmlspecialchars($card['label']); ?></div>
                <div class="value"><?= number_format($remaining,3); ?> days</div>
                <div class="progress-meta">
                    <span>Remaining credit</span>
                    <strong><?= number_format($pct,1); ?>%</strong>
                </div>
                <div class="progress-bar">
                    <div class="progress-bar-inner <?= $remaining > 0 ? 'has-balance' : ''; ?>" style="width:<?= $pctStyle; ?>%;"></div>
                </div>
                <div class="stats">
                    <span><?= number_format($used,3); ?></span>
                    <span><?= number_format($remaining,3); ?></span>
                </div>
                <div class="balance-hint"><?= htmlspecialchars($card['hint'] ?? ''); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>



    <!-- 4. Admin Actions (if admin/hr) -->
    <?php if(in_array($_SESSION['role'], ['admin','hr'])): ?>
    <div class="ui-card" style="margin-top:24px;">
        <h3>Admin Actions</h3>
        <div style="display:flex;gap:16px;flex-wrap:wrap;">
            <button id="btnUpdateBalances" class="action-btn">Update Balances</button>
            <button id="btnAddHistory" class="action-btn">Add Leave History Entry</button>
            <button id="btnRecordUndertime" class="action-btn">Record Undertime</button>
        </div>
    </div>
    <?php endif; ?>
    <script>
        ['btnUpdateBalances','btnAddHistory','btnRecordUndertime'].forEach(function(id){
            var el = document.getElementById(id);
            if(el){
                el.addEventListener('click', function(){
                    var target = 'modal' + id.replace('btn','');
                    openModal(target);
                });
            }
        });
    </script>

    <!-- 5. Leave History Table -->
    <div class="ui-card" style="margin-top:24px;">
        <h3>Leave History</h3>
        <?php if(empty($history)): ?>
            <p>No leave history available.</p>
        <?php else: ?>
        <div class="table-wrap">
            <table class="ui-table">
                <thead>
                <tr>
                    <th>Type</th>
                    <th>Dates</th>
                    <th>Days</th>
                    <th>Status</th>
                    <th>Submitted</th>
                    <th>Vacational Bal</th>
                    <th>Sick Bal</th>
                    <th>Force Bal</th>
                    <th>Comments</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach($history as $h): ?>
                <tr>
                    <td><?= htmlspecialchars($h['leave_type_name'] ?? $h['leave_type'] ?? ''); ?></td>
                    <td><?= htmlspecialchars(app_format_date_range($h['start_date'] ?? '', $h['end_date'] ?? '')); ?></td>
                    <td><?= isset($h['total_days']) ? trunc3($h['total_days']) : ''; ?></td>
                    <td><?= htmlspecialchars($h['status'] ?? ''); ?></td>
                    <td><?= !empty($h['created_at']) ? htmlspecialchars(app_format_date($h['created_at'])) : ''; ?></td>
                    <td><?= isset($h['snapshot_annual_balance']) ? trunc3($h['snapshot_annual_balance']) : '—'; ?></td>
                    <td><?= isset($h['snapshot_sick_balance']) ? trunc3($h['snapshot_sick_balance']) : '—'; ?></td>
                    <td><?= isset($h['snapshot_force_balance']) ? trunc3($h['snapshot_force_balance']) : '—'; ?></td>
                    <td><?= htmlspecialchars($h['manager_comments'] ?? $h['reason'] ?? ''); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- 6. Budget History Table -->
    <div class="ui-card" style="margin-top:24px;">
        <h3>Budget History</h3>
        <?php if(empty($budgetHistory)): ?>
            <p>No budget change history available.</p>
        <?php else: ?>
        <div class="table-wrap">
            <table class="ui-table">
                <thead>
                <tr>
                    <th>Leave Type</th>
                    <th>Action</th>
                    <th>Old Balance</th>
                    <th>New Balance</th>
                    <th>Date</th>
                    <th>Notes</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach($budgetHistory as $bh): ?>
                <tr>
                    <td><?= htmlspecialchars($bh['leave_type'] ?? ''); ?></td>
                    <td><?= htmlspecialchars($bh['action'] ?? ''); ?></td>
                    <td><?= isset($bh['old_balance']) ? trunc3($bh['old_balance']) : ''; ?></td>
                    <td><?= isset($bh['new_balance']) ? trunc3($bh['new_balance']) : ''; ?></td>
                    <td><?= !empty($bh['created_at']) ? htmlspecialchars(app_format_date($bh['created_at'])) : ''; ?></td>
                    <td><?= htmlspecialchars($bh['notes'] ?? ''); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

</div>

<div id="imageModal" class="modal">
  <div class="modal-content profile-image-modal-content">
    <button type="button" class="modal-close" onclick="closeImageModal()" aria-label="Close image preview">&times;</button>
    <h3 id="modalImageName" class="profile-image-modal-title">Profile photo</h3>
    <figure class="profile-image-modal-figure">
      <img id="modalImage" src="" alt="Profile image preview">
    </figure>
    <div id="modalImageCaption" class="profile-image-modal-caption">Click outside the image or press the close button to dismiss.</div>
    <?php if($isSelfProfile): ?>
    <form id="profileImageForm" action="../controllers/UserController.php" method="POST" enctype="multipart/form-data" class="profile-image-modal-form">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
      <input type="hidden" name="action" value="update_profile_picture">
      <input type="hidden" name="employee_id" value="<?= (int)$e['id']; ?>">
      <input id="modalProfilePicInput" type="file" name="profile_pic" accept="image/jpeg,image/png,image/gif,image/webp" hidden>
      <div class="profile-image-modal-actions">
        <button type="button" id="modalChoosePhotoBtn" class="btn btn-secondary" onclick="triggerModalPhotoPicker()">Change Photo</button>
        <button type="submit" id="modalSavePhotoBtn" class="btn btn-primary" style="display:none;">Save Photo</button>
        <button type="button" id="modalDiscardPhotoBtn" class="btn btn-ghost" style="display:none;" onclick="discardModalPhotoSelection()">Discard Changes</button>
      </div>
      <div class="profile-picture-help">Choose a JPG, PNG, GIF, or WEBP image up to 2MB. Your header avatar updates after saving.</div>
    </form>
    <?php endif; ?>
  </div>
</div>
<!-- admin modals -->
<div id="passwordModal" class="modal">
  <div class="modal-content small">
    <h3>Change Password</h3>
    <form method="POST" action="../controllers/UserController.php">
      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
      <input type="hidden" name="action" value="change_password">
      <label>Current Password</label>
      <input type="password" name="current" required>
      <label>New Password</label>
      <input type="password" name="new" required minlength="6">
      <div style="text-align:right;margin-top:12px;">
           <button type="submit">Update</button>
           <button type="button" onclick="closeModal('passwordModal')">Cancel</button>
      </div>
    </form>
  </div>
</div>

<div id="modalUpdateBalances" class="modal">
  <div class="modal-content small">
    <h3>Update Balances</h3>
    <form method="POST" action="../controllers/AdminController.php">
      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
      <input type="hidden" name="update_employee" value="1">
      <input type="hidden" name="employee_id" value="<?= $e['id']; ?>">
      <label>Vacational Balance</label>
      <input type="number" step="0.001" name="annual_balance" value="<?= trunc3($e['annual_balance'] ?? 0); ?>">
      <label>Sick Balance</label>
      <input type="number" step="0.001" name="sick_balance" value="<?= trunc3($e['sick_balance'] ?? 0); ?>">
      <label>Force Balance</label>
      <input type="number" name="force_balance" value="<?= trunc3($e['force_balance'] ?? 0); ?>">
      <div style="text-align:right;">
          <button type="submit">Update balances</button>
          <button type="button" onclick="closeModal('modalUpdateBalances')">Cancel</button>
      </div>
    </form>
  </div>
</div>
<div id="modalAddHistory" class="modal">
  <div class="modal-content">
    <h3>Add Leave History Entry</h3>
    <form method="POST" action="../controllers/AdminController.php">
      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
      <input type="hidden" name="add_history" value="1">
      <input type="hidden" name="employee_id" value="<?= $e['id']; ?>">
      <label>Leave Type</label>
      <select id="historyType" name="leave_type_id" style="width:100%;padding:8px 12px;margin-bottom:12px;border:1px solid var(--border);border-radius:6px;background:#fff;color:#111827;font-size:14px;cursor:pointer;">
        <option value="0">Vacational Accrual Earned</option>
        <option value="-1">Undertime</option>
        <?php foreach($allTypes as $lt): ?>
          <option value="<?= $lt['id']; ?>"><?= htmlspecialchars($lt['name']); ?></option>
        <?php endforeach; ?>
      </select>
      <label>Earning (1.25 days, optional)</label>
      <input type="number" step="0.001" name="earning_amount" value="">
      <label>Start Date</label>
      <input type="date" name="start_date" required>
      <label>End Date</label>
      <input type="date" name="end_date" required>
      <label>Total Days</label>
      <input id="totalDays" type="number" step="0.001" name="total_days" required>
      <label>Comments</label>
      <input type="text" name="reason">
      <!-- UNDERTIME FIELDS (only show when type = -1) -->
<div id="undertimeFields" style="display:none;margin-top:12px;">
  <strong>Undertime Details</strong>
  <div style="display:flex;gap:10px;margin-top:8px;">
    <div style="flex:1;">
      <label>Hours</label>
      <input id="utHours" type="number" step="1" name="undertime_hours" value="0" min="0">
    </div>
    <div style="flex:1;">
      <label>Minutes</label>
      <input id="utMins" type="number" step="1" name="undertime_minutes" value="0" min="0" max="59">
    </div>
  </div>
  <label style="margin-top:8px;display:block;">
    <input type="checkbox" name="undertime_with_pay" value="1"> With pay
  </label>
  <p style="font-size:12px;opacity:0.8;margin-top:6px;">
    Deduction uses chart: 480 mins = 1.000 day (8 hours = 1 day).
  </p>
</div>
      <hr>
      <p style="font-size:12px;opacity:0.8;">(optional) supply the leave balances that were available at the time of this historical entry.</p>
      <label>Vacational balance at time</label>
      <input type="number" step="0.001" name="snapshot_annual_balance" value="">
      <label>Sick balance at time</label>
      <input type="number" step="0.001" name="snapshot_sick_balance" value="">
      <label>Force balance at time</label>
      <input type="number" step="0.001" name="snapshot_force_balance" value="">
      <div style="text-align:right;">
        <button type="submit">Add history entry</button>
        <button type="button" onclick="closeModal('modalAddHistory')">Cancel</button>
      </div>
    </form>
  </div>
</div>
<div id="modalRecordUndertime" class="modal">
  <div class="modal-content small">
    <h3>Record Undertime</h3>
    <form method="POST" action="../controllers/AdminController.php" class="small-form">
      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
      <input type="hidden" name="record_undertime" value="1">
      <input type="hidden" name="employee_id" value="<?= $e['id']; ?>">
      <label>Date</label>
      <input type="date" name="date" required>
      <div style="display:flex;gap:10px;">
        <div style="flex:1;">
          <label>Hours</label>
          <input type="number" step="1" name="hours" value="0" min="0">
        </div>
        <div style="flex:1;">
          <label>Minutes</label>
          <input type="number" step="1" name="undertime_minutes" value="0" min="0" max="59">
        </div>
      </div>
      <label><input type="checkbox" name="with_pay" value="1"> With pay</label>
      <div style="text-align:right;">
        <button type="submit">Apply Deduction</button>
        <button type="button" onclick="closeModal('modalRecordUndertime')">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
var originalModalImageSrc = '';
var originalModalImageTitle = '';

function openImageModal(src, name, allowEdit) {
    var img = document.getElementById('modalImage');
    var title = document.getElementById('modalImageName');
    var caption = document.getElementById('modalImageCaption');
    var chooseBtn = document.getElementById('modalChoosePhotoBtn');
    var saveBtn = document.getElementById('modalSavePhotoBtn');
    var discardBtn = document.getElementById('modalDiscardPhotoBtn');
    var input = document.getElementById('modalProfilePicInput');

    if (!img || !title) return;

    originalModalImageSrc = src || '';
    originalModalImageTitle = name || 'Profile photo';

    img.src = originalModalImageSrc;
    title.textContent = originalModalImageTitle;
    if (caption) caption.textContent = allowEdit ? 'Click Change Photo to preview a new image before saving.' : 'Click outside the image or press the close button to dismiss.';

    if (input) input.value = '';
    if (chooseBtn) chooseBtn.style.display = allowEdit ? '' : 'none';
    if (saveBtn) saveBtn.style.display = 'none';
    if (discardBtn) discardBtn.style.display = 'none';

    openModal('imageModal');
}

function closeImageModal() {
    discardModalPhotoSelection(false);
    closeModal('imageModal');
}

function triggerModalPhotoPicker() {
    var input = document.getElementById('modalProfilePicInput');
    if (input) input.click();
}

function discardModalPhotoSelection(keepCaption) {
    if (keepCaption === undefined) keepCaption = true;
    var img = document.getElementById('modalImage');
    var title = document.getElementById('modalImageName');
    var caption = document.getElementById('modalImageCaption');
    var saveBtn = document.getElementById('modalSavePhotoBtn');
    var discardBtn = document.getElementById('modalDiscardPhotoBtn');
    var chooseBtn = document.getElementById('modalChoosePhotoBtn');
    var input = document.getElementById('modalProfilePicInput');

    if (img) img.src = originalModalImageSrc;
    if (title) title.textContent = originalModalImageTitle || 'Profile photo';
    if (caption && keepCaption && chooseBtn) caption.textContent = 'Click Change Photo to preview a new image before saving.';
    if (input) input.value = '';
    if (saveBtn) saveBtn.style.display = 'none';
    if (discardBtn) discardBtn.style.display = 'none';
    if (chooseBtn) chooseBtn.style.display = '';
}

document.addEventListener('DOMContentLoaded', function(){
    var input = document.getElementById('modalProfilePicInput');
    if (!input) return;

    input.addEventListener('change', function(){
        var file = this.files && this.files[0] ? this.files[0] : null;
        var img = document.getElementById('modalImage');
        var title = document.getElementById('modalImageName');
        var caption = document.getElementById('modalImageCaption');
        var saveBtn = document.getElementById('modalSavePhotoBtn');
        var discardBtn = document.getElementById('modalDiscardPhotoBtn');
        var chooseBtn = document.getElementById('modalChoosePhotoBtn');

        if (!file) {
            discardModalPhotoSelection();
            return;
        }

        var reader = new FileReader();
        reader.onload = function(e){
            if (img) img.src = e.target.result;
            if (title) title.textContent = file.name;
            if (caption) caption.textContent = 'Previewing new photo. Save Photo to apply it or Discard Changes to keep your current image.';
            if (saveBtn) saveBtn.style.display = '';
            if (discardBtn) discardBtn.style.display = '';
            if (chooseBtn) chooseBtn.style.display = '';
        };
        reader.readAsDataURL(file);
    });
});

function openPasswordModal() {
    openModal('passwordModal');
}

function closePasswordModal() {
    closeModal('passwordModal');
}

function openModal(id) {
    var m = document.getElementById(id);
    if(m) m.classList.add('open');
}

function closeModal(id) {
    var m = document.getElementById(id);
    if(m) m.classList.remove('open');
}

// allow clicking outside to close
['imageModal','passwordModal','modalUpdateBalances','modalAddHistory','modalRecordUndertime'].forEach(function(id){
    var el = document.getElementById(id);
    if(el){
        el.addEventListener('click', function(e){
            if(e.target === this) {
                if (id === 'imageModal') closeImageModal();
                else closeModal(id);
            }
        });
    }
});

document.addEventListener('keydown', function(e){
    if (e.key === 'Escape') {
        ['imageModal','passwordModal','modalUpdateBalances','modalAddHistory','modalRecordUndertime'].forEach(function(id){
            var el = document.getElementById(id);
            if (el && el.classList.contains('open')) {
                if (id === 'imageModal') closeImageModal();
                else closeModal(id);
            }
        });
    }
});

// dynamic form logic for history entry
// dynamic form logic for history entry
(function(){
    var typeSelect = document.getElementById('historyType');
    var totalDays = document.getElementById('totalDays');
    var earnField = document.querySelector('input[name="earning_amount"]');
    var undertimeFields = document.getElementById('undertimeFields');

    function updateRequirements(){
        var typeVal = typeSelect ? String(typeSelect.value) : '';

        var isAccrual = typeVal === '0';
        var isUT = typeVal === '-1';

        // Hide UT by default
        if (undertimeFields) undertimeFields.style.display = 'none';

        // Earning field defaults
        if (earnField) {
            earnField.required = false;
            earnField.disabled = true;
        }

        // Total days defaults
        if (totalDays) {
            totalDays.required = false;
            totalDays.disabled = true;
        }

        if (isAccrual) {
            // accrual: earning required, no total_days
            if (earnField) { earnField.disabled = false; earnField.required = true; }
            if (totalDays) totalDays.value = '';
        } else if (isUT) {
            // undertime: show UT fields, no total_days, no earning
            if (undertimeFields) undertimeFields.style.display = 'block';
            if (totalDays) totalDays.value = '';
        } else {
            // normal leave: total_days required
            if (totalDays) { totalDays.disabled = false; totalDays.required = true; }
        }
    }

    if (typeSelect) {
        typeSelect.addEventListener('change', updateRequirements);
    }

    // Initial run
    updateRequirements();
})();
</script>

</body>
</html>
