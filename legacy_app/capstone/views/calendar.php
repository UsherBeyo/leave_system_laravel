<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../config/database.php';
require_once '../helpers/Auth.php';
Auth::requireLogin('login.php');
require_once '../helpers/DateHelper.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$db = (new Database())->connect();

$role = (string)($_SESSION['role'] ?? '');
$sessionEmpId = (int)($_SESSION['emp_id'] ?? 0);

$month = isset($_GET['m']) ? max(1, min(12, intval($_GET['m']))) : intval(date('n'));
$year  = isset($_GET['y']) ? max(2000, min(2100, intval($_GET['y']))) : intval(date('Y'));

$start = sprintf('%04d-%02d-01', $year, $month);
$end = date('Y-m-t', strtotime($start));
$today = date('Y-m-d');
$monthLabel = date('F Y', strtotime($start));

$showFullCalendarLeaves = in_array($role, ['admin', 'personnel', 'hr'], true);
$showSnapshotDetails = in_array($role, ['admin', 'personnel', 'hr'], true);

$accessibleDepartmentIds = [];
if ($role === 'department_head' && $sessionEmpId > 0) {
    $deptStmt = $db->prepare("SELECT dha.department_id
        FROM department_head_assignments dha
        WHERE dha.employee_id = ? AND dha.is_active = 1");
    $deptStmt->execute([$sessionEmpId]);
    $accessibleDepartmentIds = array_values(array_unique(array_map('intval', $deptStmt->fetchAll(PDO::FETCH_COLUMN) ?: [])));

    if (empty($accessibleDepartmentIds)) {
        $fallbackStmt = $db->prepare("SELECT department_id FROM employees WHERE id = ?");
        $fallbackStmt->execute([$sessionEmpId]);
        $fallbackDeptId = (int)$fallbackStmt->fetchColumn();
        if ($fallbackDeptId > 0) {
            $accessibleDepartmentIds = [$fallbackDeptId];
        }
    }
}

$calendarLeaveWhere = ["LOWER(lr.status) IN ('approved','pending')"];
$calendarLeaveParams = [];

if (!$showFullCalendarLeaves) {
    if ($role === 'department_head') {
        if (!empty($accessibleDepartmentIds)) {
            $placeholders = implode(',', array_fill(0, count($accessibleDepartmentIds), '?'));
            $calendarLeaveWhere[] = "lr.department_id IN ($placeholders)";
            $calendarLeaveParams = array_merge($calendarLeaveParams, $accessibleDepartmentIds);
        } else {
            $calendarLeaveWhere[] = "1 = 0";
        }
    } else {
        $calendarLeaveWhere[] = "lr.employee_id = ?";
        $calendarLeaveParams[] = max(0, $sessionEmpId);
    }
}

$calendarLeaveWhereSql = implode(' AND ', $calendarLeaveWhere);

$holidaysStmt = $db->prepare("SELECT id, holiday_date, description, type FROM holidays WHERE holiday_date BETWEEN ? AND ? ORDER BY holiday_date ASC");
$holidaysStmt->execute([$start, $end]);
$holidays = $holidaysStmt->fetchAll(PDO::FETCH_ASSOC);

$leavesSql = "
    SELECT lr.id, lr.employee_id, lr.department_id, lr.leave_type, lr.start_date, lr.end_date, lr.total_days, lr.status, lr.created_at,
           e.first_name, e.last_name
    FROM leave_requests lr
    JOIN employees e ON lr.employee_id = e.id
    WHERE {$calendarLeaveWhereSql}
      AND lr.start_date <= ?
      AND lr.end_date >= ?
    ORDER BY lr.start_date ASC, lr.created_at ASC, lr.id ASC
";
$leavesStmt = $db->prepare($leavesSql);
$leavesStmt->execute(array_merge($calendarLeaveParams, [$end, $start]));
$leaves = $leavesStmt->fetchAll(PDO::FETCH_ASSOC);

$upcomingLeavesSql = "
    SELECT lr.id, lr.employee_id, lr.department_id, lr.leave_type, lr.start_date, lr.end_date, lr.total_days, lr.status,
           e.first_name, e.last_name
    FROM leave_requests lr
    JOIN employees e ON lr.employee_id = e.id
    WHERE {$calendarLeaveWhereSql}
      AND lr.end_date >= ?
    ORDER BY lr.start_date ASC, lr.created_at ASC, lr.id ASC
    LIMIT 6
";
$upcomingLeavesStmt = $db->prepare($upcomingLeavesSql);
$upcomingLeavesStmt->execute(array_merge($calendarLeaveParams, [$today]));
$upcomingLeaves = $upcomingLeavesStmt->fetchAll(PDO::FETCH_ASSOC);

$upcomingEventsStmt = $db->prepare("
    SELECT id, holiday_date, description, type
    FROM holidays
    WHERE holiday_date >= ?
    ORDER BY holiday_date ASC
    LIMIT 6
");
$upcomingEventsStmt->execute([$today]);
$upcomingEvents = $upcomingEventsStmt->fetchAll(PDO::FETCH_ASSOC);

$events = [];
$monthApprovedCount = 0;
$monthPendingCount = 0;

foreach ($holidays as $holiday) {
    $date = (string)$holiday['holiday_date'];
    $events[$date][] = [
        'type' => 'holiday',
        'status' => 'holiday',
        'title' => (string)($holiday['description'] ?: 'Holiday'),
        'desc' => (string)($holiday['type'] ?: 'Holiday'),
        'meta' => app_format_date($date),
    ];
}

foreach ($leaves as $leave) {
    $status = strtolower(trim((string)$leave['status']));
    if ($status === 'approved') $monthApprovedCount++;
    if ($status === 'pending') $monthPendingCount++;

    $current = (string)$leave['start_date'];
    $employeeName = trim((string)$leave['first_name'] . ' ' . (string)$leave['last_name']);
    $leaveType = trim((string)$leave['leave_type']);
    $displayTitle = $employeeName !== '' ? $employeeName : 'Employee Leave';
    $displayDesc = $leaveType !== '' ? $leaveType : 'Leave Request';
    $displayMeta = app_format_date_range((string)$leave['start_date'], (string)$leave['end_date']);

    while ($current <= $leave['end_date']) {
        if ($current >= $start && $current <= $end) {
            $events[$current][] = [
                'type' => 'leave',
                'status' => $status,
                'title' => $displayTitle,
                'desc' => $displayDesc,
                'meta' => $displayMeta,
            ];
        }
        $current = date('Y-m-d', strtotime($current . ' +1 day'));
    }
}

ksort($events);

$daysWithEvents = count($events);
$totalMonthRequests = count($leaves);
$totalMonthHolidays = count($holidays);

$firstDow = intval(date('N', strtotime($start)));
$daysInMonth = intval(cal_days_in_month(CAL_GREGORIAN, $month, $year));
$appBase = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\') . '/';
$calendarBaseUrl = $appBase . 'calendar';
?>
<!DOCTYPE html>
<html>
<head>
    <base href="<?= htmlspecialchars(rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/', ENT_QUOTES, 'UTF-8'); ?>">
    <title>Leave Calendar</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <script src="../assets/js/script.js"></script>
    <style>
        .calendar-shell {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 340px;
            gap: 24px;
            align-items: start;
        }
        .calendar-board {
            min-width: 0;
        }
        .calendar-card {
            overflow: hidden;
        }
        .calendar-headline {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 18px;
            flex-wrap: wrap;
        }
        .calendar-month-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 999px;
            background: linear-gradient(135deg, rgba(37,99,235,0.10), rgba(34,197,94,0.10));
            border: 1px solid rgba(37,99,235,0.15);
            color: var(--text);
            font-weight: 600;
        }
        .calendar-month-chip small {
            font-size: 12px;
            color: var(--muted);
        }
        .calendar-grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: 10px;
        }
        .calendar-grid th {
            padding: 0 0 6px;
            font-size: 12px;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: var(--muted);
            text-align: left;
        }
        .calendar-grid td {
            width: 14.285%;
            min-width: 110px;
            height: 116px;
            padding: 12px;
            vertical-align: top;
            border-radius: 18px;
            border: 1px solid var(--border);
            background: #ffffff;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
            transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease, background-color .18s ease;
            position: relative;
        }
        .calendar-grid td.is-empty {
            background: transparent;
            border-style: dashed;
            box-shadow: none;
        }
        .calendar-grid td[data-date] {
            cursor: pointer;
        }
        .calendar-grid td[data-date]:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.09);
            border-color: rgba(37, 99, 235, 0.25);
            background: #f8fbff;
        }
        .calendar-grid td.has-events::after {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 18px;
            pointer-events: none;
            border: 1px solid transparent;
        }
        .calendar-grid td.has-holiday::after {
            border-color: rgba(239, 68, 68, 0.18);
        }
        .calendar-grid td.has-approved::after {
            box-shadow: inset 0 0 0 1px rgba(34, 197, 94, 0.10);
        }
        .calendar-grid td.has-pending::after {
            box-shadow: inset 0 0 0 1px rgba(234, 179, 8, 0.12);
        }
        .calendar-grid td.is-today {
            background: linear-gradient(180deg, #eff6ff 0%, #ffffff 100%);
            border-color: rgba(37,99,235,0.35);
        }
        .day-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
        }
        .day-number {
            font-size: 18px;
            font-weight: 700;
            color: var(--text);
            line-height: 1;
        }
        .today-badge {
            padding: 4px 8px;
            border-radius: 999px;
            background: rgba(37,99,235,0.12);
            color: var(--primary);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
        }
        .day-pill-row {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .day-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            width: fit-content;
            max-width: 100%;
            padding: 5px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
            white-space: nowrap;
        }
        .day-pill::before {
            content: '';
            width: 7px;
            height: 7px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        .day-pill.holiday {
            background: #fef2f2;
            color: #b91c1c;
        }
        .day-pill.holiday::before,
        .legend-dot.holiday,
        .panel-badge.holiday::before {
            background: #ef4444;
        }
        .day-pill.approved {
            background: #f0fdf4;
            color: #15803d;
        }
        .day-pill.approved::before,
        .legend-dot.approved,
        .panel-badge.approved::before {
            background: #22c55e;
        }
        .day-pill.pending {
            background: #fffbeb;
            color: #a16207;
        }
        .day-pill.pending::before,
        .legend-dot.pending,
        .panel-badge.pending::before {
            background: #eab308;
        }
        .calendar-note {
            margin-top: 10px;
            font-size: 12px;
            color: var(--muted);
        }
        .calendar-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
        }
        .calendar-jump-form {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            padding: 10px 12px;
            border-radius: 16px;
            border: 1px solid var(--border);
            background: linear-gradient(180deg, #ffffff, #f8fafc);
            box-shadow: 0 4px 12px rgba(15,23,42,0.04);
        }
        .calendar-jump-field {
            display: flex;
            flex-direction: column;
            gap: 4px;
            min-width: 132px;
        }
        .calendar-jump-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--muted);
        }
        .calendar-jump-select {
            min-height: 40px;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 9px 12px;
            background: #fff;
            color: var(--text);
            font-weight: 600;
        }
        .calendar-jump-actions {
            display: inline-flex;
            gap: 8px;
            align-items: center;
            margin-left: 4px;
            padding-top: 16px;
        }
        .calendar-insight-card {
            border-radius: 20px;
            overflow: hidden;
            background: linear-gradient(135deg, rgba(37,99,235,0.06), rgba(16,185,129,0.04));
        }
        .calendar-action-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
        }
        .calendar-modal-trigger {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            width: 100%;
            padding: 15px 16px;
            border-radius: 18px;
            border: 1px solid rgba(37,99,235,0.12);
            background: #fff;
            text-align: left;
            cursor: pointer;
            transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
            box-shadow: 0 2px 10px rgba(15,23,42,0.04);
        }
        .calendar-modal-trigger:hover {
            transform: translateY(-2px);
            color: #ffffff;
            background: #2563eb;
            box-shadow: 0 12px 26px rgba(15,23,42,0.08);
            border-color: rgba(37,99,235,0.22);
        }
        .calendar-modal-trigger:hover .calendar-trigger-title,
        .calendar-modal-trigger:hover .calendar-trigger-sub {
            color: #ffffff;
        }
        .calendar-modal-trigger:hover .calendar-trigger-count {
            background: linear-gradient(135deg, #2563eb, #d2f0e6);
            color: #ffffff;
        }
        .calendar-trigger-copy {
            min-width: 0;
        }
        .calendar-trigger-title {
            font-size: 15px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 4px;
        }
        .calendar-trigger-sub {
            font-size: 12px;
            color: var(--secondary-text);
        }
        
        .calendar-trigger-count {
            flex-shrink: 0;
            min-width: 58px;
            height: 58px;
            border-radius: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #dbeafe, #eef2ff);
            color: var(--primary);
            font-size: 22px;
            font-weight: 800;
        }
        .calendar-overview-note {
            margin-top: 12px;
            font-size: 12px;
            color: var(--muted);
        }
        .calendar-detail-modal .modal-content {
            width: min(760px, 94vw);
            padding: 0;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 20px 45px rgba(15,23,42,0.18);
        }
        .calendar-detail-shell {
            background: linear-gradient(180deg, #f8fbff 0%, #ffffff 22%);
        }
        .calendar-detail-header {
            position: relative;
            padding: 24px 24px 18px;
            border-bottom: 1px solid var(--border);
        }
        .calendar-detail-kicker {
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .09em;
            color: var(--muted);
            margin-bottom: 8px;
        }
        .calendar-detail-title {
            font-size: 26px;
            font-weight: 800;
            color: var(--text);
            margin: 0 0 8px;
            padding-right: 52px;
        }
        .calendar-detail-subtitle {
            font-size: 14px;
            color: var(--secondary-text);
            margin: 0;
        }
        .calendar-detail-body {
            padding: 22px 24px 24px;
            display: flex;
            flex-direction: column;
            gap: 14px;
            max-height: 70vh;
            overflow-y: auto;
        }
        .calendar-detail-modal .modal-close {
            top: 18px;
            right: 18px;
            width: 36px;
            height: 36px;
            border-radius: 12px;
            background: #fff;
            border: 1px solid var(--border);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 6px 18px rgba(15,23,42,0.08);
        }
        .calendar-sidebar-stack {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .calendar-summary-card {
            border-radius: 20px;
            overflow: hidden;
        }
        .calendar-summary-card h4 {
            margin: 0 0 14px;
        }
        .summary-kicker {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--muted);
            margin-bottom: 8px;
        }
        .legend-grid {
            display: grid;
            gap: 10px;
        }
        .legend-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 14px;
            border-radius: 14px;
            border: 1px solid var(--border);
            background: #fff;
        }
        .legend-left {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: var(--text);
            font-weight: 600;
        }
        .legend-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
        }
        .legend-dot.today {
            background: transparent;
            border: 2px solid #2563eb;
        }
        .legend-text {
            font-size: 12px;
            color: var(--muted);
        }
        .summary-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .summary-item {
            padding: 14px;
            border-radius: 16px;
            border: 1px solid var(--border);
            background: #fff;
            box-shadow: 0 1px 2px rgba(15,23,42,0.04);
        }
        .summary-item-header {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: start;
            margin-bottom: 6px;
        }
        .summary-item-title {
            color: var(--text);
            font-weight: 700;
            line-height: 1.3;
        }
        .summary-item-sub {
            color: var(--secondary-text);
            font-size: 13px;
        }
        .summary-item-meta {
            color: var(--muted);
            font-size: 12px;
            margin-top: 6px;
        }
        .summary-badge,
        .panel-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 999px;
            padding: 5px 10px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .03em;
            text-transform: uppercase;
            width: fit-content;
            flex-shrink: 0;
        }
        .summary-badge.approved,
        .panel-badge.approved {
            background: #f0fdf4;
            color: #15803d;
        }
        .summary-badge.pending,
        .panel-badge.pending {
            background: #fffbeb;
            color: #a16207;
        }
        .summary-badge.holiday,
        .panel-badge.holiday {
            background: #fef2f2;
            color: #b91c1c;
        }
        .panel-badge::before {
            content: '';
            width: 7px;
            height: 7px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        .month-stats {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }
        .stat-card {
            padding: 16px;
            border-radius: 16px;
            border: 1px solid var(--border);
            background: linear-gradient(180deg, #fff, #f8fafc);
        }
        .stat-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .07em;
            color: var(--muted);
            margin-bottom: 8px;
        }
        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--text);
            line-height: 1;
        }
        .stat-help {
            font-size: 12px;
            color: var(--secondary-text);
            margin-top: 6px;
        }
        .empty-state {
            padding: 16px;
            border-radius: 16px;
            border: 1px dashed var(--border);
            background: #fff;
            color: var(--muted);
            text-align: center;
        }
        .calendar-side-panel {
            left: -420px;
            width: 400px;
            padding: 26px 22px;
            border-right: 1px solid var(--border);
            border-left: none;
            box-shadow: 18px 0 40px rgba(15, 23, 42, 0.12);
            background: linear-gradient(180deg, #ffffff, #f8fafc);
        }
        .calendar-side-panel.open {
            left: 0;
        }
        .calendar-panel-close {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            border-radius: 12px;
            border: 1px solid var(--border);
            background: #fff;
            color: var(--text);
            font-size: 20px;
            cursor: pointer;
            margin-left: auto;
            margin-bottom: 14px;
            transition: background-color .18s ease, transform .18s ease;
        }
        .calendar-panel-close:hover {
            background: #f1f5f9;
            transform: scale(1.03);
        }
        .panel-date-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 8px;
        }
        .panel-subtitle {
            font-size: 13px;
            color: var(--muted);
            margin-bottom: 18px;
        }
        .panel-stack {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .panel-event-card {
            padding: 16px;
            border-radius: 18px;
            border: 1px solid var(--border);
            background: #fff;
            box-shadow: 0 2px 8px rgba(15,23,42,0.05);
        }
        .panel-event-top {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            align-items: center;
            margin-bottom: 10px;
            flex-wrap: wrap;
        }
        .panel-event-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 6px;
        }
        .panel-event-desc {
            color: var(--secondary-text);
            font-size: 14px;
            margin-bottom: 8px;
        }
        .panel-event-meta {
            color: var(--muted);
            font-size: 12px;
        }
        .panel-empty {
            padding: 18px;
            border-radius: 18px;
            border: 1px dashed var(--border);
            color: var(--muted);
            text-align: center;
            background: rgba(255,255,255,0.7);
        }
        @media (max-width: 1180px) {
            .calendar-shell {
                grid-template-columns: 1fr;
            }
            .calendar-sidebar-stack {
                order: -1;
            }
        }
        @media (max-width: 860px) {
            .calendar-grid {
                border-spacing: 6px;
            }
            .calendar-toolbar {
                align-items: stretch;
            }
            .calendar-jump-form {
                width: 100%;
            }
            .calendar-jump-field {
                flex: 1 1 160px;
            }
            .calendar-grid td {
                min-width: 0;
                height: 100px;
                padding: 10px;
            }
            .month-stats {
                grid-template-columns: 1fr 1fr;
            }
            .calendar-side-panel {
                width: min(92vw, 400px);
            }
        }
        @media (max-width: 640px) {
            .calendar-grid th {
                font-size: 11px;
            }
            .calendar-jump-actions {
                width: 100%;
                margin-left: 0;
                padding-top: 0;
            }
            .calendar-jump-actions .btn {
                flex: 1 1 0;
            }
            .calendar-trigger-count {
                min-width: 50px;
                height: 50px;
                font-size: 18px;
            }
            .calendar-detail-title {
                font-size: 22px;
            }
            .calendar-grid td {
                height: 90px;
                padding: 8px;
            }
            .day-number {
                font-size: 16px;
            }
            .day-pill {
                font-size: 10px;
                padding: 4px 8px;
            }
            .month-stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/partials/sidebar.php'; ?>

<div class="app-main">
    <div class="calendar-shell">
        <div class="calendar-board">
            <?php
            $title = 'Leave Calendar';
            $subtitle = 'View all scheduled leaves and holidays';
            $actions = [
                '<a href="' . htmlspecialchars($calendarBaseUrl, ENT_QUOTES, 'UTF-8') . '?m=' . ($month == 1 ? 12 : $month - 1) . '&y=' . ($month == 1 ? $year - 1 : $year) . '" class="btn btn-ghost">&lt; Prev</a>',
                '<a href="' . htmlspecialchars($calendarBaseUrl, ENT_QUOTES, 'UTF-8') . '?m=' . intval(date('n')) . '&y=' . intval(date('Y')) . '" class="btn btn-secondary">Today</a>',
                '<a href="' . htmlspecialchars($calendarBaseUrl, ENT_QUOTES, 'UTF-8') . '?m=' . ($month == 12 ? 1 : $month + 1) . '&y=' . ($month == 12 ? $year + 1 : $year) . '" class="btn btn-ghost">Next &gt;</a>'
            ];
            include __DIR__ . '/partials/ui/page-header.php';
            ?>

            <div class="ui-card calendar-card">
                <div class="calendar-headline">
                    <div class="calendar-toolbar">
                        <div>
                            <div class="calendar-month-chip">
                                <span><?= htmlspecialchars($monthLabel); ?></span>
                                <small><?= intval($daysWithEvents); ?> active day<?= $daysWithEvents === 1 ? '' : 's'; ?></small>
                            </div>
                            <div class="calendar-note">Click a date with events to view full details.</div>
                        </div>
                        <form method="get" action="<?= htmlspecialchars($calendarBaseUrl, ENT_QUOTES, 'UTF-8'); ?>" class="calendar-jump-form">
                            <div class="calendar-jump-field">
                                <label class="calendar-jump-label" for="calendar-month-select">Month</label>
                                <select id="calendar-month-select" name="m" class="calendar-jump-select">
                                    <?php for ($monthOption = 1; $monthOption <= 12; $monthOption++): ?>
                                        <option value="<?= $monthOption; ?>" <?= $monthOption === $month ? 'selected' : ''; ?>>
                                            <?= htmlspecialchars(date('F', mktime(0, 0, 0, $monthOption, 1, $year))); ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="calendar-jump-field">
                                <label class="calendar-jump-label" for="calendar-year-select">Year</label>
                                <select id="calendar-year-select" name="y" class="calendar-jump-select">
                                    <?php for ($yearOption = max(2000, $year - 5); $yearOption <= min(2100, $year + 5); $yearOption++): ?>
                                        <option value="<?= $yearOption; ?>" <?= $yearOption === $year ? 'selected' : ''; ?>><?= $yearOption; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="calendar-jump-actions">
                                <button type="submit" class="btn btn-primary">Jump</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="table-wrap">
                    <table class="calendar-grid">
                        <tr>
                            <th>Mon</th>
                            <th>Tue</th>
                            <th>Wed</th>
                            <th>Thu</th>
                            <th>Fri</th>
                            <th>Sat</th>
                            <th>Sun</th>
                        </tr>
                        <?php
                        $day = 1;
                        $dow = 1;
                        echo '<tr>';
                        for ($i = 1; $i < $firstDow; $i++) {
                            echo '<td class="is-empty"></td>';
                            $dow++;
                        }
                        while ($day <= $daysInMonth) {
                            if ($dow > 7) {
                                echo '</tr><tr>';
                                $dow = 1;
                            }

                            $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
                            $dayEvents = $events[$date] ?? [];
                            $holidayCount = 0;
                            $approvedCount = 0;
                            $pendingCount = 0;
                            foreach ($dayEvents as $event) {
                                if (($event['type'] ?? '') === 'holiday') $holidayCount++;
                                if (($event['status'] ?? '') === 'approved') $approvedCount++;
                                if (($event['status'] ?? '') === 'pending') $pendingCount++;
                            }

                            $classes = ['calendar-day'];
                            if (!empty($dayEvents)) $classes[] = 'has-events';
                            if ($holidayCount > 0) $classes[] = 'has-holiday';
                            if ($approvedCount > 0) $classes[] = 'has-approved';
                            if ($pendingCount > 0) $classes[] = 'has-pending';
                            if ($date === $today) $classes[] = 'is-today';

                            echo '<td class="' . implode(' ', $classes) . '" data-date="' . htmlspecialchars($date) . '" data-human-date="' . htmlspecialchars(app_format_date($date)) . '">';
                            echo '<div class="day-header">';
                            echo '<span class="day-number">' . intval($day) . '</span>';
                            if ($date === $today) {
                                echo '<span class="today-badge">Today</span>';
                            }
                            echo '</div>';
                            echo '<div class="day-pill-row">';
                            if ($holidayCount > 0) {
                                echo '<span class="day-pill holiday">Holiday' . ($holidayCount > 1 ? ' × ' . $holidayCount : '') . '</span>';
                            }
                            if ($approvedCount > 0) {
                                echo '<span class="day-pill approved">Approved' . ($approvedCount > 1 ? ' × ' . $approvedCount : '') . '</span>';
                            }
                            if ($pendingCount > 0) {
                                echo '<span class="day-pill pending">Pending' . ($pendingCount > 1 ? ' × ' . $pendingCount : '') . '</span>';
                            }
                            echo '</div>';
                            echo '</td>';

                            $day++;
                            $dow++;
                        }
                        while ($dow <= 7) {
                            echo '<td class="is-empty"></td>';
                            $dow++;
                        }
                        echo '</tr>';
                        ?>
                    </table>
                </div>
            </div>
        </div>

        <div class="calendar-sidebar-stack">
            <div class="ui-card calendar-summary-card">
                <div class="summary-kicker">Legend</div>
                <h4>Calendar Colors</h4>
                <div class="legend-grid">
                    <div class="legend-item">
                        <div class="legend-left"><span class="legend-dot holiday"></span><span>Holiday</span></div>
                        <span class="legend-text">Red chip</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-left"><span class="legend-dot approved"></span><span>Approved Leave</span></div>
                        <span class="legend-text">Green chip</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-left"><span class="legend-dot pending"></span><span>Pending Leave</span></div>
                        <span class="legend-text">Yellow chip</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-left"><span class="legend-dot today"></span><span>Today</span></div>
                        <span class="legend-text">Blue outline</span>
                    </div>
                </div>
            </div>

            <div class="ui-card calendar-summary-card calendar-insight-card">
                <div class="summary-kicker">Quick View</div>
                <h4>Open Calendar Details</h4>
                <div class="calendar-action-grid">
                    <button type="button" class="calendar-modal-trigger" data-modal-target="upcomingLeavesModal">
                        <div class="calendar-trigger-copy">
                            <div class="calendar-trigger-title">Upcoming Leaves</div>
                            <div class="calendar-trigger-sub">Review the next approved and pending leave requests.</div>
                        </div>
                        <span class="calendar-trigger-count"><?= count($upcomingLeaves); ?></span>
                    </button>
                    <button type="button" class="calendar-modal-trigger" data-modal-target="upcomingEventsModal">
                        <div class="calendar-trigger-copy">
                            <div class="calendar-trigger-title">Upcoming Events</div>
                            <div class="calendar-trigger-sub">See the next holidays and non-working dates.</div>
                        </div>
                        <span class="calendar-trigger-count"><?= count($upcomingEvents); ?></span>
                    </button>
                    <?php if ($showSnapshotDetails): ?>
                    <button type="button" class="calendar-modal-trigger" data-modal-target="snapshotModal">
                        <div class="calendar-trigger-copy">
                            <div class="calendar-trigger-title">Snapshot</div>
                            <div class="calendar-trigger-sub">Get a quick monthly summary before planning ahead.</div>
                        </div>
                        <span class="calendar-trigger-count"><?= intval($totalMonthRequests); ?></span>
                    </button>
                    <?php endif; ?>
                </div>
                <div class="calendar-overview-note">These open in focused modals so you can review details without stretching the page downward.</div>
                <?php if (!$showSnapshotDetails): ?>
                    <div class="calendar-overview-note" style="margin-top:8px;">Monthly snapshots are visible only to personnel and admins.</div>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <div id="upcomingLeavesModal" class="modal calendar-detail-modal" style="display:none;">
        <div class="modal-content calendar-detail-shell">
            <button type="button" class="modal-close" data-close-modal="upcomingLeavesModal" aria-label="Close">&times;</button>
            <div class="calendar-detail-header">
                <div class="calendar-detail-kicker">Calendar Detail</div>
                <h3 class="calendar-detail-title">Upcoming Leaves</h3>
                <p class="calendar-detail-subtitle">A focused list of the next approved and pending leave requests.</p>
            </div>
            <div class="calendar-detail-body">
                <?php if (empty($upcomingLeaves)): ?>
                    <div class="empty-state">No upcoming leave requests found.</div>
                <?php else: ?>
                    <?php foreach ($upcomingLeaves as $leave): ?>
                        <?php $status = strtolower(trim((string)$leave['status'])); ?>
                        <div class="summary-item">
                            <div class="summary-item-header">
                                <div>
                                    <div class="summary-item-title"><?= htmlspecialchars(trim(($leave['first_name'] ?? '') . ' ' . ($leave['last_name'] ?? ''))); ?></div>
                                    <div class="summary-item-sub"><?= htmlspecialchars((string)$leave['leave_type']); ?></div>
                                </div>
                                <span class="summary-badge <?= $status === 'approved' ? 'approved' : 'pending'; ?>"><?= htmlspecialchars(ucfirst($status)); ?></span>
                            </div>
                            <div class="summary-item-meta"><?= htmlspecialchars(app_format_date_range((string)$leave['start_date'], (string)$leave['end_date'])); ?><?= !empty($leave['total_days']) ? ' • ' . number_format((float)$leave['total_days'], 3) . ' day(s)' : ''; ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="upcomingEventsModal" class="modal calendar-detail-modal" style="display:none;">
        <div class="modal-content calendar-detail-shell">
            <button type="button" class="modal-close" data-close-modal="upcomingEventsModal" aria-label="Close">&times;</button>
            <div class="calendar-detail-header">
                <div class="calendar-detail-kicker">Calendar Detail</div>
                <h3 class="calendar-detail-title">Upcoming Events</h3>
                <p class="calendar-detail-subtitle">All upcoming holidays in an easier-to-scan modal list.</p>
            </div>
            <div class="calendar-detail-body">
                <?php if (empty($upcomingEvents)): ?>
                    <div class="empty-state">No upcoming holidays found.</div>
                <?php else: ?>
                    <?php foreach ($upcomingEvents as $event): ?>
                        <div class="summary-item">
                            <div class="summary-item-header">
                                <div>
                                    <div class="summary-item-title"><?= htmlspecialchars((string)($event['description'] ?: 'Holiday')); ?></div>
                                    <div class="summary-item-sub"><?= htmlspecialchars((string)($event['type'] ?: 'Holiday')); ?></div>
                                </div>
                                <span class="summary-badge holiday">Holiday</span>
                            </div>
                            <div class="summary-item-meta"><?= htmlspecialchars(app_format_date((string)$event['holiday_date'])); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($showSnapshotDetails): ?>
    <div id="snapshotModal" class="modal calendar-detail-modal" style="display:none;">
        <div class="modal-content calendar-detail-shell">
            <button type="button" class="modal-close" data-close-modal="snapshotModal" aria-label="Close">&times;</button>
            <div class="calendar-detail-header">
                <div class="calendar-detail-kicker">Calendar Detail</div>
                <h3 class="calendar-detail-title">This Month Snapshot</h3>
                <p class="calendar-detail-subtitle">A clean summary of requests, approvals, pending items, and holiday dates for <?= htmlspecialchars($monthLabel); ?>.</p>
            </div>
            <div class="calendar-detail-body">
                <div class="month-stats">
                    <div class="stat-card">
                        <div class="stat-label">Leave Requests</div>
                        <div class="stat-value"><?= intval($totalMonthRequests); ?></div>
                        <div class="stat-help">Requests visible in this month view.</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Approved</div>
                        <div class="stat-value" style="color:#15803d;"><?= intval($monthApprovedCount); ?></div>
                        <div class="stat-help">Approved leave requests in this month.</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Pending</div>
                        <div class="stat-value" style="color:#a16207;"><?= intval($monthPendingCount); ?></div>
                        <div class="stat-help">Pending leave requests still awaiting action.</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Holiday Dates</div>
                        <div class="stat-value" style="color:#b91c1c;"><?= intval($totalMonthHolidays); ?></div>
                        <div class="stat-help">Holiday dates stored for the selected month.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div id="sidePanel" class="side-panel calendar-side-panel" aria-hidden="true">
        <button id="closeSidePanel" class="calendar-panel-close" type="button" aria-label="Close">×</button>
        <div id="panelContent">
            <div class="panel-empty">Select a calendar date with events to view full details.</div>
        </div>
    </div>

    <script>
        var calendarEvents = <?= json_encode($events, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

        function escapeHtml(value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function renderPanel(dateKey, humanDate, items) {
            var content = document.getElementById('panelContent');
            var html = '<div class="panel-date-title">' + escapeHtml(humanDate || dateKey) + '</div>';
            html += '<div class="panel-subtitle">' + items.length + ' item' + (items.length === 1 ? '' : 's') + ' scheduled on this date.</div>';
            html += '<div class="panel-stack">';

            items.forEach(function(item) {
                var badgeClass = item.type === 'holiday' ? 'holiday' : ((item.status || '').toLowerCase() === 'approved' ? 'approved' : 'pending');
                var badgeLabel = item.type === 'holiday' ? 'Holiday' : (((item.status || '').toLowerCase() === 'approved') ? 'Approved Leave' : 'Pending Leave');
                html += '<div class="panel-event-card">';
                html += '<div class="panel-event-top"><span class="panel-badge ' + badgeClass + '">' + escapeHtml(badgeLabel) + '</span></div>';
                html += '<div class="panel-event-title">' + escapeHtml(item.title || '') + '</div>';
                html += '<div class="panel-event-desc">' + escapeHtml(item.desc || '') + '</div>';
                if (item.meta) {
                    html += '<div class="panel-event-meta">' + escapeHtml(item.meta) + '</div>';
                }
                html += '</div>';
            });

            html += '</div>';
            content.innerHTML = html;
        }

        document.querySelectorAll('td[data-date]').forEach(function(cell) {
            cell.addEventListener('click', function() {
                var dateKey = this.getAttribute('data-date');
                var humanDate = this.getAttribute('data-human-date');
                var items = calendarEvents[dateKey] || [];
                if (!items.length) return;
                renderPanel(dateKey, humanDate, items);
                document.getElementById('sidePanel').classList.add('open');
            });
        });

        document.getElementById('closeSidePanel').addEventListener('click', function() {
            document.getElementById('sidePanel').classList.remove('open');
        });

        window.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                document.getElementById('sidePanel').classList.remove('open');
            }
        });

        window.addEventListener('click', function(event) {
            var panel = document.getElementById('sidePanel');
            if (!panel.contains(event.target) && !event.target.closest('td[data-date]')) {
                panel.classList.remove('open');
            }
        });

        function openCalendarModal(id) {
            var modal = document.getElementById(id);
            if (!modal) return;
            modal.style.display = 'flex';
            modal.classList.add('open');
            document.body.style.overflow = 'hidden';
        }

        function closeCalendarModal(id) {
            var modal = document.getElementById(id);
            if (!modal) return;
            modal.classList.remove('open');
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }

        document.querySelectorAll('[data-modal-target]').forEach(function(button) {
            button.addEventListener('click', function() {
                openCalendarModal(this.getAttribute('data-modal-target'));
            });
        });

        document.querySelectorAll('[data-close-modal]').forEach(function(button) {
            button.addEventListener('click', function() {
                closeCalendarModal(this.getAttribute('data-close-modal'));
            });
        });

        document.querySelectorAll('.calendar-detail-modal').forEach(function(modal) {
            modal.addEventListener('click', function(event) {
                if (event.target === modal) {
                    closeCalendarModal(modal.id);
                }
            });
        });

        window.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                document.querySelectorAll('.calendar-detail-modal.open').forEach(function(modal) {
                    closeCalendarModal(modal.id);
                });
            }
        });
    </script>
</div>
</body>
</html>
