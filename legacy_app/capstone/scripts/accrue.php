<?php
// Run this script monthly (via cron or manually)
// to add 1.25 days to annual/vacational and sick balances.
// Force leave reset is yearly and should be handled separately.

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Leave.php';

$db = (new Database())->connect();
$leave = new Leave($db);

$result = $leave->accrueAllEmployees(
    1.25,
    date('Y-m'),
    date('Y-m-t'),
    'Monthly accrual recorded'
);

if (!empty($result['success'])) {
    echo "Monthly accrual completed for " . intval($result['count'] ?? 0) . " employee(s).\n";
    echo "Force leave was not changed.\n";
} else {
    echo ($result['message'] ?? "Failed to perform accrual.") . "\n";
}
?>