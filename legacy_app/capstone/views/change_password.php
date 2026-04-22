<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../config/database.php';
require_once '../helpers/Auth.php';
Auth::requireLogin('login.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html>
<head>
    <base href="<?= htmlspecialchars(rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/', ENT_QUOTES, 'UTF-8'); ?>">
    <title>Change Password</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>
<?php include __DIR__ . '/partials/sidebar.php'; ?>

<div class="app-main">
    <div class="ui-card">
        <h2>Change Password</h2>
        <p class="page-subtitle" style="margin-bottom:14px;">Update your login credentials</p>
        <form method="POST" action="../controllers/UserController.php">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="action" value="change_password">
            <label>Current Password</label>
            <input type="password" name="current" required>
            <label>New Password</label>
            <input type="password" name="new" required minlength="6">
            <br><br>
            <button type="submit">Update</button>
        </form>
    </div>
</div>
</body>
</html>
