<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../config/database.php';
require_once '../helpers/Auth.php';
Auth::requireLogin('login.php');

if ($_SESSION['role'] !== 'admin') {
    die("Access denied");
}

$db = (new Database())->connect();
// employees per department
$deptStmt = $db->query("SELECT department, COUNT(*) as cnt FROM employees GROUP BY department");
$depts = $deptStmt->fetchAll(PDO::FETCH_ASSOC);

// employees per role (join with users)
$roleStmt = $db->query("SELECT u.role, COUNT(*) as cnt FROM users u
                       JOIN employees e ON e.user_id = u.id
                       GROUP BY u.role");
$roles = $roleStmt->fetchAll(PDO::FETCH_ASSOC);

// total employees
$total = $db->query("SELECT COUNT(*) FROM employees")->fetchColumn();

?>
<!DOCTYPE html>
<html>
<head>
    <base href="<?= htmlspecialchars(rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/', ENT_QUOTES, 'UTF-8'); ?>">
    <title>Statistics</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>
<?php include __DIR__ . '/partials/sidebar.php'; ?>

<div class="app-main">
    <h2>System Statistics</h2>
    <p class="page-subtitle" style="margin-bottom:14px;">System and department-wide analytics</p>

    <div class="ui-card">
        <h3>Total employees</h3>
        <p><?= $total ?></p>
    </div>

    <div class="ui-card">
        <h3>By department</h3>
        <table border="1">
            <tr><th>Department</th><th>Count</th></tr>
            <?php foreach($depts as $d): ?>
            <tr><td><?= htmlspecialchars($d['department']); ?></td><td><?= $d['cnt']; ?></td></tr>
            <?php endforeach; ?>
        </table>
    </div>

    <div class="ui-card" style="margin-top:24px;">
        <h3>By role</h3>
        <table border="1">
            <tr><th>Role</th><th>Count</th></tr>
            <?php foreach($roles as $r): ?>
            <tr><td><?= htmlspecialchars($r['role']); ?></td><td><?= $r['cnt']; ?></td></tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
</body>
</html>
