<?php

declare(strict_types=1);

echo $view->render('components.page-hero', compact('page', 'e'));
?>
<section class="section">
    <div class="container-wide">
        <div class="section-heading centered">
            <p class="eyebrow">Professional directory</p>
            <h2>Areas of professional practice.</h2>
            <p>Areas, connected programmes and coordinators are maintained in the official catalogue.</p>
        </div>
        <?php if (!$directoryAvailable): ?><div class="notice" role="status">The professional area directory is temporarily unavailable.</div>
        <?php elseif ($professionalAreas === []): ?><div class="empty-state"><h2>No professional areas are currently published.</h2></div>
        <?php else: ?>
        <div class="area-grid area-grid-large">
            <?php foreach ($professionalAreas as $area): ?>
                <article class="area-card">
                    <span><?= $e($area['code'] ?: 'AREA') ?></span>
                    <h2><a href="/professional-areas/<?= rawurlencode($area['slug']) ?>"><?= $e($area['name']) ?></a></h2>
                    <p><?= $e($area['description'] ?: 'Approved area information will be published here.') ?></p>
                    <span class="programme-meta"><?= count($area['programmes']) ?> published programme<?= count($area['programmes']) === 1 ? '' : 's' ?></span>
                </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

