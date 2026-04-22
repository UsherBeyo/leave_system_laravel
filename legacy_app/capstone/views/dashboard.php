<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../config/database.php';
require_once '../helpers/Auth.php';
Auth::requireLogin('login.php');
require_once '../helpers/DateHelper.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$db = (new Database())->connect();
$role = (string)($_SESSION['role'] ?? '');
$userId = (int)($_SESSION['user_id'] ?? 0);
$sessionEmpId = (int)($_SESSION['emp_id'] ?? 0);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function safe_h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function status_badge(string $status, ?string $workflow = null): string {
    $status = strtolower(trim($status));
    $workflow = strtolower(trim((string)$workflow));
    if ($workflow === 'pending_personnel') return '<span class="badge badge-pending">Pending Personnel</span>';
    if ($workflow === 'pending_department_head') return '<span class="badge badge-pending">Pending Dept Head</span>';
    if ($workflow === 'finalized' || $status === 'approved') return '<span class="badge badge-approved">Approved</span>';
    if (str_contains($workflow, 'rejected') || $status === 'rejected') return '<span class="badge badge-rejected">Rejected</span>';
    return '<span class="badge badge-pending">' . safe_h(ucfirst($status ?: 'pending')) . '</span>';
}

function fmt_days($value): string {
    return number_format((float)$value, 3);
}

function placeholder_sql(int $count): string {
    return implode(',', array_fill(0, $count, '?'));
}

function render_balance_metric(string $label, $value, string $sub = 'Your current leave balance'): string {
    return '<div class="dashboard-metric"><div class="metric-label">' . safe_h($label) . '</div><div class="metric-value">' . fmt_days($value) . '</div><div class="metric-sub">' . safe_h($sub) . '</div></div>';
}

