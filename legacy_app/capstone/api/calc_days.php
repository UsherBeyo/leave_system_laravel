<?php
// returns JSON with deductible days between two dates excluding weekends and holidays
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
$start = $_GET['start'] ?? null;
$end = $_GET['end'] ?? null;
if (!$start || !$end) {
    echo json_encode(['error' => 'missing parameters']);
    exit;
}

$db = (new Database())->connect();

// reuse logic from Leave model by including it
require_once __DIR__ . '/../models/Leave.php';
$leave = new Leave($db);
$breakdown = method_exists($leave, 'calculateDaysBreakdown')
    ? $leave->calculateDaysBreakdown($start, $end)
    : ['valid' => true, 'days' => $leave->calculateDays($start, $end), 'message' => ''];

echo json_encode([
    'valid' => (bool)($breakdown['valid'] ?? true),
    'days' => (int)($breakdown['days'] ?? 0),
    'calendar_days' => (int)($breakdown['calendar_days'] ?? 0),
    'weekend_days' => (int)($breakdown['weekend_days'] ?? 0),
    'holiday_days' => (int)($breakdown['holiday_days'] ?? 0),
    'message' => (string)($breakdown['message'] ?? ''),
]);
