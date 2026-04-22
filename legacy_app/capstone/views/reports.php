<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../config/database.php';
require_once '../helpers/Auth.php';
require_once '../helpers/Flash.php';
Auth::requireLogin('login.php');
require_once '../helpers/DateHelper.php';

$db = (new Database())->connect();

/**
 * Helpers
 */
function safe_h($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function trunc3($v): string {
    if ($v === null || $v === '') return '';
    $n = (float)$v;
    $t = floor($n * 1000) / 1000;         // TRUNCATE (not round)
    return number_format($t, 3, '.', ''); // always 3 decimals
}

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

function leaveCardExportBaseName(?array $currentEmp): string {
    $fullName = trim((string)($currentEmp['first_name'] ?? '') . ' ' . (string)($currentEmp['last_name'] ?? ''));
    if ($fullName === '') {
        $fullName = 'Employee';
    }
    return safeExportFilename('Leave Card - ' . $fullName);
}

/**
 * Detect if a column exists in a table (safe + reusable).
 */
function columnExists(PDO $db, string $table, string $column): bool {
    try {
        $stmt = $db->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $stmt->execute([$column]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $t) {
        return false;
    }
}

/**
 * Build Leave Card rows:
 * - ONLY Leave Requests (using snapshot balances - NO budget_history)
 * - Each row represents a leave request with snapshots as-is
 */
function bh_date(array $r, bool $hasTransDate): string {
    if ($hasTransDate && !empty($r['trans_date'])) return (string)$r['trans_date'];
    return substr((string)($r['created_at'] ?? ''), 0, 10);
}

function buildLeaveCardRows(PDO $db, int $empId, bool $hasTransDate, bool $hasSnapshots): array {
    $rows = [];

    // -----------------------------
    // 1) LEAVE REQUESTS (history rows)
    // -----------------------------
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
                $statusRaw = strtolower(trim((string)$r['status']));
        $days = floatval($r['total_days']);

        $txDate = !empty($r['start_date']) ? (string)$r['start_date'] : substr((string)$r['created_at'], 0, 10);

        if (strtolower(trim($leaveType)) === 'undertime') {
            continue;
        }

        $isAccrual = isAccrualLeaveType($leaveType);
        $isSick = isSickLeaveType($leaveType);
        $isForce = isForceLeaveType($leaveType);

        $vacEarn = 0.0; $sickEarn = 0.0;
        $vacDed  = 0.0; $sickDed  = 0.0;

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

        // snapshots EXACTLY as stored (typed by admin for historical entries)
        $vacBal = ($r['snapshot_annual_balance'] !== null && $r['snapshot_annual_balance'] !== '')
            ? floatval($r['snapshot_annual_balance']) : '';
        $sickBal = ($r['snapshot_sick_balance'] !== null && $r['snapshot_sick_balance'] !== '')
            ? floatval($r['snapshot_sick_balance']) : '';

                $particulars = $leaveType;
        if (!$isAccrual && stripos($particulars, 'leave') === false) {
            $particulars .= ' Leave';
        }

        $rows[] = [
            'date' => $txDate,
            'particulars' => $particulars,
            'vac_earned' => $vacEarn,
            'vac_deducted' => $vacDed,
            'vac_balance' => $vacBal,
            'sick_earned' => $sickEarn,
            'sick_deducted' => $sickDed,
            'sick_balance' => $sickBal,
            'status' => ucfirst($statusRaw),
            '_sort_ts' => strtotime($txDate ?: '1970-01-01'),
            '_sort_seq' => 1,
        ];
    }

    // -----------------------------
    // 2) BUDGET HISTORY (undertime / adjustments / earnings / etc)
    // -----------------------------
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

        $vacEarn = 0.0; $sickEarn = 0.0;
        $vacDed  = 0.0; $sickDed  = 0.0;
        $vacBal  = '';  $sickBal  = '';

        // Particulars
                if ($actionLower === 'undertime_paid' || $actionLower === 'undertime_unpaid') {
            $meta = parseBudgetHistoryMeta($notes);
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

            $particulars = 'Undertime ' . ($actionLower === 'undertime_paid' ? '(With pay)' : '(Without pay)');
        } else {
            $old = floatval($r['old_balance']);
            $new = floatval($r['new_balance']);
            $deltaEarn = max(0, $new - $old);
            $deltaDed  = max(0, $old - $new);

            $isSick = isSickLeaveType($leaveType);
            $isForce = isForceLeaveType($leaveType);

            if (in_array($actionLower, ['accrual', 'earning'], true)) {
                if ($isSick) {
                    $sickEarn = $deltaEarn;
                    $sickBal = $new;
                } elseif (!$isForce) {
                    $vacEarn = $deltaEarn;
                    $vacBal = $new;
                }
            } else {
                if ($isSick) {
                    $sickEarn = $deltaEarn;
                    $sickDed  = $deltaDed;
                    $sickBal  = $new;
                } elseif (!$isForce) {
                    $vacEarn = $deltaEarn;
                    $vacDed  = $deltaDed;
                    $vacBal  = $new;
                }
            }

            $particulars = ucfirst($actionLower) . ' ' . $leaveType;
            if ($isForce) {
                $particulars .= ' (Force balance entry)';
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
            '_sort_ts' => strtotime($txDate ?: '1970-01-01'),
            '_sort_seq' => 2,
        ];
    }

    // FINAL: sort everything chronologically by date, then seq (leave rows first), then particulars
    usort($rows, function($a, $b) {
        $ta = $a['_sort_ts'] ?? 0;
        $tb = $b['_sort_ts'] ?? 0;
        if ($ta !== $tb) return $ta <=> $tb;

        $sa = $a['_sort_seq'] ?? 0;
        $sb = $b['_sort_seq'] ?? 0;
        if ($sa !== $sb) return $sa <=> $sb;

        return strcmp((string)($a['particulars'] ?? ''), (string)($b['particulars'] ?? ''));
    });

    // remove internal sort keys
    foreach ($rows as &$rr) {
        unset($rr['_sort_ts'], $rr['_sort_seq']);
    }
    unset($rr);

    return $rows;
}

function exportCsv(array $headers, array $rows, string $filename): void {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    $out = fopen('php://output', 'w');
    fputcsv($out, $headers);

    foreach ($rows as $row) {
        if (!is_array($row)) continue;

        // leave_card style rows
        if (isset($row['date'], $row['particulars'])) {
            $vb = $row['vac_balance'] ?? '';
            $sb = $row['sick_balance'] ?? '';
            fputcsv($out, [
                app_format_date($row['date'] ?? ''),
                $row['particulars'] ?? '',
                (($row['vac_earned'] ?? 0) != 0 ? trunc3($row['vac_earned']) : ''),
                (($row['vac_deducted'] ?? 0) != 0 ? trunc3($row['vac_deducted']) : ''),
                ($vb === '' ? '' : trunc3($vb)),
                (($row['sick_earned'] ?? 0) != 0 ? trunc3($row['sick_earned']) : ''),
                (($row['sick_deducted'] ?? 0) != 0 ? trunc3($row['sick_deducted']) : ''),
                ($sb === '' ? '' : trunc3($sb)),
                $row['status'] ?? ''
            ]);
        } else {
            fputcsv($out, array_values($row));
        }
    }

    fclose($out);
    exit();
}

function exportExcelPhpSpreadsheet(array $headers, array $rows, string $filename, ?array $currentEmp): void {
    if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
        exportCsv($headers, $rows, preg_replace('/\.xlsx$/', '.csv', $filename));
    }

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $sheet->setTitle('Leave Card History');
    $sheet->setCellValueByColumnAndRow(1, 1, 'LEAVE CARD - COMPLETE TRANSACTION HISTORY');
    $sheet->mergeCells('A1:I1');
    $sheet->getStyle('A1')->getFont()->setBold(true);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal('center');

    if (!empty($currentEmp)) {
        $fullName = trim(($currentEmp['first_name'] ?? '') . ' ' . ($currentEmp['last_name'] ?? ''));
        $sheet->setCellValueByColumnAndRow(1, 2, 'Employee: ' . $fullName);
        $sheet->mergeCells('A2:I2');
    }

    $headerRow = 4;
    $col = 1;
    foreach ($headers as $h) {
        $sheet->setCellValueByColumnAndRow($col, $headerRow, $h);
        $sheet->getStyleByColumnAndRow($col, $headerRow)->getFont()->setBold(true);
        $sheet->getStyleByColumnAndRow($col, $headerRow)->getFill()
            ->setFillType('solid')->getStartColor()->setRGB('D3D3D3');
        $col++;
    }

    $rownum = 5;
    foreach ($rows as $row) {
        $sheet->setCellValueByColumnAndRow(1, $rownum, app_format_date($row['date'] ?? ''));
        $sheet->setCellValueByColumnAndRow(2, $rownum, $row['particulars'] ?? '');
        $sheet->setCellValueByColumnAndRow(3, $rownum, (($row['vac_earned'] ?? 0) != 0 ? trunc3($row['vac_earned']) : ''));
        $sheet->setCellValueByColumnAndRow(4, $rownum, (($row['vac_deducted'] ?? 0) != 0 ? trunc3($row['vac_deducted']) : ''));
        $vb = $row['vac_balance'] ?? '';
        $sheet->setCellValueByColumnAndRow(5, $rownum, ($vb === '' ? '' : trunc3($vb)));
        $sheet->setCellValueByColumnAndRow(6, $rownum, (($row['sick_earned'] ?? 0) != 0 ? trunc3($row['sick_earned']) : ''));
        $sheet->setCellValueByColumnAndRow(7, $rownum, (($row['sick_deducted'] ?? 0) != 0 ? trunc3($row['sick_deducted']) : ''));
        $sb = $row['sick_balance'] ?? '';
        $sheet->setCellValueByColumnAndRow(8, $rownum, ($sb === '' ? '' : trunc3($sb)));
        $sheet->setCellValueByColumnAndRow(9, $rownum, $row['status'] ?? '');
        $rownum++;
    }

    foreach (range('A', 'I') as $c) {
        $sheet->getColumnDimension($c)->setAutoSize(true);
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save('php://output');
    exit();
}

function exportPdfTcpdf(array $headers, array $rows, string $filename, ?array $currentEmp, string $title): void {
    if (!class_exists('TCPDF')) {
        exportCsv($headers, $rows, preg_replace('/\.pdf$/', '.csv', $filename));
    }

    $pdf = new TCPDF();
    $pdf->AddPage();

    $html = '<h2>' . safe_h($title) . '</h2>';
    if (!empty($currentEmp)) {
        $html .= '<p><strong>Employee:</strong> ' . safe_h(($currentEmp['first_name'] ?? '') . ' ' . ($currentEmp['last_name'] ?? '')) . '</p>';
    }

    $html .= '<table border="1" cellpadding="4">';
    $html .= '<tr>';
    foreach ($headers as $h) $html .= '<th>' . safe_h($h) . '</th>';
    $html .= '</tr>';

    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        $vb = $row['vac_balance'] ?? '';
        $sb = $row['sick_balance'] ?? '';
        $html .= '<tr>';
        $html .= '<td>' . safe_h(app_format_date($row['date'] ?? '')) . '</td>';
        $html .= '<td>' . safe_h($row['particulars'] ?? '') . '</td>';
        $html .= '<td>' . ((($row['vac_earned'] ?? 0) != 0) ? trunc3($row['vac_earned']) : '') . '</td>';
        $html .= '<td>' . ((($row['vac_deducted'] ?? 0) != 0) ? trunc3($row['vac_deducted']) : '') . '</td>';
        $html .= '<td>' . ($vb === '' ? '' : trunc3($vb)) . '</td>';
        $html .= '<td>' . ((($row['sick_earned'] ?? 0) != 0) ? trunc3($row['sick_earned']) : '') . '</td>';
        $html .= '<td>' . ((($row['sick_deducted'] ?? 0) != 0) ? trunc3($row['sick_deducted']) : '') . '</td>';
        $html .= '<td>' . ($sb === '' ? '' : trunc3($sb)) . '</td>';
        $html .= '<td>' . safe_h($row['status'] ?? '') . '</td>';
        $html .= '</tr>';
    }

    $html .= '</table>';
    $pdf->writeHTML($html);
    $pdf->Output($filename, 'D');
    exit();
}

