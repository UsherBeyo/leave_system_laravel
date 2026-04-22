<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../config/database.php';
require_once '../models/Department.php';
require_once '../helpers/Flash.php';

if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Access denied");
}

$db = (new Database())->connect();
$departmentModel = new Department($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF validation failed.");
    }

    if (isset($_POST['create_department'])) {
        $name = trim($_POST['name']);
        if (empty($name)) {
            flash_redirect('../views/manage_departments.php', 'error', 'Department name required');
        }
        $departmentModel->create($name);
        flash_redirect('../views/manage_departments.php', 'success', 'Department created');
    }

    if (isset($_POST['update_department'])) {
        $id = intval($_POST['id']);
        $name = trim($_POST['name']);
        if (empty($name)) {
            flash_redirect('../views/manage_departments.php', 'error', 'Department name required');
        }
        $departmentModel->update($id, $name);
        flash_redirect('../views/manage_departments.php', 'success', 'Department updated');
    }

    if (isset($_POST['delete_department'])) {
        $id = intval($_POST['id']);
        if ($departmentModel->delete($id)) {
            flash_redirect('../views/manage_departments.php', 'success', 'Department deleted');
        } else {
            flash_redirect('../views/manage_departments.php', 'error', 'Cannot delete department in use');
        }
    }
}