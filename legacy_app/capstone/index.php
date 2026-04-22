<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/helpers/Auth.php';
Auth::sendNoCacheHeaders();

if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard');
    exit();
}

header('Location: login');
exit();