$employeeRow = null;
if ($sessionEmpId > 0) {
    $stmt = $db->prepare('SELECT * FROM employees WHERE id = ? LIMIT 1');
    $stmt->execute([$sessionEmpId]);
    $employeeRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
if (!$employeeRow) {
    $stmt = $db->prepare('SELECT * FROM employees WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $employeeRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$userName = trim((string)($employeeRow['first_name'] ?? '') . ' ' . (string)($employeeRow['last_name'] ?? ''));
if ($userName === '') {
    $userName = (string)($_SESSION['email'] ?? 'User');
}

$today = date('Y-m-d');
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');
$yearStart = date('Y-01-01');
$yearEnd = date('Y-12-31');

$employeeDashboard = [];
$departmentHeadDashboard = [];
$personnelDashboard = [];
$managerHrDashboard = [];
$adminDashboard = [];

if ($role === 'employee' && $employeeRow) {
    $annual = (float)($employeeRow['annual_balance'] ?? 0);
    $sick = (float)($employeeRow['sick_balance'] ?? 0);
    $force = (float)($employeeRow['force_balance'] ?? 0);

    $monthlyUsageStmt = $db->prepare("SELECT leave_type, action, old_balance, new_balance
        FROM budget_history
        WHERE employee_id = ?
          AND COALESCE(trans_date, DATE(created_at)) BETWEEN ? AND ?
          AND action LIKE 'deduction%'");
    $monthlyUsageStmt->execute([(int)$employeeRow['id'], $monthStart, $monthEnd]);
    $annualUsedThisMonth = 0.0;
    $sickUsedThisMonth = 0.0;
    foreach ($monthlyUsageStmt->fetchAll(PDO::FETCH_ASSOC) as $usageRow) {
        $leaveTypeRaw = strtolower(trim((string)($usageRow['leave_type'] ?? '')));
        $delta = max(0.0, (float)($usageRow['old_balance'] ?? 0) - (float)($usageRow['new_balance'] ?? 0));
        if ($delta <= 0) {
            continue;
        }

        if (str_contains($leaveTypeRaw, 'sick')) {
            $sickUsedThisMonth += $delta;
            continue;
        }

        if (str_contains($leaveTypeRaw, 'force') || str_contains($leaveTypeRaw, 'mandatory')) {
            continue;
        }

        $annualUsedThisMonth += $delta;
    }

    $forceUsageStmt = $db->prepare("SELECT leave_type, old_balance, new_balance
        FROM budget_history
        WHERE employee_id = ?
          AND COALESCE(trans_date, DATE(created_at)) BETWEEN ? AND ?
          AND action LIKE 'deduction%'");
    $forceUsageStmt->execute([(int)$employeeRow['id'], $yearStart, $yearEnd]);
    $forceUsedThisYear = 0.0;
    foreach ($forceUsageStmt->fetchAll(PDO::FETCH_ASSOC) as $usageRow) {
        $leaveTypeRaw = strtolower(trim((string)($usageRow['leave_type'] ?? '')));
        $delta = max(0.0, (float)($usageRow['old_balance'] ?? 0) - (float)($usageRow['new_balance'] ?? 0));
        if ($delta <= 0) {
            continue;
        }
        if (str_contains($leaveTypeRaw, 'force') || str_contains($leaveTypeRaw, 'mandatory')) {
            $forceUsedThisYear += $delta;
        }
    }

    $requestStmt = $db->prepare("SELECT lr.*, COALESCE(lt.name, lr.leave_type) AS leave_type_name
        FROM leave_requests lr
        LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id
        WHERE lr.employee_id = ?
        ORDER BY COALESCE(lr.start_date, DATE(lr.created_at)) DESC, lr.id DESC");
    $requestStmt->execute([(int)$employeeRow['id']]);
    $ownRequests = $requestStmt->fetchAll(PDO::FETCH_ASSOC);

    $pendingRequests = array_values(array_filter($ownRequests, fn($r) => strtolower((string)$r['status']) === 'pending'));
    $approvedThisMonth = 0;
    $upcomingLeaves = [];
    foreach ($ownRequests as $row) {
        $status = strtolower((string)($row['status'] ?? ''));
        if ($status === 'approved' && !empty($row['start_date']) && $row['start_date'] >= $monthStart && $row['start_date'] <= $monthEnd) {
            $approvedThisMonth++;
        }
        if (count($upcomingLeaves) < 5 && !empty($row['start_date']) && $row['start_date'] >= $today && in_array($status, ['approved', 'pending'], true)) {
            $upcomingLeaves[] = $row;
        }
    }

    $employeeDashboard = [
        'annual' => $annual,
        'sick' => $sick,
        'force' => $force,
        'annual_used_this_month' => round($annualUsedThisMonth, 3),
        'sick_used_this_month' => round($sickUsedThisMonth, 3),
        'force_used_this_year' => round($forceUsedThisYear, 3),
        'pending_count' => count($pendingRequests),
        'approved_this_month' => $approvedThisMonth,
        'upcoming_count' => count($upcomingLeaves),
        'pending_requests' => array_slice($pendingRequests, 0, 6),
        'recent_requests' => array_slice($ownRequests, 0, 6),
    ];
}

if ($role === 'department_head') {
    $deptStmt = $db->prepare("SELECT dha.department_id
        FROM department_head_assignments dha
        JOIN employees e ON e.id = dha.employee_id
        WHERE e.user_id = ? AND dha.is_active = 1");
    $deptStmt->execute([$userId]);
    $deptIds = array_map('intval', $deptStmt->fetchAll(PDO::FETCH_COLUMN));
    if (empty($deptIds) && !empty($employeeRow['department_id'])) {
        $deptIds = [(int)$employeeRow['department_id']];
    }
    $deptStmt->execute([$userId]);
    $deptIds = array_map('intval', $deptStmt->fetchAll(PDO::FETCH_COLUMN));
    if (empty($deptIds) && !empty($employeeRow['department_id'])) {
        $deptIds = [(int)$employeeRow['department_id']];
    }

    if (!empty($deptIds)) {
        $in = placeholder_sql(count($deptIds));

        $countStmt = $db->prepare("SELECT COUNT(*) FROM leave_requests WHERE workflow_status = 'pending_department_head' AND status = 'pending' AND department_id IN ($in)");
        $countStmt->execute($deptIds);
        $pendingCount = (int)$countStmt->fetchColumn();

        $monthParams = array_merge([$monthStart, $monthEnd], $deptIds);
        $approvedStmt = $db->prepare("SELECT COUNT(*) FROM leave_requests WHERE department_head_approved_at IS NOT NULL AND DATE(department_head_approved_at) BETWEEN ? AND ? AND department_id IN ($in)");
        $approvedStmt->execute($monthParams);
        $approvedThisMonth = (int)$approvedStmt->fetchColumn();

        $returnedStmt = $db->prepare("SELECT COUNT(*) FROM leave_requests WHERE workflow_status IN ('returned_by_personnel','rejected_department_head') AND department_id IN ($in)");
        $returnedStmt->execute($deptIds);
        $returnedCount = (int)$returnedStmt->fetchColumn();

        $upcomingStmt = $db->prepare("SELECT COUNT(*) FROM leave_requests WHERE status IN ('pending','approved') AND start_date >= ? AND department_id IN ($in)");
        $upcomingStmt->execute(array_merge([$today], $deptIds));
        $upcomingCount = (int)$upcomingStmt->fetchColumn();

        $reviewStmt = $db->prepare("SELECT lr.*, e.first_name, e.last_name, e.department, COALESCE(lt.name, lr.leave_type) AS leave_type_name
            FROM leave_requests lr
            JOIN employees e ON e.id = lr.employee_id
            LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id
            WHERE lr.workflow_status = 'pending_department_head' AND lr.status = 'pending' AND lr.department_id IN ($in)
            ORDER BY lr.start_date ASC, lr.id ASC
            LIMIT 8");
        $reviewStmt->execute($deptIds);
        $pendingRows = $reviewStmt->fetchAll(PDO::FETCH_ASSOC);

        $upcomingRowsStmt = $db->prepare("SELECT lr.*, e.first_name, e.last_name, e.department, COALESCE(lt.name, lr.leave_type) AS leave_type_name
            FROM leave_requests lr
            JOIN employees e ON e.id = lr.employee_id
            LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id
            WHERE lr.status IN ('pending','approved') AND lr.start_date >= ? AND lr.department_id IN ($in)
            ORDER BY lr.start_date ASC, lr.id ASC
            LIMIT 8");
        $upcomingRowsStmt->execute(array_merge([$today], $deptIds));
        $upcomingRows = $upcomingRowsStmt->fetchAll(PDO::FETCH_ASSOC);

        $recentStmt = $db->prepare("SELECT lr.*, e.first_name, e.last_name, e.department, COALESCE(lt.name, lr.leave_type) AS leave_type_name
            FROM leave_requests lr
            JOIN employees e ON e.id = lr.employee_id
            LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id
            WHERE lr.department_head_user_id = ? AND lr.department_head_approved_at IS NOT NULL
            ORDER BY lr.department_head_approved_at DESC, lr.id DESC
            LIMIT 6");
        $recentStmt->execute([$userId]);
        $recentRows = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

        $departmentHeadDashboard = [
            'pending_count' => $pendingCount,
            'approved_this_month' => $approvedThisMonth,
            'returned_count' => $returnedCount,
            'upcoming_count' => $upcomingCount,
            'pending_rows' => $pendingRows,
            'upcoming_rows' => $upcomingRows,
            'recent_rows' => $recentRows,
        ];
    }
}

if ($role === 'personnel') {
    $pendingStmt = $db->query("SELECT COUNT(*) FROM leave_requests WHERE workflow_status = 'pending_personnel' AND status = 'pending'");
    $pendingCount = (int)$pendingStmt->fetchColumn();

    $printStmt = $db->query("SELECT COUNT(*) FROM leave_requests WHERE workflow_status = 'finalized' AND COALESCE(print_status, '') = 'pending_print'");
    $printQueueCount = (int)$printStmt->fetchColumn();

    $reviewedStmt = $db->prepare("SELECT COUNT(*) FROM leave_requests WHERE personnel_checked_at IS NOT NULL AND DATE(personnel_checked_at) BETWEEN ? AND ?");
    $reviewedStmt->execute([$monthStart, $monthEnd]);
    $reviewedThisMonth = (int)$reviewedStmt->fetchColumn();

    $upcomingStmt = $db->prepare("SELECT COUNT(*) FROM leave_requests WHERE status = 'approved' AND start_date >= ?");
    $upcomingStmt->execute([$today]);
    $upcomingCount = (int)$upcomingStmt->fetchColumn();

    $pendingRowsStmt = $db->query("SELECT lr.*, e.first_name, e.last_name, e.department, e.annual_balance, e.sick_balance, e.force_balance,
        COALESCE(lt.name, lr.leave_type) AS leave_type_name
        FROM leave_requests lr
        JOIN employees e ON e.id = lr.employee_id
        LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id
        WHERE lr.workflow_status = 'pending_personnel' AND lr.status = 'pending'
        ORDER BY lr.start_date ASC, lr.id ASC
        LIMIT 8");
    $pendingRows = $pendingRowsStmt->fetchAll(PDO::FETCH_ASSOC);

    $printQueueRowsStmt = $db->query("SELECT lr.*, e.first_name, e.last_name, e.department, COALESCE(lt.name, lr.leave_type) AS leave_type_name
        FROM leave_requests lr
        JOIN employees e ON e.id = lr.employee_id
        LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id
        WHERE lr.workflow_status = 'finalized' AND COALESCE(lr.print_status, '') = 'pending_print'
        ORDER BY COALESCE(lr.finalized_at, lr.personnel_checked_at, lr.created_at) DESC, lr.id DESC
        LIMIT 8");
    $printQueueRows = $printQueueRowsStmt->fetchAll(PDO::FETCH_ASSOC);

    $upcomingRowsStmt = $db->prepare("SELECT lr.*, e.first_name, e.last_name, e.department, COALESCE(lt.name, lr.leave_type) AS leave_type_name
        FROM leave_requests lr
        JOIN employees e ON e.id = lr.employee_id
        LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id
        WHERE lr.status = 'approved' AND lr.start_date >= ?
        ORDER BY lr.start_date ASC, lr.id ASC
        LIMIT 6");
    $upcomingRowsStmt->execute([$today]);
    $upcomingRows = $upcomingRowsStmt->fetchAll(PDO::FETCH_ASSOC);

    $personnelDashboard = [
        'pending_count' => $pendingCount,
        'print_queue_count' => $printQueueCount,
        'reviewed_this_month' => $reviewedThisMonth,
        'upcoming_count' => $upcomingCount,
        'pending_rows' => $pendingRows,
        'print_queue_rows' => $printQueueRows,
        'upcoming_rows' => $upcomingRows,
    ];
}

if (in_array($role, ['manager', 'hr'], true)) {
    if ($role === 'manager' && $sessionEmpId > 0) {
        $countStmt = $db->prepare("SELECT COUNT(*) FROM leave_requests lr JOIN employees e ON e.id = lr.employee_id WHERE lr.status = 'pending' AND e.manager_id = ?");
        $countStmt->execute([$sessionEmpId]);
        $pendingCount = (int)$countStmt->fetchColumn();

        $requestStmt = $db->prepare("SELECT lr.*, e.first_name, e.last_name, e.department, COALESCE(lt.name, lr.leave_type) AS leave_type_name
            FROM leave_requests lr
            JOIN employees e ON e.id = lr.employee_id
            LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id
            WHERE lr.status = 'pending' AND e.manager_id = ?
            ORDER BY lr.start_date ASC, lr.id ASC
            LIMIT 8");
        $requestStmt->execute([$sessionEmpId]);
        $pendingRows = $requestStmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $pendingCount = (int)$db->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'pending'")->fetchColumn();
        $requestStmt = $db->query("SELECT lr.*, e.first_name, e.last_name, e.department, COALESCE(lt.name, lr.leave_type) AS leave_type_name
            FROM leave_requests lr
            JOIN employees e ON e.id = lr.employee_id
            LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id
            WHERE lr.status = 'pending'
            ORDER BY lr.start_date ASC, lr.id ASC
            LIMIT 8");
        $pendingRows = $requestStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $approvedThisMonth = (int)$db->prepare("SELECT COUNT(*) FROM leave_requests WHERE status = 'approved' AND start_date BETWEEN ? AND ?");
    $stmtTmp = $db->prepare("SELECT COUNT(*) FROM leave_requests WHERE status = 'approved' AND start_date BETWEEN ? AND ?");
    $stmtTmp->execute([$monthStart, $monthEnd]);
    $approvedThisMonth = (int)$stmtTmp->fetchColumn();

    $mostAbsent = $db->query("SELECT employee_id, COUNT(*) AS cnt FROM leave_requests WHERE status='approved' GROUP BY employee_id ORDER BY cnt DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $mostAbsentName = '';
    if ($mostAbsent) {
        $stmt = $db->prepare('SELECT first_name, last_name FROM employees WHERE id = ?');
        $stmt->execute([(int)$mostAbsent['employee_id']]);
        $emp = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($emp) {
            $mostAbsentName = trim(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? ''));
        }
    }

    $monthlyStmt = $db->query("SELECT MONTH(start_date) AS m, COUNT(*) AS cnt FROM leave_requests WHERE status='approved' GROUP BY MONTH(start_date) ORDER BY MONTH(start_date)");
    $monthlyData = $monthlyStmt->fetchAll(PDO::FETCH_ASSOC);
    $deptChartStmt = $db->query("SELECT department, COUNT(*) AS cnt FROM employees GROUP BY department ORDER BY department");
    $deptChartData = $deptChartStmt->fetchAll(PDO::FETCH_ASSOC);

    $managerHrDashboard = [
        'pending_count' => $pendingCount,
        'approved_this_month' => $approvedThisMonth,
        'most_absent_name' => $mostAbsentName,
        'most_absent_count' => (int)($mostAbsent['cnt'] ?? 0),
        'monthly_data' => $monthlyData,
        'dept_chart_data' => $deptChartData,
        'pending_rows' => $pendingRows,
    ];
}

if ($role === 'admin') {
    $totalEmployees = (int)$db->query('SELECT COUNT(*) FROM employees')->fetchColumn();
    $pendingCount = (int)$db->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'pending'")->fetchColumn();
    $approvedCount = (int)$db->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'approved'")->fetchColumn();
    $finalizedPrintQueue = (int)$db->query("SELECT COUNT(*) FROM leave_requests WHERE workflow_status = 'finalized' AND COALESCE(print_status, '') = 'pending_print'")->fetchColumn();

    $deptData = $db->query("SELECT department, COUNT(*) AS cnt FROM employees GROUP BY department ORDER BY department")->fetchAll(PDO::FETCH_ASSOC);
    $roleData = $db->query("SELECT role, COUNT(*) AS cnt FROM users GROUP BY role ORDER BY role")->fetchAll(PDO::FETCH_ASSOC);
    $recentUsers = $db->query("SELECT e.first_name, e.last_name, u.role, e.department FROM users u JOIN employees e ON e.user_id = u.id ORDER BY e.id DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);

    $adminDashboard = [
        'total_employees' => $totalEmployees,
        'pending_count' => $pendingCount,
        'approved_count' => $approvedCount,
        'print_queue_count' => $finalizedPrintQueue,
        'dept_data' => $deptData,
        'role_data' => $roleData,
        'recent_users' => $recentUsers,
    ];
}

$monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
?>
<!DOCTYPE html>
<html>
<head>
    <base href="<?= htmlspecialchars(rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/', ENT_QUOTES, 'UTF-8'); ?>">
    <title>Dashboard</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../assets/js/script.js"></script>
    <style>
        .dashboard-hero {
            display:grid;
            grid-template-columns: minmax(0, 1.6fr) minmax(280px, 0.9fr);
            gap: 20px;
            align-items: stretch;
            margin-bottom: 24px;
        }
        .dashboard-intro {
            background: #ffffff;
            border: 1px solid rgba(37,99,235,0.16);
            box-shadow: 0 18px 36px rgba(37,99,235,.08);
        }
        .dashboard-intro h3 { margin: 0 0 10px; font-size: 26px; }
        .dashboard-intro p { margin: 0; color: var(--muted); line-height: 1.65; }
        .dashboard-side-note {
            display:flex; flex-direction:column; justify-content:center; gap:10px;
            background: #ffffff;
            color: #ffffff;
            box-shadow: 0 18px 40px rgba(37,99,235,.20);
        }
        .dashboard-side-note .mini-label { font-size: 12px; text-transform: uppercase; letter-spacing: .08em; color: rgba(0, 0, 0, 0.78); }
        .dashboard-side-note .mini-value { font-size: 30px; font-weight: 800; color: #000000; }
        .dashboard-metrics {
            display:grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .dashboard-metric {
            padding: 18px;
            border-radius: 18px;
            border: 1px solid var(--border);
            background: linear-gradient(180deg, #fff, #fbfdff);
            box-shadow: 0 12px 28px rgba(15,23,42,.06);
        }
        .dashboard-metric .metric-label { font-size: 13px; color: var(--muted); margin-bottom: 8px; }
        .dashboard-metric .metric-value { font-size: 30px; font-weight: 700; color: var(--text); line-height: 1; }
        .dashboard-metric .metric-sub { margin-top: 10px; font-size: 13px; color: var(--muted); }
        .dashboard-metric:nth-child(1) { background: #ffffff; }
        .dashboard-metric:nth-child(2) { background: #ffffff; }
        .dashboard-metric:nth-child(3) { background: #ffffff; }
        .dashboard-metric:nth-child(4) { background: #ffffff; }
        .dashboard-grid {
            display:grid;
            grid-template-columns: minmax(0, 1.2fr) minmax(320px, 0.8fr);
            gap: 20px;
        }
        .dashboard-panel-title {
            display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:14px;
            padding-bottom: 6px; border-bottom: 1px solid rgba(148,163,184,0.2);
        }
        .dashboard-panel-title h3, .dashboard-panel-title h4 { margin: 0; }
        .ui-card.table-card, .ui-card {
            padding: 20px;
        }
        .dashboard-table-note { margin: 0 0 14px; font-size: 13px; color: var(--muted); }
        .dashboard-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    text-decoration: none;
    font-weight: 600;
    color: #fff;
    background: var(--primary);
    border: 1px solid var(--primary);
    padding: 10px 14px;
    border-radius: 12px;
    transition: all .18s ease;
    box-shadow: 0 8px 18px rgba(37,99,235,.16);
}

.dashboard-link:hover {
    background: #1d4ed8;
    border-color: #1d4ed8;
    color: #fff;
    transform: translateY(-1px);
    box-shadow: 0 10px 22px rgba(29,78,216,.22);
}

.dashboard-link:active {
    transform: translateY(0);
}

.dashboard-link:focus-visible {
    outline: 3px solid rgba(37,99,235,.22);
    outline-offset: 2px;
}
        .dashboard-list {
            display:grid; gap: 12px;
        }
        .dashboard-list-item {
            padding: 14px 16px; border-radius: 14px; border:1px solid var(--border); background:linear-gradient(180deg, #fff, #fcfdff); box-shadow: 0 8px 18px rgba(15,23,42,.04);
        }
        .dashboard-list-item strong { color: var(--text); }
        .dashboard-list-item .meta { margin-top: 6px; color: var(--muted); font-size: 13px; }
        .dashboard-chart-card canvas { width: 100% !important; max-height: 320px; }
        .dashboard-empty {
            text-align:center; padding:32px 16px; color: var(--muted); border:1px dashed var(--border); border-radius:16px;
        }
        .balance-pill-row { display:flex; gap:10px; flex-wrap:wrap; margin-top:10px; }
        .balance-pill {
            display:inline-flex; align-items:center; gap:8px; padding:8px 12px; border-radius:999px; background:#eff6ff; color:#1d4ed8; font-size:13px; font-weight:600;
        }
        .kpi-chip-row { display:flex; gap:10px; flex-wrap:wrap; margin-top:16px; }
        .kpi-chip {
            padding:10px 14px; border-radius:14px; background:#f8fafc; border:1px solid var(--border); font-size:13px; color: var(--text);
        }
        .dashboard-table-note { color: var(--muted); font-size: 13px; margin-top: -4px; margin-bottom: 12px; }
        @media (max-width: 1100px) {
            .dashboard-hero, .dashboard-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/partials/sidebar.php'; ?>
<div class="app-main">
    <?php
    $title = 'Dashboard';
    $subtitle = 'Welcome back, ' . safe_h($userName);
    $actions = [];
    if (in_array($role, ['department_head','personnel','manager','hr','admin'], true)) {
        $actions[] = '<a href="leave_requests.php" class="dashboard-link">Open Leave Requests</a>';
    }
    if ($role === 'employee') {
        $actions[] = '<a href="apply_leave.php" class="btn btn-primary">Apply Leave</a>';
    }
    include __DIR__ . '/partials/ui/page-header.php';
    ?>

    <div class="dashboard-hero">
        <div class="ui-card dashboard-intro">
            <h3><?= safe_h(match($role) {
                'employee' => 'Your leave overview is ready.',
                'department_head' => 'Department approvals at a glance.',
                'personnel' => 'Personnel review and print queue overview.',
                'manager' => 'Manager review dashboard.',
                'hr' => 'HR operational overview.',
                'admin' => 'System-wide control center.',
                default => 'Dashboard overview.'
            }); ?></h3>
            <p><?= safe_h(match($role) {
                'employee' => 'Track your balances, monitor pending requests, and keep an eye on upcoming approved leaves from one place.',
                'department_head' => 'See what needs your decision, monitor upcoming team leaves, and keep your department workflow moving.',
                'personnel' => 'Review final-stage requests, monitor the print queue, and watch the approved leave pipeline in real time.',
                'manager' => 'Review pending leave requests and monitor leave trends across your assigned team.',
                'hr' => 'Monitor overall leave demand, department distribution, and pending review workload.',
                'admin' => 'See the system health, employee distribution, and leave operations in one consolidated dashboard.',
                default => 'Your dashboard is ready.'
            }); ?></p>
            <div class="kpi-chip-row">
                <div class="kpi-chip">Today: <strong><?= safe_h(app_format_date($today)); ?></strong></div>
                <div class="kpi-chip">Role: <strong><?= safe_h(ucwords(str_replace('_', ' ', $role))); ?></strong></div>
                <?php if (!empty($employeeRow['department'])): ?>
                    <div class="kpi-chip">Department: <strong><?= safe_h($employeeRow['department']); ?></strong></div>
                <?php endif; ?>
            </div>
        </div>
        <div class="ui-card dashboard-side-note">
            <div class="mini-label">This month</div>
            <div class="mini-value"><?= safe_h(date('F Y')); ?></div>
            <div class="mini-label">Quick path</div>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <?php if ($role === 'employee'): ?>
                    <a href="employee_profile.php" class="btn btn-secondary">View Profile</a>
                <?php else: ?>
                    <a href="leave_requests.php" class="btn btn-secondary">Review Requests</a>
                <?php endif; ?>
                <a href="calendar.php" class="btn btn-ghost">Calendar</a>
            </div>
        </div>
    </div>

    <?php if ($role === 'employee' && !empty($employeeDashboard)): ?>
        <div class="dashboard-metrics">
            <div class="dashboard-metric"><div class="metric-label">Pending Requests</div><div class="metric-value"><?= (int)$employeeDashboard['pending_count']; ?></div><div class="metric-sub">Requests still moving through workflow</div></div>
            <div class="dashboard-metric"><div class="metric-label">Approved This Month</div><div class="metric-value"><?= (int)$employeeDashboard['approved_this_month']; ?></div><div class="metric-sub">Approved leave applications this month</div></div>
            <div class="dashboard-metric"><div class="metric-label">Upcoming Leave Entries</div><div class="metric-value"><?= (int)$employeeDashboard['upcoming_count']; ?></div><div class="metric-sub">Pending or approved leaves from today onward</div></div>
        </div>

        <div class="ui-card dashboard-chart-card">
            <div class="dashboard-panel-title"><h3>Leave Usage vs Remaining</h3></div>
            <div style="display:flex;gap:16px;flex-wrap:wrap;justify-content:center;">
                <div style="flex:1;min-width:240px;max-width:320px;height:240px;"><canvas id="annualChart"></canvas></div>
                <div style="flex:1;min-width:240px;max-width:320px;height:240px;"><canvas id="sickChart"></canvas></div>
                <div style="flex:1;min-width:240px;max-width:320px;height:240px;"><canvas id="forceChart"></canvas></div>
            </div>
        </div>

        <div class="dashboard-grid">
            <div class="ui-card table-card">
                <div class="dashboard-panel-title"><h3>Recent Requests</h3><a class="dashboard-link" href="employee_profile.php">Open full profile</a></div>
                <div class="table-wrap">
                    <table class="ui-table">
                        <thead><tr><th>Type</th><th>Dates</th><th>Days</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php if (empty($employeeDashboard['recent_requests'])): ?>
                            <tr><td colspan="4" class="table-empty">No leave requests submitted yet.</td></tr>
                        <?php else: foreach ($employeeDashboard['recent_requests'] as $r): ?>
                            <tr>
                                <td><?= safe_h($r['leave_type_name'] ?? $r['leave_type'] ?? ''); ?></td>
                                <td><?= safe_h(app_format_date_range($r['start_date'] ?? '', $r['end_date'] ?? '')); ?></td>
                                <td><?= fmt_days($r['total_days'] ?? 0); ?></td>
                                <td><?= status_badge((string)($r['status'] ?? ''), (string)($r['workflow_status'] ?? '')); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="ui-card">
                <div class="dashboard-panel-title"><h3>Pending Snapshot</h3></div>
                <?php if (empty($employeeDashboard['pending_requests'])): ?>
                    <div class="dashboard-empty">You have no pending requests right now.</div>
                <?php else: ?>
                    <div class="dashboard-list">
                        <?php foreach ($employeeDashboard['pending_requests'] as $r): ?>
                            <div class="dashboard-list-item">
                                <strong><?= safe_h($r['leave_type_name'] ?? $r['leave_type'] ?? ''); ?></strong>
                                <div class="meta"><?= safe_h(app_format_date_range($r['start_date'] ?? '', $r['end_date'] ?? '')); ?> · <?= fmt_days($r['total_days'] ?? 0); ?> day(s)</div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            var textColor = getComputedStyle(document.documentElement).getPropertyValue('--text').trim() || '#111827';
            function makeDoughnut(id, title, usedThisMonth, remaining, colors) {
                var el = document.getElementById(id);
                if (!el) return;
                var used = Math.max(0, parseFloat(usedThisMonth || 0));
                var remainingValue = Math.max(0, parseFloat(remaining || 0));
                new Chart(el.getContext('2d'), {
                    type: 'doughnut',
                    data: { labels: ['Used this month', 'Remaining'], datasets: [{ data: [used, remainingValue], backgroundColor: colors }] },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom', labels: { color: textColor } },
                            title: { display: true, text: title, color: textColor },
                            tooltip: { callbacks: { label: function(context){ return context.label + ': ' + Number(context.parsed || 0).toFixed(3) + ' days'; } } }
                        }
                    }
                });
            }
            makeDoughnut('annualChart', 'Vacational Leave', <?= json_encode((float)($employeeDashboard['annual_used_this_month'] ?? 0)); ?>, <?= json_encode((float)$employeeDashboard['annual']); ?>, ['#fda4af', '#2563eb']);
            makeDoughnut('sickChart', 'Sick Leave', <?= json_encode((float)($employeeDashboard['sick_used_this_month'] ?? 0)); ?>, <?= json_encode((float)$employeeDashboard['sick']); ?>, ['#fde68a', '#16a34a']);
            makeDoughnut('forceChart', 'Force Leave (Used This Year)', <?= json_encode((float)($employeeDashboard['force_used_this_year'] ?? 0)); ?>, <?= json_encode((float)$employeeDashboard['force']); ?>, ['#fca5a5', '#0f766e']);
        });
        </script>

    <?php elseif ($role === 'department_head'): ?>
        <?php if ($employeeRow): ?>
        <div class="dashboard-metrics">
            <?= render_balance_metric('Your Vacational Balance', (float)($employeeRow['annual_balance'] ?? 0)); ?>
            <?= render_balance_metric('Your Sick Balance', (float)($employeeRow['sick_balance'] ?? 0)); ?>
            <?= render_balance_metric('Your Force Balance', (float)($employeeRow['force_balance'] ?? 0)); ?>
        </div>
        <?php endif; ?>
        <div class="dashboard-metrics">
            <div class="dashboard-metric"><div class="metric-label">Awaiting Your Review</div><div class="metric-value"><?= (int)($departmentHeadDashboard['pending_count'] ?? 0); ?></div><div class="metric-sub">Pending department-head approvals</div></div>
            <div class="dashboard-metric"><div class="metric-label">Approved This Month</div><div class="metric-value"><?= (int)($departmentHeadDashboard['approved_this_month'] ?? 0); ?></div><div class="metric-sub">Requests forwarded to personnel</div></div>
            <div class="dashboard-metric"><div class="metric-label">Returned / Rejected</div><div class="metric-value"><?= (int)($departmentHeadDashboard['returned_count'] ?? 0); ?></div><div class="metric-sub">Requests needing follow-up attention</div></div>
            <div class="dashboard-metric"><div class="metric-label">Upcoming Team Leaves</div><div class="metric-value"><?= (int)($departmentHeadDashboard['upcoming_count'] ?? 0); ?></div><div class="metric-sub">Upcoming pending or approved leaves</div></div>
        </div>

        <div class="dashboard-grid">
            <div class="ui-card table-card">
                <div class="dashboard-panel-title"><h3>Requests Awaiting Your Decision</h3><a href="leave_requests.php" class="dashboard-link">Open full review queue</a></div>
                <p class="dashboard-table-note">Review the high-priority requests below or open the full Leave Requests page for complete actions.</p>
                <div class="table-wrap">
                    <table class="ui-table">
                        <thead><tr><th>Employee</th><th>Type</th><th>Dates</th><th>Days</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php if (empty($departmentHeadDashboard['pending_rows'])): ?>
                            <tr><td colspan="5" class="table-empty">No requests are waiting for your department-head review.</td></tr>
                        <?php else: foreach ($departmentHeadDashboard['pending_rows'] as $r): ?>
                            <tr>
                                <td><?= safe_h(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')); ?></td>
                                <td><?= safe_h($r['leave_type_name'] ?? $r['leave_type'] ?? ''); ?></td>
                                <td><?= safe_h(app_format_date_range($r['start_date'] ?? '', $r['end_date'] ?? '')); ?></td>
                                <td><?= fmt_days($r['total_days'] ?? 0); ?></td>
                                <td><?= status_badge((string)($r['status'] ?? ''), (string)($r['workflow_status'] ?? '')); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="ui-card collapsible-card">
                <div class="collapsible-header">
                    <h3 class="collapsible-title">Upcoming Team Leaves</h3>
                    <button type="button" class="collapsible-toggle" aria-expanded="true">▾</button>
                </div>
                <div class="collapsible-body expanded">
                    <?php if (empty($departmentHeadDashboard['upcoming_rows'])): ?>
                        <div class="dashboard-empty">No upcoming team leave entries found.</div>
                    <?php else: ?>
                        <div class="dashboard-list scrollable-section">
                            <?php foreach ($departmentHeadDashboard['upcoming_rows'] as $r): ?>
                                <div class="dashboard-list-item">
                                    <strong><?= safe_h(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')); ?></strong>
                                    <div class="meta"><?= safe_h($r['leave_type_name'] ?? $r['leave_type'] ?? ''); ?> · <?= safe_h(app_format_date_range($r['start_date'] ?? '', $r['end_date'] ?? '')); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="ui-card table-card">
            <div class="dashboard-panel-title"><h3>Recent Department Decisions</h3></div>
            <div class="table-wrap">
                <table class="ui-table">
                    <thead><tr><th>Employee</th><th>Type</th><th>Decision Date</th><th>Workflow</th></tr></thead>
                    <tbody>
                    <?php if (empty($departmentHeadDashboard['recent_rows'])): ?>
                        <tr><td colspan="4" class="table-empty">No recent department-head decisions yet.</td></tr>
                    <?php else: foreach ($departmentHeadDashboard['recent_rows'] as $r): ?>
                        <tr>
                            <td><?= safe_h(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')); ?></td>
                            <td><?= safe_h($r['leave_type_name'] ?? $r['leave_type'] ?? ''); ?></td>
                            <td><?= safe_h(app_format_datetime($r['department_head_approved_at'] ?? '')); ?></td>
                            <td><?= status_badge((string)($r['status'] ?? ''), (string)($r['workflow_status'] ?? '')); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($role === 'personnel'): ?>
        <?php if ($employeeRow): ?>
        <div class="dashboard-metrics">
            <?= render_balance_metric('Your Vacational Balance', (float)($employeeRow['annual_balance'] ?? 0)); ?>
            <?= render_balance_metric('Your Sick Balance', (float)($employeeRow['sick_balance'] ?? 0)); ?>
            <?= render_balance_metric('Your Force Balance', (float)($employeeRow['force_balance'] ?? 0)); ?>
        </div>
        <?php endif; ?>
        <div class="dashboard-metrics">
            <div class="dashboard-metric"><div class="metric-label">Pending Personnel Review</div><div class="metric-value"><?= (int)($personnelDashboard['pending_count'] ?? 0); ?></div><div class="metric-sub">Requests waiting for final review</div></div>
            <div class="dashboard-metric"><div class="metric-label">Pending Print Queue</div><div class="metric-value"><?= (int)($personnelDashboard['print_queue_count'] ?? 0); ?></div><div class="metric-sub">Finalized requests waiting for form printing</div></div>
            <div class="dashboard-metric"><div class="metric-label">Reviewed This Month</div><div class="metric-value"><?= (int)($personnelDashboard['reviewed_this_month'] ?? 0); ?></div><div class="metric-sub">Personnel reviews completed this month</div></div>
            <div class="dashboard-metric"><div class="metric-label">Upcoming Approved Leaves</div><div class="metric-value"><?= (int)($personnelDashboard['upcoming_count'] ?? 0); ?></div><div class="metric-sub">Approved leave entries from today onward</div></div>
        </div>

        <div class="dashboard-grid">
            <div class="ui-card table-card">
                <div class="dashboard-panel-title"><h3>Pending Personnel Review</h3><a href="leave_requests.php" class="dashboard-link">Open full review queue</a></div>
                <div class="table-wrap">
                    <table class="ui-table">
                        <thead><tr><th>Employee</th><th>Type</th><th>Dates</th><th>Projected Preview</th></tr></thead>
                        <tbody>
                        <?php if (empty($personnelDashboard['pending_rows'])): ?>
                            <tr><td colspan="4" class="table-empty">No requests are waiting for personnel review.</td></tr>
                        <?php else: foreach ($personnelDashboard['pending_rows'] as $r): ?>
                            <?php
                                $typeName = strtolower(trim((string)($r['leave_type_name'] ?? $r['leave_type'] ?? '')));
                                $days = (float)($r['total_days'] ?? 0);
                                $annualBefore = (float)($r['annual_balance'] ?? 0);
                                $sickBefore = (float)($r['sick_balance'] ?? 0);
                                $forceBefore = (float)($r['force_balance'] ?? 0);
                                $annualAfter = $annualBefore;
                                $sickAfter = $sickBefore;
                                $forceAfter = $forceBefore;
                                $isForce = in_array($typeName, ['mandatory / forced leave','mandatory/forced leave','mandatory / force leave','mandatory/force leave','force','force leave','forced','forced leave','mandatory','mandatory leave'], true);
                                $isSick = in_array($typeName, ['sick','sick leave'], true);
                                if ($isForce) {
                                    $annualAfter = max(0, $annualBefore - $days);
                                    $forceAfter = max(0, $forceBefore - $days);
                                } elseif ($isSick) {
                                    $sickAfter = max(0, $sickBefore - $days);
                                } else {
                                    $annualAfter = max(0, $annualBefore - $days);
                                }
                            ?>
                            <tr>
                                <td><?= safe_h(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')); ?></td>
                                <td><?= safe_h($r['leave_type_name'] ?? $r['leave_type'] ?? ''); ?></td>
                                <td><?= safe_h(app_format_date_range($r['start_date'] ?? '', $r['end_date'] ?? '')); ?></td>
                                <td>
                                    <div class="balance-pill-row">
                                        <span class="balance-pill">Vac: <?= fmt_days($annualBefore); ?> → <?= fmt_days($annualAfter); ?></span>
                                        <span class="balance-pill">Sick: <?= fmt_days($sickBefore); ?> → <?= fmt_days($sickAfter); ?></span>
                                        <span class="balance-pill">Force: <?= fmt_days($forceBefore); ?> → <?= fmt_days($forceAfter); ?></span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="ui-card">
                <div class="dashboard-panel-title"><h3>Pending Print Queue</h3></div>
                <?php if (empty($personnelDashboard['print_queue_rows'])): ?>
                    <div class="dashboard-empty">No finalized requests waiting for print.</div>
                <?php else: ?>
                    <div class="dashboard-list">
                        <?php foreach ($personnelDashboard['print_queue_rows'] as $r): ?>
                            <div class="dashboard-list-item">
                                <strong><?= safe_h(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')); ?></strong>
                                <div class="meta"><?= safe_h($r['leave_type_name'] ?? $r['leave_type'] ?? ''); ?> · <?= safe_h(app_format_date_range($r['start_date'] ?? '', $r['end_date'] ?? '')); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="ui-card table-card">
            <div class="dashboard-panel-title"><h3>Upcoming Approved Leaves</h3></div>
            <div class="table-wrap">
                <table class="ui-table">
                    <thead><tr><th>Employee</th><th>Department</th><th>Type</th><th>Starts</th></tr></thead>
                    <tbody>
                    <?php if (empty($personnelDashboard['upcoming_rows'])): ?>
                        <tr><td colspan="4" class="table-empty">No upcoming approved leave entries.</td></tr>
                    <?php else: foreach ($personnelDashboard['upcoming_rows'] as $r): ?>
                        <tr>
                            <td><?= safe_h(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')); ?></td>
                            <td><?= safe_h($r['department'] ?? ''); ?></td>
                            <td><?= safe_h($r['leave_type_name'] ?? $r['leave_type'] ?? ''); ?></td>
                            <td><?= safe_h(app_format_date($r['start_date'] ?? '')); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif (in_array($role, ['manager', 'hr'], true)): ?>
        <div class="dashboard-metrics">
            <div class="dashboard-metric"><div class="metric-label">Pending Requests</div><div class="metric-value"><?= (int)($managerHrDashboard['pending_count'] ?? 0); ?></div><div class="metric-sub">Requests awaiting action</div></div>
            <div class="dashboard-metric"><div class="metric-label">Approved This Month</div><div class="metric-value"><?= (int)($managerHrDashboard['approved_this_month'] ?? 0); ?></div><div class="metric-sub">Approved leave records this month</div></div>
            <div class="dashboard-metric"><div class="metric-label">Most Absent Employee</div><div class="metric-value" style="font-size:22px;"><?= safe_h($managerHrDashboard['most_absent_name'] ?: '—'); ?></div><div class="metric-sub"><?= (int)($managerHrDashboard['most_absent_count'] ?? 0); ?> approved entries</div></div>
        </div>

        <div class="dashboard-grid">
            <div class="ui-card dashboard-chart-card">
                <div class="dashboard-panel-title"><h3>Monthly Approved Leave Trend</h3></div>
                <canvas id="monthlyChart"></canvas>
            </div>
            <div class="ui-card dashboard-chart-card">
                <div class="dashboard-panel-title"><h3>Employees by Department</h3></div>
                <canvas id="deptChart"></canvas>
            </div>
        </div>

        <div class="ui-card table-card">
            <div class="dashboard-panel-title"><h3>Pending Leave Requests</h3><a href="leave_requests.php" class="dashboard-link">Open full queue</a></div>
            <div class="table-wrap">
                <table class="ui-table">
                    <thead><tr><th>Employee</th><th>Department</th><th>Type</th><th>Dates</th><th>Days</th></tr></thead>
                    <tbody>
                    <?php if (empty($managerHrDashboard['pending_rows'])): ?>
                        <tr><td colspan="5" class="table-empty">No pending leave requests found.</td></tr>
                    <?php else: foreach ($managerHrDashboard['pending_rows'] as $r): ?>
                        <tr>
                            <td><?= safe_h(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')); ?></td>
                            <td><?= safe_h($r['department'] ?? ''); ?></td>
                            <td><?= safe_h($r['leave_type_name'] ?? $r['leave_type'] ?? ''); ?></td>
                            <td><?= safe_h(app_format_date_range($r['start_date'] ?? '', $r['end_date'] ?? '')); ?></td>
                            <td><?= fmt_days($r['total_days'] ?? 0); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            var textColor = getComputedStyle(document.documentElement).getPropertyValue('--text').trim() || '#111827';
            var monthlyCanvas = document.getElementById('monthlyChart');
            var deptCanvas = document.getElementById('deptChart');
            if (monthlyCanvas) {
                new Chart(monthlyCanvas.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: <?= json_encode(array_map(function($row) use ($monthNames) { $m = (int)($row['m'] ?? 0); return $monthNames[$m - 1] ?? (string)$m; }, $managerHrDashboard['monthly_data'] ?? [])); ?>,
                        datasets: [{ label: 'Approved leaves', data: <?= json_encode(array_map(fn($row) => (int)($row['cnt'] ?? 0), $managerHrDashboard['monthly_data'] ?? [])); ?>, backgroundColor: 'rgba(37,99,235,0.45)', borderRadius: 10 }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { labels: { color: textColor } } }, scales: { x: { ticks: { color: textColor } }, y: { beginAtZero: true, ticks: { color: textColor } } } }
                });
            }
            if (deptCanvas) {
                new Chart(deptCanvas.getContext('2d'), {
                    type: 'pie',
                    data: {
                        labels: <?= json_encode(array_map(fn($row) => (string)($row['department'] ?? 'Unknown'), $managerHrDashboard['dept_chart_data'] ?? [])); ?>,
                        datasets: [{ data: <?= json_encode(array_map(fn($row) => (int)($row['cnt'] ?? 0), $managerHrDashboard['dept_chart_data'] ?? [])); ?> }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { color: textColor } } } }
                });
            }
        });
        </script>

    <?php elseif ($role === 'admin'): ?>
        <div class="dashboard-metrics">
            <div class="dashboard-metric"><div class="metric-label">Total Employees</div><div class="metric-value"><?= (int)$adminDashboard['total_employees']; ?></div><div class="metric-sub">Active employee records in the system</div></div>
            <div class="dashboard-metric"><div class="metric-label">Pending Requests</div><div class="metric-value"><?= (int)$adminDashboard['pending_count']; ?></div><div class="metric-sub">Requests waiting in workflow</div></div>
            <div class="dashboard-metric"><div class="metric-label">Approved Requests</div><div class="metric-value"><?= (int)$adminDashboard['approved_count']; ?></div><div class="metric-sub">Approved leave records</div></div>
            <div class="dashboard-metric"><div class="metric-label">Pending Print Queue</div><div class="metric-value"><?= (int)$adminDashboard['print_queue_count']; ?></div><div class="metric-sub">Finalized forms waiting to print</div></div>
        </div>

        <div class="dashboard-grid">
            <div class="ui-card dashboard-chart-card">
                <div class="dashboard-panel-title"><h3>Employees by Department</h3></div>
                <canvas id="adminDeptChart"></canvas>
            </div>
            <div class="ui-card dashboard-chart-card">
                <div class="dashboard-panel-title"><h3>Users by Role</h3></div>
                <canvas id="adminRoleChart"></canvas>
            </div>
        </div>

        <div class="ui-card table-card">
            <div class="dashboard-panel-title"><h3>Recent Employee Directory Snapshot</h3><a href="manage_employees.php" class="dashboard-link">Manage employees</a></div>
            <div class="table-wrap">
                <table class="ui-table">
                    <thead><tr><th>Name</th><th>Role</th><th>Department</th></tr></thead>
                    <tbody>
                    <?php if (empty($adminDashboard['recent_users'])): ?>
                        <tr><td colspan="3" class="table-empty">No employee records found.</td></tr>
                    <?php else: foreach ($adminDashboard['recent_users'] as $r): ?>
                        <tr>
                            <td><?= safe_h(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')); ?></td>
                            <td><?= safe_h(ucwords(str_replace('_', ' ', (string)($r['role'] ?? '')))); ?></td>
                            <td><?= safe_h($r['department'] ?? ''); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            var textColor = getComputedStyle(document.documentElement).getPropertyValue('--text').trim() || '#111827';
            var deptCanvas = document.getElementById('adminDeptChart');
            var roleCanvas = document.getElementById('adminRoleChart');
            if (deptCanvas) {
                new Chart(deptCanvas.getContext('2d'), {
                    type: 'bar',
                    data: { labels: <?= json_encode(array_map(fn($row) => (string)($row['department'] ?? 'Unknown'), $adminDashboard['dept_data'] ?? [])); ?>, datasets: [{ label: 'Employees', data: <?= json_encode(array_map(fn($row) => (int)($row['cnt'] ?? 0), $adminDashboard['dept_data'] ?? [])); ?>, backgroundColor: 'rgba(37,99,235,0.45)', borderRadius: 10 }] },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { labels: { color: textColor } } }, scales: { x: { ticks: { color: textColor } }, y: { beginAtZero: true, ticks: { color: textColor } } } }
                });
            }
            if (roleCanvas) {
                new Chart(roleCanvas.getContext('2d'), {
                    type: 'doughnut',
                    data: { labels: <?= json_encode(array_map(fn($row) => ucwords(str_replace('_', ' ', (string)($row['role'] ?? 'Unknown'))), $adminDashboard['role_data'] ?? [])); ?>, datasets: [{ data: <?= json_encode(array_map(fn($row) => (int)($row['cnt'] ?? 0), $adminDashboard['role_data'] ?? [])); ?> }] },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { color: textColor } } } }
                });
            }
        });
        </script>
    <?php else: ?>
        <div class="ui-card">
            <div class="dashboard-empty">No dashboard layout is available for this role yet.</div>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
