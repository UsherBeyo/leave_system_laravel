<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../config/database.php';
require_once '../helpers/Flash.php';

if (!in_array($_SESSION['role'] ?? '', ['personnel','admin','hr'], true)) {
    die("Access denied");
}

if (
    !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    die("CSRF validation failed.");
}

$db = (new Database())->connect();

$ids = $_POST['id'] ?? [];
$names = $_POST['name'] ?? [];
$positions = $_POST['position'] ?? [];

if (!is_array($ids) || !is_array($names) || !is_array($positions)) {
    die("Invalid request.");
}

$stmt = $db->prepare("
    UPDATE system_signatories
    SET name = ?, position = ?
    WHERE id = ?
");

$count = min(count($ids), count($names), count($positions));

for ($i = 0; $i < $count; $i++) {
    $id = (int)$ids[$i];
    $name = trim((string)$names[$i]);
    $position = trim((string)$positions[$i]);

    if ($id <= 0 || $name === '' || $position === '') {
        continue;
    }

    $stmt->execute([$name, $position, $id]);
}

flash_redirect('../views/signatories_settings.php', 'success', 'Signatories updated successfully');