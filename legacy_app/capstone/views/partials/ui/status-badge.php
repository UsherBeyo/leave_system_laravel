<?php
// expects $status variable (string)
if (!isset($status)) {
    return;
}
$clean = strtolower(trim($status));
$class = 'badge badge-' . htmlspecialchars($clean);
?>
<span class="<?= $class ?>"><?= htmlspecialchars(ucfirst($clean)) ?></span>
