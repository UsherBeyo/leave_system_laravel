<?php
Auth::startSession();
require_once '../config/database.php';
require_once '../helpers/Flash.php';
require_once '../helpers/Auth.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../views/login.php');
    exit();
}

$db = (new Database())->connect();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        flash_redirect('../views/dashboard.php', 'error', 'Invalid request. Please try again.');
    }

    if (($_POST['action'] ?? '') === 'update_profile_picture') {
        $sessionEmpId = (int)($_SESSION['emp_id'] ?? 0);
        $employeeId = (int)($_POST['employee_id'] ?? 0);

        if ($sessionEmpId <= 0 || $employeeId !== $sessionEmpId) {
            flash_redirect('../views/dashboard.php', 'error', 'Access Denied');
        }

        if (!isset($_FILES['profile_pic']) || !is_array($_FILES['profile_pic']) || (int)($_FILES['profile_pic']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            flash_redirect('../views/employee_profile.php?id=' . $sessionEmpId, 'error', 'Please choose an image to upload.');
        }

        $file = $_FILES['profile_pic'];
        if ((int)($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            flash_redirect('../views/employee_profile.php?id=' . $sessionEmpId, 'error', 'Unable to upload profile picture.');
        }

        $maxBytes = 2 * 1024 * 1024;
        if ((int)($file['size'] ?? 0) > $maxBytes) {
            flash_redirect('../views/employee_profile.php?id=' . $sessionEmpId, 'error', 'Profile picture must be 2MB or smaller.');
        }

        $originalName = (string)($file['name'] ?? '');
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($ext, $allowedExt, true)) {
            flash_redirect('../views/employee_profile.php?id=' . $sessionEmpId, 'error', 'Allowed formats: JPG, PNG, GIF, WEBP.');
        }

        $allowedMime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $mime = '';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = (string)finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
            }
        }
        if ($mime !== '' && !in_array($mime, $allowedMime, true)) {
            flash_redirect('../views/employee_profile.php?id=' . $sessionEmpId, 'error', 'The uploaded file is not a valid image.');
        }

        $uploadDir = dirname(__DIR__) . '/uploads';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0775, true);
        }

        $newName = uniqid('profile_', true) . '.' . $ext;
        $absolutePath = $uploadDir . '/' . $newName;
        $relativePath = '../uploads/' . $newName;

        if (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
            flash_redirect('../views/employee_profile.php?id=' . $sessionEmpId, 'error', 'Failed to save the uploaded image.');
        }

        $stmt = $db->prepare('UPDATE employees SET profile_pic = ? WHERE id = ? AND user_id = ?');
        $stmt->execute([$relativePath, $sessionEmpId, (int)$_SESSION['user_id']]);

        if ((int)$stmt->rowCount() < 1) {
            @unlink($absolutePath);
            flash_redirect('../views/dashboard.php', 'error', 'Unable to update profile picture.');
        }

        flash_redirect('../views/employee_profile.php?id=' . $sessionEmpId, 'success', 'Profile picture updated successfully.');
    }

    if (($_POST['action'] ?? '') === 'change_password') {
        $userId = $_SESSION['user_id'];
        $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && password_verify($_POST['current'], $row['password'])) {
            $hash = password_hash($_POST['new'], PASSWORD_DEFAULT);
            $db->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $userId]);
            flash_set('success', 'Password updated');
        } else {
            flash_set('error', 'Current password incorrect');
        }
        flash_redirect('../views/dashboard.php');
    }
}
