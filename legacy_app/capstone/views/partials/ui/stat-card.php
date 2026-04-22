<?php
// Variables expected:
//  $icon (html string),
//  $title (string),
//  $value (string/number),
//  $delta (optional string),
//  $theme (optional extra class to tweak color)
?>
<div class="stat-card <?= isset($theme) ? htmlspecialchars($theme) : '' ?>">
    <?php if (!empty($icon)): ?>
        <div class="stat-card-icon"><?= $icon ?></div>
    <?php endif; ?>
    <div>
        <div class="stat-card-value"><?= htmlspecialchars($value) ?></div>
        <?php if (!empty($title)): ?>
            <div class="stat-card-label"><?= htmlspecialchars($title) ?></div>
        <?php endif; ?>
        <?php if (!empty($delta)): ?>
            <div class="stat-card-delta"><?= htmlspecialchars($delta) ?></div>
        <?php endif; ?>
    </div>
</div>