/**
 * Session & Access rules
 */
$role = $_SESSION['role'] ?? '';
$sessionEmpId = intval($_SESSION['emp_id'] ?? 0);

$sessionDepartment = '';
if ($sessionEmpId > 0) {
    try {
        $deptStmt = $db->prepare("SELECT department FROM employees WHERE id = ? LIMIT 1");
        $deptStmt->execute([$sessionEmpId]);
        $sessionDepartment = (string)($deptStmt->fetchColumn() ?: '');
    } catch (Throwable $t) {
        $sessionDepartment = '';
    }
}

// Detect columns
$hasTransDate = columnExists($db, 'budget_history', 'trans_date');
$hasSnapshots = columnExists($db, 'leave_requests', 'snapshot_annual_balance') && columnExists($db, 'leave_requests', 'snapshot_sick_balance');

// Get report type & filters
$reportType = $_GET['type'] ?? 'summary';
$departmentFilter = $_GET['dept'] ?? '';
$employeeFilter = intval($_GET['employee_id'] ?? 0);

// Employees are limited to their own leave card.
if ($role === 'employee') {
    $reportType = 'leave_card';
    $employeeFilter = $sessionEmpId;
} elseif ($role === 'department_head') {
    // Department heads are restricted to their own department across reports.
    $departmentFilter = $sessionDepartment;
    if ($reportType === 'leave_card' && $employeeFilter <= 0) {
        $employeeFilter = $sessionEmpId;
    }
} elseif ($role === 'personnel' && $reportType === 'leave_card' && $employeeFilter <= 0 && $sessionEmpId > 0) {
    $employeeFilter = $sessionEmpId;
}

