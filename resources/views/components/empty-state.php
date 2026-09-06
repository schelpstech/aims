<?php

declare(strict_types=1);
?>
<div class="empty-state" role="status">
    <span class="status-pill"><?= $e($label ?? 'In preparation') ?></span>
    <h2><?= $e($title) ?></h2>
    <p><?= $e($message) ?></p>
    <?php if (!empty($linkPath) && !empty($linkLabel)): ?>
        <a class="text-link" href="<?= $e($linkPath) ?>"><?= $e($linkLabel) ?> <span aria-hidden="true">→</span></a>
    <?php endif; ?>
</div>

