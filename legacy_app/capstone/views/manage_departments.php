<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../config/database.php';
require_once '../helpers/Auth.php';
require_once '../helpers/Pagination.php';
Auth::requireLogin('login.php');

if ($_SESSION['role'] !== 'admin') {
    die("Access denied");
}

$db = (new Database())->connect();
require_once '../models/Department.php';
$departmentModel = new Department($db);

$departments = $departmentModel->getAll();
$departmentSearch = trim((string)($_GET['q'] ?? ''));
$departments = pagination_filter_array($departments, $departmentSearch, ['id', 'name']);
$departmentsPagination = paginate_array($departments, (int)($_GET['page'] ?? 1), 10);
$departments = $departmentsPagination['items'];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html>
<head>
    <base href="<?= htmlspecialchars(rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/', ENT_QUOTES, 'UTF-8'); ?>">
    <title>Manage Departments</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <script src="../assets/js/script.js"></script>
</head>
<body>

<?php include __DIR__ . '/partials/sidebar.php'; ?>

<div class="app-main">
    <?php
    $title = 'Manage Departments';
    $subtitle = 'Manage department structure and employee assignments';
    $actions = ['<button id="openCreateModal" class="btn btn-primary">+ New Department</button>'];
    include __DIR__ . '/partials/ui/page-header.php';
    ?>


    <div id="createModal" class="modal" style="display:none;">
        <div class="modal-content small">
            <span class="modal-close" id="closeCreateModal">&times;</span>
            <h3>Create Department</h3>
            <form method="POST" action="../controllers/DepartmentController.php">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                    <div class="form-group">
                    <label>Name</label>
                    <input type="text" name="name" required class="form-control">
                </div>
                <div style="text-align:right;">
                    <button type="submit" name="create_department">Create</button>
                </div>
            </form>
        </div>
    </div>

    <div class="ui-card ajax-fragment" data-fragment-id="departments-list" data-page-param="page" data-search-param="q" style="margin-top:30px;">
        <h2>Departments</h2>
        <div class="fragment-toolbar">
            <div class="search-input">
                <input class="form-control live-search-input" type="text" name="q" value="<?= htmlspecialchars($departmentSearch, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search departments...">
            </div>
            <div class="fragment-summary">Showing <?= $departmentsPagination['from']; ?>–<?= $departmentsPagination['to']; ?> of <?= $departmentsPagination['total']; ?> departments.</div>
        </div>
        <div class="table-wrap">
            <table class="ui-table">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
            <?php foreach($departments as $d): ?>
            <tr>
                <td><?= $d['id']; ?></td>
                <td><?= htmlspecialchars($d['name']); ?></td>
                <td>
                    <button class="edit-btn" data-id="<?= $d['id']; ?>" data-name="<?= htmlspecialchars($d['name']); ?>">Edit</button>
                    <form method="POST" action="../controllers/DepartmentController.php" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="id" value="<?= $d['id']; ?>">
                        <button type="submit" name="delete_department" class="btn btn-danger" onclick="return confirm('Delete this department?')">Delete</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= pagination_render($departmentsPagination, 'page'); ?>
    </div>

    <div id="editModal" class="modal" style="display:none;">
        <div class="modal-content small">
            <span class="modal-close" id="closeEditModal">&times;</span>
            <h3>Edit Department</h3>
            <form method="POST" action="../controllers/DepartmentController.php">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="id" id="editId">
                <div class="form-group">
                    <label>Name</label>
                    <input type="text" name="name" id="editName" required class="form-control">
                </div>
                <div style="text-align:right;">
                    <button type="submit" name="update_department">Update</button>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
document.getElementById('openCreateModal').addEventListener('click', function(e){
    e.preventDefault();
    document.getElementById('createModal').style.display = 'flex';
});
document.getElementById('closeCreateModal').addEventListener('click', function(){
    document.getElementById('createModal').style.display = 'none';
});
window.addEventListener('click', function(e){
    if(e.target == document.getElementById('createModal')) document.getElementById('createModal').style.display = 'none';
});

document.addEventListener('click', function(e){
    const btn = e.target.closest('.edit-btn');
    if (!btn) return;
    const id = btn.getAttribute('data-id');
    const name = btn.getAttribute('data-name');
    document.getElementById('editId').value = id;
    document.getElementById('editName').value = name;
    document.getElementById('editModal').style.display = 'flex';
});
document.getElementById('closeEditModal').addEventListener('click', function(){
    document.getElementById('editModal').style.display = 'none';
});
window.addEventListener('click', function(e){
    if(e.target == document.getElementById('editModal')) document.getElementById('editModal').style.display = 'none';
});
</script>

</body>
</html>
