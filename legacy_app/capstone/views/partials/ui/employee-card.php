<?php
// expects variables: $name, $employeeId, $email, $phone, $department, $position, $status, $editLink, $primaryAction (html)
// $initials can be provided, otherwise derive from name
$initials = $initials ?? '';
if (!$initials && !empty($name)) {
    $parts = preg_split('/\s+/', $name);
    $initials = strtoupper(substr($parts[0],0,1) . (isset($parts[1]) ? substr($parts[1],0,1) : ''));
}
?>
<div class="employee-card ui-card">
    <div class="employee-avatar"><?= htmlspecialchars($initials) ?></div>
    <div class="employee-details">
        <div class="employee-name"><?= htmlspecialchars($name) ?></div>
        <div class="employee-meta"><?= htmlspecialchars($department) ?> &bull; <?= htmlspecialchars($position) ?></div>
        <div class="employee-contact"><?= htmlspecialchars($email) ?><?php if(!empty($phone)): ?> &bull; <?= htmlspecialchars($phone) ?><?php endif; ?></div>
    </div>
    <div class="employee-status">
        <?php if (isset($status)): ?>
            <?php $status = $status; include __DIR__ . '/status-badge.php'; ?>
        <?php endif; ?>
    </div>
    <div class="employee-actions">
        <?php if (!empty($primaryAction)) echo $primaryAction; ?>
        <?php if (!empty($editLink)): ?>
            <a href="<?= htmlspecialchars($editLink) ?>" class="btn btn-ghost btn-icon"><i class="icon-edit"></i></a>
        <?php endif; ?>
    </div>
</div>
