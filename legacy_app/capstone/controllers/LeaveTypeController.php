<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../config/database.php';
require_once '../models/LeaveType.php';
require_once '../helpers/Flash.php';

if (!in_array($_SESSION['role'] ?? '', ['admin','hr'], true)) {
    die("Unauthorized");
}

$db = (new Database())->connect();
$typeModel = new LeaveType($db);

action:
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

if ($action === 'list') {
    $types = $typeModel->all();
    include __DIR__ . '/../views/manage_leave_types.php';
    exit();
}

if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'name' => trim($_POST['name']),
        'deduct_balance' => isset($_POST['deduct_balance']) ? 1 : 0,
        'requires_approval' => isset($_POST['requires_approval']) ? 1 : 0,
        'max_days_per_year' => $_POST['max_days_per_year'] ?: null,
        'auto_approve' => isset($_POST['auto_approve']) ? 1 : 0,
    ];
    $typeModel->create($data);
    flash_redirect('../views/manage_leave_types.php', 'success', 'Leave type created');
}

if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['type_id']);
    $stmt = $db->prepare("UPDATE leave_types SET name=?, deduct_balance=?, requires_approval=?, max_days_per_year=?, auto_approve=? WHERE id=?");
    $stmt->execute([
        trim($_POST['name']),
        isset($_POST['deduct_balance']) ? 1 : 0,
        isset($_POST['requires_approval']) ? 1 : 0,
        $_POST['max_days_per_year'] ?: null,
        isset($_POST['auto_approve']) ? 1 : 0,
        $id
    ]);
    flash_redirect('../views/manage_leave_types.php', 'success', 'Leave type updated');
}

if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['type_id']);
    $stmt = $db->prepare("DELETE FROM leave_types WHERE id=?");
    $stmt->execute([$id]);
    flash_redirect('../views/manage_leave_types.php', 'success', 'Leave type removed');
}
