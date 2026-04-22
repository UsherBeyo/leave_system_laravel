<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../config/database.php';
require_once '../helpers/Auth.php';
Auth::requireLogin('login.php');

// admin/hr/manager can edit any employee, employees can edit their own profile
$role = $_SESSION['role'] ?? '';
$emp_id = $_SESSION['emp_id'] ?? 0;

if (!in_array($role, ['admin','hr','manager','employee'])) {
    die("Access denied");
}

if (!isset($_GET['id'])) {
    header("Location: manage_employees.php");
    exit();
}

$id_to_edit = intval($_GET['id']);

// if employee, check they're editing their own profile
if ($role === 'employee') {
    if ($emp_id !== $id_to_edit) {
        die("You can only edit your own profile");
    }
} elseif (!in_array($role, ['admin','hr','manager'])) {
    die("Access denied");
}

$db = (new Database())->connect();
$stmt = $db->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->execute([$id_to_edit]);
$e = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$e) {
    header("Location: manage_employees.php");
    exit();
}

$departments = $db->query("SELECT * FROM departments ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

$managers = $db->query("SELECT e.id, e.first_name, e.last_name
    FROM employees e
    JOIN users u ON e.user_id = u.id
    WHERE u.role = 'manager'")->fetchAll(PDO::FETCH_ASSOC);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$is_own_profile = ($role === 'employee');
?>
<!DOCTYPE html>
<html>
<head>
    <base href="<?= htmlspecialchars(rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/', ENT_QUOTES, 'UTF-8'); ?>">
    <title>Edit Employee</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>
<?php include __DIR__ . '/partials/sidebar.php'; ?>

<div class="app-main">
    <div class="ui-card">
        <h2>Edit Employee</h2>
        <p class="page-subtitle" style="margin-bottom:14px;">Update employee information and assignments</p>
        <form method="POST" action="../controllers/AdminController.php" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="update_employee" value="1">
            <input type="hidden" name="employee_id" value="<?= $e['id']; ?>">

            <?php if(!empty($e['profile_pic'])): ?>
                <img src="<?= $e['profile_pic']; ?>" alt="Profile" style="width:100px; height:100px; object-fit:cover; border-radius:50%;cursor:pointer;" onclick="openImageModal('<?= htmlspecialchars($e['profile_pic']); ?>', '<?= htmlspecialchars($e['first_name'].' '.$e['last_name']); ?>')"><br>
            <?php endif; ?>
            <label>Profile Picture</label>
            <input type="file" name="profile_pic" accept="image/*">
            <label>First Name</label>
            <input type="text" name="first_name" value="<?= htmlspecialchars($e['first_name']); ?>" required>
            <label>Last Name</label>
            <input type="text" name="last_name" value="<?= htmlspecialchars($e['last_name']); ?>" required>
            <label>Department</label>
            <select name="department_id" required>
                <option value="">Select Department</option>
                <?php foreach($departments as $d): ?>
                    <option value="<?= $d['id']; ?>" <?= ($e['department_id'] == $d['id']) ? 'selected' : ''; ?>><?= htmlspecialchars($d['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <label>Position</label>
            <input type="text" name="position" value="<?= htmlspecialchars($e['position'] ?? ''); ?>">
            <label>Status</label>
            <input type="text" name="status" value="<?= htmlspecialchars($e['status'] ?? ''); ?>">
            <label>Civil Status</label>
            <input type="text" name="civil_status" value="<?= htmlspecialchars($e['civil_status'] ?? ''); ?>">
            <label>Entrance to Duty</label>
            <input type="date" name="entrance_to_duty" value="<?= htmlspecialchars($e['entrance_to_duty'] ?? ''); ?>">
            <label>Unit</label>
            <input type="text" name="unit" value="<?= htmlspecialchars($e['unit'] ?? ''); ?>">
            <label>GSIS Policy No.</label>
            <input type="text" name="gsis_policy_no" value="<?= htmlspecialchars($e['gsis_policy_no'] ?? ''); ?>">
            <label>National Reference Card No.</label>
            <input type="text" name="national_reference_card_no" value="<?= htmlspecialchars($e['national_reference_card_no'] ?? ''); ?>">
            
            <?php if(!$is_own_profile): ?>
            <label>Vacational Balance</label>
            <input type="number" step="0.001" name="annual_balance" value="<?= number_format($e['annual_balance'],3); ?>">
            <label>Sick Balance</label>
            <input type="number" step="0.001" name="sick_balance" value="<?= number_format($e['sick_balance'],3); ?>">
            <label>Force Balance</label>
            <input type="number" name="force_balance" value="<?= $e['force_balance']; ?>">
            <label>Assign Manager</label>
            <select name="manager_id">
                <option value="">None</option>
                <?php foreach($managers as $m): ?>
                    <option value="<?= $m['id']; ?>" <?php if($e['manager_id']==$m['id']) echo 'selected'; ?>>
                        <?= $m['first_name'].' '.$m['last_name']; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>

            <button type="submit">Save changes</button>
        </form>
    </div>
</div>

<div id="imageModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.8);z-index:2000;justify-content:center;align-items:center;flex-direction:column;">
    <span style="color:white;font-size:20px;margin-bottom:24px;" id="modalImageName"></span>
    <img id="modalImage" style="max-width:80%;max-height:80%;border-radius:8px;">
    <button onclick="closeImageModal()" style="margin-top:20px;padding:10px 20px;background:var(--primary);color:white;border:none;border-radius:4px;cursor:pointer;">Close</button>
</div>

<script>
function openImageModal(src, name) {
    document.getElementById('modalImage').src = src;
    document.getElementById('modalImageName').textContent = name;
    document.getElementById('imageModal').style.display = 'flex';
}

function closeImageModal() {
    document.getElementById('imageModal').style.display = 'none';
}

document.getElementById('imageModal').addEventListener('click', function(e) {
    if(e.target === this) closeImageModal();
});
</script>

</body>
</html>
