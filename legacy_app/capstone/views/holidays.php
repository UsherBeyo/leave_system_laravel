<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../config/database.php';
require_once '../helpers/Auth.php';
Auth::requireLogin('login.php');
require_once '../helpers/Flash.php';
require_once '../helpers/Pagination.php';

if (empty($_SESSION['user_id'])) {
    flash_redirect('login.php', 'warning', 'Please log in first.');
}

if (!in_array($_SESSION['role'] ?? '', ['admin','manager','hr','personnel'], true)) {
    $redirect = !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'dashboard.php';
    flash_redirect($redirect, 'error', 'Access Denied!');
}

$db = (new Database())->connect();
$hols = $db->query("SELECT * FROM holidays ORDER BY holiday_date DESC")->fetchAll(PDO::FETCH_ASSOC);
$holidaySearch = trim((string)($_GET['q'] ?? ''));
$hols = pagination_filter_array($hols, $holidaySearch, ['holiday_date', 'description', 'type']);
$holidaysPagination = paginate_array($hols, (int)($_GET['page'] ?? 1), 10);
$hols = $holidaysPagination['items'];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html>
<head>
    <base href="<?= htmlspecialchars(rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/', ENT_QUOTES, 'UTF-8'); ?>">
    <title>Holidays</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .holidays-page { background: none; }
        .holidays-create-form {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 14px;
            align-items: end;
            margin-bottom: 16px;
        }
        .holidays-form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .holidays-form-group label {
            font-size: 13px;
            font-weight: 600;
            color: #374151;
        }
        .holidays-form-group input,
        .holidays-form-group select {
            width: 100%;
            min-height: 36px;
            border: 1px solid #dbe2ec;
            border-radius: 8px;
            padding: 8px 10px;
            background: #f8fafc;
            color: #111827;
            font-size: 14px;
        }
        .holidays-create-actions {
            grid-column: 1 / -1;
            display: flex;
            justify-content: flex-start;
            gap: 8px;
            margin-top: 4px;
        }
        .holidays-create-actions .btn {
            padding: 10px 18px;
            font-weight: 600;
        }
        .holiday-action-cell {
            padding: 8px 0;
        }
        .holiday-actions {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }
        .holiday-update-form {
            display: flex;
            gap: 6px;
            align-items: center;
            flex: 1;
        }
        .holiday-update-form input[type="date"] {
            width: 110px;
            padding: 6px 8px;
        }
        .holiday-update-form input[type="text"] {
            flex: 1;
            min-width: 140px;
            padding: 6px 8px;
        }
        .holiday-update-form select {
            width: 140px;
            padding: 6px 8px;
        }
        .holiday-update-form button {
            padding: 6px 12px;
            font-size: 13px;
            font-weight: 600;
        }
        .holiday-delete-form {
            flex-shrink: 0;
        }
        .holiday-delete-form .btn {
            padding: 6px 12px;
            font-size: 13px;
        }
        @media (max-width: 1024px) {
            .holidays-create-form { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 640px) {
            .holidays-create-form { grid-template-columns: 1fr; }
            .holiday-actions { flex-direction: column; width: 100%; }
            .holiday-update-form { flex-direction: column; width: 100%; }
            .holiday-update-form input,
            .holiday-update-form select { width: 100% !important; }
        }
    </style>
    <script src="../assets/js/script.js"></script>
</head>
<body>
<?php include __DIR__ . '/partials/sidebar.php'; ?>

<div class="app-main">
    <?php
    $title = 'Manage Holidays';
    $subtitle = 'Configure holiday dates used by the leave calendar';
    include __DIR__ . '/partials/ui/page-header.php';
    ?>
    <div class="ui-card ajax-fragment" data-fragment-id="holidays-list" data-page-param="page" data-search-param="q">
        <h2>Manage Holidays</h2>
        <div class="fragment-toolbar">
            <div class="search-input">
                <input class="form-control live-search-input" type="text" name="q" value="<?= htmlspecialchars($holidaySearch, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search date, description, or type...">
            </div>
            <div class="fragment-summary">Showing <?= $holidaysPagination['from']; ?>–<?= $holidaysPagination['to']; ?> of <?= $holidaysPagination['total']; ?> holiday entries.</div>
        </div>
        <form method="POST" action="../controllers/HolidayController.php" class="holidays-create-form">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
            <div class="holidays-form-group">
                <label>Date</label>
                <input type="date" name="date" required class="form-control">
            </div>
            <div class="holidays-form-group">
                <label>Type</label>
                <select name="type" class="form-select">
                    <option value="Non-working Holiday">Non-working Holiday</option>
                    <option value="Special Working Holiday">Special Working Holiday</option>
                    <option value="Company Event">Company Event</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div class="holidays-form-group">
                <label>Description</label>
                <input type="text" name="description" class="form-control">
            </div>
            <div class="holidays-create-actions">
                <button type="submit" name="add" class="btn btn-primary">Add Holiday</button>
            </div>
        </form>
        <div class="table-wrap" style="margin-top:24px;">
            <table class="ui-table">
                <thead>
                <tr><th>Date</th><th>Description</th><th>Type</th><th>Action</th></tr>
                </thead>
                <tbody>
            <?php foreach($hols as $h): ?>
            <tr>
                <td><?= $h['holiday_date']; ?></td>
                <td><?= htmlspecialchars($h['description'] ?? ''); ?></td>
                <td><?= htmlspecialchars($h['type'] ?? 'Other'); ?></td>
                <td class="holiday-action-cell">
                    <div class="holiday-actions">
                        <form method="POST" action="../controllers/HolidayController.php" class="holiday-update-form">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="id" value="<?= $h['id']; ?>">
                            <input type="hidden" name="update" value="1">
                            <input type="date" name="date" value="<?= $h['holiday_date']; ?>" required>
                            <input type="text" name="description" value="<?= htmlspecialchars($h['description'] ?? ''); ?>">
                            <select name="type">
                                <option value="Non-working Holiday" <?= ($h['type'] === 'Non-working Holiday' ? 'selected' : ''); ?>>Non-working Holiday</option>
                                <option value="Special Working Holiday" <?= ($h['type'] === 'Special Working Holiday' ? 'selected' : ''); ?>>Special Working Holiday</option>
                                <option value="Company Event" <?= ($h['type'] === 'Company Event' ? 'selected' : ''); ?>>Company Event</option>
                                <option value="Other" <?= ($h['type'] === 'Other' || empty($h['type']) ? 'selected' : ''); ?>>Other</option>
                            </select>
                            <button style="padding: 12px;" type="submit">Update</button>
                        </form>
                        <form method="POST" action="../controllers/HolidayController.php" class="holiday-delete-form">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="id" value="<?= $h['id']; ?>">
                            <button type="submit" name="delete" class="btn btn-danger">Delete</button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= pagination_render($holidaysPagination, 'page'); ?>
    </div>
</div>
</body>
</html>
