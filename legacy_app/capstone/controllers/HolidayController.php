<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../config/database.php';
require_once '../models/Holiday.php';
require_once '../helpers/Flash.php';

if (empty($_SESSION['user_id'])) {
    flash_redirect('../views/login.php', 'warning', 'Please log in first.');
}

if (!in_array($_SESSION['role'] ?? '', ['admin','manager','hr','personnel'], true)) {
    $redirect = !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '../views/dashboard.php';
    flash_redirect($redirect, 'error', 'Access Denied!');
}

$db = (new Database())->connect();
$holidayModel = new Holiday($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        flash_redirect('../views/holidays.php', 'error', 'CSRF validation failed. Please try again.');
    }
    if (isset($_POST['add'])) {
        $date = $_POST['date'];
        $desc = trim($_POST['description']);
        $type = $_POST['type'] ?? 'Other';
        $holidayModel->add($date, $desc, $type);
        $msg = 'Holiday added';
        $type = 'success';
    }
    if (isset($_POST['update'])) {
        $id = intval($_POST['id']);
        $date = $_POST['date'];
        $desc = trim($_POST['description']);
        $type = $_POST['type'] ?? 'Other';
        $holidayModel->update($id, $date, $desc, $type);
        $msg = 'Holiday updated';
        $type = 'success';
    }
    if (isset($_POST['delete'])) {
        $id = $_POST['id'];
        $holidayModel->delete($id);
        $msg = 'Holiday removed';
        $type = 'success';
    }
    flash_redirect('../views/holidays.php', $type ?? 'success', $msg ?? 'Holiday action completed');
}
