<?php

declare(strict_types=1);

echo $view->render('components.page-hero', compact('page', 'e'));
?>
<section class="section"><div class="container-wide">
    <div class="section-heading centered"><p class="eyebrow">Programme catalogue</p><h2>Approved professional learning pathways.</h2><p>Only published programmes appear here. Programme applications are not yet available.</p></div>
    <?php if (!$catalogueAvailable): ?><div class="notice" role="status">The programme catalogue is temporarily unavailable.</div>
    <?php elseif ($programmes === []): ?><div class="empty-state"><h2>No programmes are currently published.</h2><p>The approved programme types are listed below while verified programme details are prepared.</p></div>
    <?php else: ?><div class="programme-grid"><?php foreach ($programmes as $programme): ?>
        <article class="programme-card"><div class="programme-card-top"><span><?= $e($programme['code']) ?></span><span class="status-pill"><?= $e($programme['type']['name']) ?></span></div><h2><a href="/programmes/<?= rawurlencode($programme['slug']) ?>"><?= $e($programme['name']) ?></a></h2><p><?= $e($programme['description'] ?: 'Verified programme details will be published here.') ?></p><?php if ($programme['duration']): ?><span class="programme-meta"><?= $e($programme['duration']) ?></span><?php endif; ?></article>
    <?php endforeach; ?></div><?php endif; ?>
</div></section>
<section class="section section-warm"><div class="container-wide"><div class="section-heading"><p class="eyebrow">Programme types</p><h2>Configured pathways</h2></div><div class="area-grid compact-area-grid">
    <?php foreach ($programmeTypes as $type): ?><article class="area-card area-card-static"><strong><?= $e($type['name']) ?></strong><?php if ($type['description']): ?><p><?= $e($type['description']) ?></p><?php endif; ?></article><?php endforeach; ?>
</div></div></section>
