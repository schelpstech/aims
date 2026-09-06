<?php

declare(strict_types=1);

echo $view->render('components.page-hero', compact('page', 'e'));
?>
<section class="section">
    <div class="container-wide">
        <div class="section-heading">
            <p class="eyebrow">Event categories</p>
            <h2>A flexible calendar for different forms of professional engagement.</h2>
        </div>
        <div class="tag-cloud" aria-label="Supported event categories">
            <?php foreach ($eventTypes as $eventType): ?>
                <span><?= $e($eventType) ?></span>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section section-warm">
    <div class="container-narrow">
        <?= $view->render('components.empty-state', [
            'label' => 'Calendar awaiting publication',
            'title' => 'No verified events have been published yet.',
            'message' => 'Approved event dates, venues, online links, eligibility, capacity and fees will appear here when the event-management stage is complete.',
            'linkPath' => '/contact',
            'linkLabel' => 'View official contact channels',
            'e' => $e,
        ]) ?>
    </div>
</section>