// Access: admin/manager/hr/personnel can access reports; employee only own leave card; department head summary is locked to own department.
if (!in_array($role, ['admin', 'manager', 'hr', 'personnel', 'employee', 'department_head'], true)) {
    flash_redirect('dashboard.php', 'error', 'Access Denied');
}
if ($role === 'employee' && ($employeeFilter !== $sessionEmpId || $reportType !== 'leave_card')) {
    flash_redirect('reports.php?type=leave_card&employee_id=' . $sessionEmpId, 'error', 'You can only view your own leave card.');
}
if ($role === 'department_head') {
    if (!in_array($reportType, ['summary', 'balance', 'usage', 'leave_card'], true)) {
        $reportType = 'summary';
    }
    if ($reportType === 'leave_card' && $employeeFilter > 0) {
        $empDeptStmt = $db->prepare("SELECT department FROM employees WHERE id = ? LIMIT 1");
        $empDeptStmt->execute([$employeeFilter]);
        $targetDept = (string)($empDeptStmt->fetchColumn() ?: '');
        if ($targetDept === '' || $targetDept !== $sessionDepartment) {
            flash_redirect('reports.php?type=summary', 'error', 'You can only view leave cards for employees in your department.');
        }
    }
}

/**
 * Load current employee record (for header display)
 */
