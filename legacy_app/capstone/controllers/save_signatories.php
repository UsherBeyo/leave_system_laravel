<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../config/database.php';
require_once '../helpers/Auth.php';
require_once '../helpers/Flash.php';
Auth::requireLogin('../views/login.php');

if (!in_array($_SESSION['role'] ?? '', ['personnel','hr','admin'], true)) {
    flash_redirect('../views/leave_requests.php?tab=approved', 'error', 'Access Denied');
}

if (
    !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    flash_redirect('../views/leave_requests.php?tab=approved', 'error', 'Invalid CSRF token');
}

$db = (new Database())->connect();

$leaveId = (int)($_POST['leave_id'] ?? 0);

$nameA = trim($_POST['name_a'] ?? '');
$posA  = trim($_POST['position_a'] ?? '');
$nameC = trim($_POST['name_c'] ?? '');
$posC  = trim($_POST['position_c'] ?? '');

if ($leaveId <= 0) {
    flash_redirect('../views/leave_requests.php?tab=approved', 'error', 'Invalid leave request');
}

if ($nameA === '' || $posA === '' || $nameC === '' || $posC === '') {
    flash_redirect('../views/leave_requests.php?tab=approved', 'error', 'All signatory fields are required.');
}

$stmt = $db->prepare("
    INSERT INTO leave_request_forms
    (
        leave_request_id,
        personnel_signatory_name_a,
        personnel_signatory_position_a,
        personnel_signatory_name_c,
        personnel_signatory_position_c
    )
    VALUES (?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        personnel_signatory_name_a = VALUES(personnel_signatory_name_a),
        personnel_signatory_position_a = VALUES(personnel_signatory_position_a),
        personnel_signatory_name_c = VALUES(personnel_signatory_name_c),
        personnel_signatory_position_c = VALUES(personnel_signatory_position_c)
");

$stmt->execute([
    $leaveId,
    $nameA,
    $posA,
    $nameC,
    $posC
]);

flash_redirect('../views/print_leave_form.php?id=' . $leaveId, 'success', 'Signatories saved successfully');