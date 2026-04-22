<?php
// expects $tabs to be an array of ['label'=>..., 'href'=>..., 'active'=>bool]
if (empty($tabs) || !is_array($tabs)) return;
?>
<div class="filter-tabs">
    <?php foreach ($tabs as $tab): ?>
        <a href="<?= htmlspecialchars($tab['href'] ?? '#') ?>" class="filter-tab<?= !empty($tab['active']) ? ' is-active' : '' ?>">
            <?= htmlspecialchars($tab['label']) ?>
        </a>
    <?php endforeach; ?>
</div>
