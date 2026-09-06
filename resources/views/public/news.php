<?php

declare(strict_types=1);

echo $view->render('components.page-hero', compact('page', 'e'));
?>
<section class="section">
    <div class="container-narrow">
        <?= $view->render('components.empty-state', [
            'label' => 'News room ready',
            'title' => 'No official articles have been published yet.',
            'message' => 'News items will appear here after they are reviewed and released through the future content-management workflow.',
            'linkPath' => '/resources',
            'linkLabel' => 'Explore the resource library',
            'e' => $e,
        ]) ?>
    </div>
</section>

<section class="section section-warm">
    <div class="container-wide">
        <div class="editorial-note">
            <p class="eyebrow">Editorial standard</p>
            <h2>Official updates should be easy to recognise.</h2>
            <p>Published news will carry a clear title, publication date, summary and permanent link. Draft or unverified announcements will not be presented as official information.</p>
        </div>
    </div>
</section>

