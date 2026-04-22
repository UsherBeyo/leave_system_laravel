<?php
// expects $actions to be array of ['icon'=>'<i>','href'=>'...','class'=>'btn-...','title'=>'...']
if (empty($actions) || !is_array($actions)) return;
?>
<div class="action-group">
    <?php foreach ($actions as $act): ?>
        <a href="<?= htmlspecialchars($act['href'] ?? '#') ?>" class="action-btn <?= htmlspecialchars($act['class'] ?? '') ?>" title="<?= htmlspecialchars($act['title'] ?? '') ?>">
            <?= $act['icon'] ?? '' ?>
        </a>
    <?php endforeach; ?>
</div>
