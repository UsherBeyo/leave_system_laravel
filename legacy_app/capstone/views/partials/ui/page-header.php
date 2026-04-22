<?php
// Usage:
//  $title = 'Page Title';
//  $subtitle = 'Optional subtitle';
//  $actions = [ '<a href="#" class="btn btn-primary">Add</a>' ];
?>
<div class="page-header">
    <div class="page-title-group">
        <h1 class="page-title"><?= htmlspecialchars($title) ?></h1>
        <?php if (!empty($subtitle)): ?>
            <p class="page-subtitle"><?= htmlspecialchars($subtitle) ?></p>
        <?php endif; ?>
    </div>
    <?php if (!empty($actions) && is_array($actions)): ?>
        <div class="page-actions">
            <?php foreach ($actions as $a): ?>
                <?= $a ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