$currentEmp = null;
if (!empty($employeeFilter)) {
    $stmt = $db->prepare("SELECT id, first_name, last_name FROM employees WHERE id = ?");
    $stmt->execute([$employeeFilter]);
    $currentEmp = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Export handling
if (isset($_GET['export']) && $_GET['export'] === '1') {
    $format = $_GET['format'] ?? 'csv';

    $rows = [];
    $headers = [];
    $reportTitle = 'Reports';

    if ($reportType === 'balance') {
        if ($departmentFilter) {
            $stmt = $db->prepare(
                "SELECT e.id, e.first_name, e.last_name, e.department, e.annual_balance, e.sick_balance, e.force_balance
                 FROM employees e
                 WHERE e.department = ?
                 ORDER BY e.first_name"
            );
            $stmt->execute([$departmentFilter]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $rows = $db->query(
                "SELECT e.id, e.first_name, e.last_name, e.department, e.annual_balance, e.sick_balance, e.force_balance
                 FROM employees e
                 ORDER BY e.department, e.first_name"
            )->fetchAll(PDO::FETCH_ASSOC);
        }
        $headers = ['ID', 'First Name', 'Last Name', 'Department', 'Vacational Balance', 'Sick Balance', 'Force Balance'];
        $reportTitle = "Leave Balance Report";

        if ($format === 'excel') {
            exportExcelPhpSpreadsheet($headers, $rows, 'balance_' . date('Y-m-d') . '.xlsx', null);
        } elseif ($format === 'pdf') {
            exportPdfTcpdf($headers, $rows, 'balance_' . date('Y-m-d') . '.pdf', null, $reportTitle);
        } else {
            exportCsv($headers, $rows, 'balance_' . date('Y-m-d') . '.csv');
        }
    }

    if ($reportType === 'leave_card' && $employeeFilter) {
        $rows = buildLeaveCardRows($db, $employeeFilter, $hasTransDate, $hasSnapshots);
        $headers = ['Date','Particulars','Vac Earned','Vac Deducted','Vac Balance','Sick Earned','Sick Deducted','Sick Balance','Status'];
        $reportTitle = "Leave Card";

                $baseName = leaveCardExportBaseName($currentEmp);

        if ($format === 'excel') {
            exportExcelPhpSpreadsheet($headers, $rows, $baseName . '.xlsx', $currentEmp);
        } elseif ($format === 'pdf') {
            exportPdfTcpdf($headers, $rows, $baseName . '.pdf', $currentEmp, $reportTitle);
        } else {
            exportCsv($headers, $rows, $baseName . '.csv');
        }
    }

    if ($reportType === 'usage') {
        $query =
            "SELECT e.department, COALESCE(lt.name, lr.leave_type) AS leave_type, COUNT(*) AS count, SUM(lr.total_days) AS total_days
             FROM leave_requests lr
             JOIN employees e ON lr.employee_id = e.id
             LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id
             WHERE LOWER(lr.status) = 'approved'";

        if ($departmentFilter) {
            $query .= " AND e.department = ?";
            $query .= " GROUP BY e.department, leave_type ORDER BY e.department, leave_type";
            $stmt = $db->prepare($query);
            $stmt->execute([$departmentFilter]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $query .= " GROUP BY e.department, leave_type ORDER BY e.department, leave_type";
            $rows = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);
        }

        $headers = ['Department', 'Leave Type', 'Request Count', 'Total Days'];
        $reportTitle = "Leave Usage Report";

        if ($format === 'excel') {
            exportExcelPhpSpreadsheet($headers, $rows, 'usage_' . date('Y-m-d') . '.xlsx', null);
        } elseif ($format === 'pdf') {
            exportPdfTcpdf($headers, $rows, 'usage_' . date('Y-m-d') . '.pdf', null, $reportTitle);
        } else {
            exportCsv($headers, $rows, 'usage_' . date('Y-m-d') . '.csv');
        }
    }

    die("Invalid export request.");
}

// Departments for filters
$deptStmt = $db->query("SELECT DISTINCT department FROM employees ORDER BY department");
$departments = $deptStmt->fetchAll(PDO::FETCH_ASSOC);

// Page data
$reportTitle = "Leave System Summary";
$reportData = [];

if ($reportType === 'balance') {
    if ($departmentFilter) {
        $stmt = $db->prepare(
            "SELECT e.id, e.first_name, e.last_name, e.department, e.annual_balance, e.sick_balance, e.force_balance
             FROM employees e
             WHERE e.department = ?
             ORDER BY e.department, e.first_name"
        );
        $stmt->execute([$departmentFilter]);
    } else {
        $stmt = $db->prepare(
            "SELECT e.id, e.first_name, e.last_name, e.department, e.annual_balance, e.sick_balance, e.force_balance
             FROM employees e
             ORDER BY e.department, e.first_name"
        );
        $stmt->execute();
    }
    $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $reportTitle = "Leave Balance Report";
} elseif ($reportType === 'leave_card' && $employeeFilter) {
    $reportData = buildLeaveCardRows($db, $employeeFilter, $hasTransDate, $hasSnapshots);
    $reportTitle = "Leave Card";
} elseif ($reportType === 'usage') {
    $query =
        "SELECT e.department, COALESCE(lt.name, lr.leave_type) as leave_type, COUNT(*) as count, SUM(lr.total_days) as total_days
         FROM leave_requests lr
         JOIN employees e ON lr.employee_id = e.id
         LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id
         WHERE LOWER(lr.status) = 'approved'";

    if ($departmentFilter) {
        $query .= " AND e.department = ?";
        $query .= " GROUP BY e.department, leave_type ORDER BY e.department, leave_type";
        $stmt = $db->prepare($query);
        $stmt->execute([$departmentFilter]);
    } else {
        $query .= " GROUP BY e.department, leave_type ORDER BY e.department, leave_type";
        $stmt = $db->prepare($query);
        $stmt->execute();
    }

    $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $reportTitle = "Leave Usage Report";
} else {
    if ($departmentFilter !== '') {
        $empCountStmt = $db->prepare("SELECT COUNT(*) FROM employees WHERE department = ?");
        $empCountStmt->execute([$departmentFilter]);
        $totalEmployees = $empCountStmt->fetchColumn();

        $pendingStmt = $db->prepare("
            SELECT COUNT(*)
            FROM leave_requests lr
            INNER JOIN employees e ON e.id = lr.employee_id
            WHERE LOWER(lr.status) = 'pending' AND e.department = ?
        ");
        $pendingStmt->execute([$departmentFilter]);
        $totalPending = $pendingStmt->fetchColumn();

        $approvedStmt = $db->prepare("
            SELECT COUNT(*)
            FROM leave_requests lr
            INNER JOIN employees e ON e.id = lr.employee_id
            WHERE LOWER(lr.status) = 'approved' AND e.department = ?
        ");
        $approvedStmt->execute([$departmentFilter]);
        $totalApproved = $approvedStmt->fetchColumn();

        $avgStmt = $db->prepare("SELECT AVG(annual_balance) FROM employees WHERE department = ?");
        $avgStmt->execute([$departmentFilter]);
        $avgAnnualBalance = $avgStmt->fetchColumn();

        $reportTitle = "Leave System Summary - " . $departmentFilter;
    } else {
        $totalEmployees = $db->query("SELECT COUNT(*) FROM employees")->fetchColumn();
        $totalPending = $db->query("SELECT COUNT(*) FROM leave_requests WHERE LOWER(status) = 'pending'")->fetchColumn();
        $totalApproved = $db->query("SELECT COUNT(*) FROM leave_requests WHERE LOWER(status) = 'approved'")->fetchColumn();
        $avgAnnualBalance = $db->query("SELECT AVG(annual_balance) FROM employees")->fetchColumn();
        $reportTitle = "Leave System Summary";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <base href="<?= htmlspecialchars(rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/', ENT_QUOTES, 'UTF-8'); ?>">
    <title>Reports - Leave System</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>

<?php include __DIR__ . '/partials/sidebar.php'; ?>

<div class="app-main">
    <h1><?= safe_h($reportTitle); ?></h1>
    <p class="page-subtitle" style="margin-bottom:14px;">Generate summaries, exports, and leave records</p>

    <div class="ui-card report-panel-card" style="margin-bottom:24px;">
        <div class="report-panel-header">
            <h3>Report Filter</h3>
        </div>
        <form method="GET" class="report-filter-row">
            <?php if($role === 'employee'): ?>
                <input type="hidden" name="type" value="leave_card">
                <div class="report-filter-field">
                    <p class="report-filter-static" style="margin:0;">Viewing: <strong>Leave Card</strong></p>
                </div>
            <?php else: ?>
            <div class="report-filter-field">
                <label>Report Type:</label>
                <select name="type">
                    <option value="summary" <?= ($reportType === 'summary' ? 'selected' : ''); ?>>Summary</option>
                    <option value="balance" <?= ($reportType === 'balance' ? 'selected' : ''); ?>>Leave Balance</option>
                    <option value="usage" <?= ($reportType === 'usage' ? 'selected' : ''); ?>>Leave Usage</option>
                    <option value="leave_card" <?= ($reportType === 'leave_card' ? 'selected' : ''); ?>>Leave Card</option>
                </select>
            </div>
            <?php endif; ?>

            <?php if($reportType !== 'leave_card'): ?>
            <div class="report-filter-field">
                <label>Department:</label>
                <?php if ($role === 'department_head'): ?>
                    <input type="hidden" name="dept" value="<?= safe_h($departmentFilter); ?>">
                    <span class="report-field-value"><?= safe_h($departmentFilter ?: 'No department assigned'); ?></span>
                <?php else: ?>
                <select name="dept">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= safe_h($d['department']); ?>" <?= ($departmentFilter === $d['department'] ? 'selected' : ''); ?>>
                            <?= safe_h($d['department']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="report-filter-field">
                <label>Employee:</label>
                <?php if($role === 'employee'): ?>
                    <input type="hidden" name="employee_id" value="<?= safe_h($sessionEmpId); ?>">
                    <span class="report-field-value"><?= safe_h($currentEmp ? (($currentEmp['first_name'] ?? '').' '.($currentEmp['last_name'] ?? '')) : ''); ?></span>
                <?php else: ?>
                    <select name="employee_id">
                        <option value="">-- select --</option>
                        <?php
                        if ($role === 'department_head') {
                            $empStmt = $db->prepare("SELECT id, first_name, last_name FROM employees WHERE department = ? ORDER BY first_name, last_name");
                            $empStmt->execute([$sessionDepartment]);
                        } else {
                            $empStmt = $db->query("SELECT id, first_name, last_name FROM employees ORDER BY first_name, last_name");
                        }
                        foreach($empStmt->fetchAll(PDO::FETCH_ASSOC) as $empRow):
                        ?>
                            <option value="<?= (int)$empRow['id']; ?>" <?= ($employeeFilter == $empRow['id'] ? 'selected' : ''); ?>>
                                <?= safe_h(($empRow['first_name'] ?? '').' '.($empRow['last_name'] ?? '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="report-filter-field report-filter-button-field">
                <label class="report-filter-label--hidden">Apply Filter</label>
                <button type="submit" class="report-filter-button">Apply Filter</button>
            </div>

            <?php
            $base = "?type=" . urlencode($reportType) . "&dept=" . urlencode($departmentFilter);
            if ($reportType === 'leave_card' && $employeeFilter) $base .= "&employee_id=" . intval($employeeFilter);
            ?>
            <?php if ($reportType === 'leave_card' && $employeeFilter): ?>
                <a href="employee_profile.php?export=leave_card&id=<?= intval($employeeFilter); ?>" class="report-export-button">
                    <span class="export-icon">📄</span> Export Leave Card
                </a>
            <?php else: ?>
                <a href="<?= $base; ?>&export=1&format=excel" class="report-export-button">
                    <span class="export-icon">📊</span> Export Excel
                </a>
                <a href="<?= $base; ?>&export=1&format=pdf" class="report-export-button">
                    <span class="export-icon">📄</span> Export PDF
                </a>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($reportType === 'summary'): ?>
        <div class="ui-card report-summary-card">
            <div class="report-panel-header">
                <h3>System Summary</h3>
            </div>
            <div class="summary-table-wrapper">
                <table class="report-summary-table">
                    <thead>
                        <tr>
                            <th>Metric</th>
                            <th>   Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Total Employees</td>
                            <td><span class="summary-value-pill"><?= (int)$totalEmployees; ?></span></td>
                        </tr> 
                        <tr>
                            <td>Pending Requests</td>
                            <td><span class="summary-value-pill"><?= (int)$totalPending; ?></span></td>
                        </tr>
                        <tr>
                            <td>Approved Requests</td>
                            <td><span class="summary-value-pill"><?= (int)$totalApproved; ?></span></td>
                        </tr>
                        <tr>
                            <td>Average Vacational Balance</td>
                            <td><span class="summary-value-pill"><?= trunc3($avgAnnualBalance); ?> days</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($reportType === 'balance'): ?>
        <div class="ui-card">
            <table>
                <tr>
                    <th>Name</th>
                    <th>Department</th>
                    <th>Vacational Balance</th>
                    <th>Sick Balance</th>
                    <th>Force Balance</th>
                </tr>
                <?php foreach ($reportData as $row): ?>
                <tr>
                    <td><?= safe_h(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')); ?></td>
                    <td><?= safe_h($row['department'] ?? ''); ?></td>
                    <td><?= trunc3($row['annual_balance'] ?? 0); ?></td>
                    <td><?= trunc3($row['sick_balance'] ?? 0); ?></td>
                    <td><?= (int)($row['force_balance'] ?? 0); ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>

    <?php elseif ($reportType === 'leave_card' && $employeeFilter): ?>
        <div class="ui-card">
            <h3>
                Leave Card - Complete Transaction History for
                <?= safe_h($currentEmp ? (($currentEmp['first_name'] ?? '') . ' ' . ($currentEmp['last_name'] ?? '')) : 'Unknown Employee'); ?>
            </h3>
            <p style="font-size:12px;color:#666;">
                Shows all transactions by date: budget history changes + leave requests (history respects trans_date when available).
            </p>

            <table>
                <tr style="background-color:#e0e0e0;font-weight:bold;">
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
                <?php foreach($reportData as $row): ?>
                <tr>
                    <td><?= safe_h(app_format_date($row['date'] ?? '')); ?></td>
                    <td><?= safe_h($row['particulars'] ?? ''); ?></td>
                    <td><?= ((($row['vac_earned'] ?? 0) != 0) ? trunc3($row['vac_earned']) : ''); ?></td>
                    <td><?= ((($row['vac_deducted'] ?? 0) != 0) ? trunc3($row['vac_deducted']) : ''); ?></td>
                    <?php $vb = $row['vac_balance'] ?? ''; ?>
                    <td style="background-color:<?= ($vb !== '' && (float)$vb < 0 ? '#ffcccc' : '#ccffcc'); ?>;">
                        <?= ($vb === '' ? '' : trunc3($vb)); ?>
                    </td>
                    <td><?= ((($row['sick_earned'] ?? 0) != 0) ? trunc3($row['sick_earned']) : ''); ?></td>
                    <td><?= ((($row['sick_deducted'] ?? 0) != 0) ? trunc3($row['sick_deducted']) : ''); ?></td>
                    <?php $sb = $row['sick_balance'] ?? ''; ?>
                    <td style="background-color:<?= ($sb !== '' && (float)$sb < 0 ? '#ffcccc' : '#ccffcc'); ?>;">
                        <?= ($sb === '' ? '' : trunc3($sb)); ?>
                    </td>
                    <td><?= safe_h($row['status'] ?? ''); ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>

    <?php elseif ($reportType === 'usage'): ?>
        <div class="ui-card">
            <table>
                <tr>
                    <th>Department</th>
                    <th>Leave Type</th>
                    <th>Request Count</th>
                    <th>Total Days</th>
                </tr>
                <?php foreach ($reportData as $row): ?>
                <tr>
                    <td><?= safe_h($row['department'] ?? ''); ?></td>
                    <td><?= safe_h($row['leave_type'] ?? ''); ?></td>
                    <td><?= (int)($row['count'] ?? 0); ?></td>
                    <td><?= number_format((float)($row['total_days'] ?? 0), 3); ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php endif; ?>

</div>

</body>
</html>
