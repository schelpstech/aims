<?php

declare(strict_types=1);

echo $view->render('components.page-hero', compact('page', 'e'));
?>
<section class="section">
    <div class="container-wide">
        <div class="resource-grid">
            <?php
            $resourceTypes = [
                ['Publications', 'Reviewed articles and formal publications.'],
                ['Professional guidance', 'Approved guidance organised by topic and professional area.'],
                ['Downloads', 'Official documents made available for public access.'],
                ['Frequently asked questions', 'Clear answers to common enquiries.'],
            ];
            ?>
            <?php foreach ($resourceTypes as $index => [$title, $description]): ?>
                <article class="resource-card">
                    <span class="resource-icon" aria-hidden="true"><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
                    <h2><?= $e($title) ?></h2>
                    <p><?= $e($description) ?></p>
                    <span class="status-pill">Awaiting content</span>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section section-warm">
    <div class="container-narrow">
        <?= $view->render('components.empty-state', [
            'label' => 'Library in preparation',
            'title' => 'No public resources are available yet.',
            'message' => 'The resource library will display only approved content with a clear title, category and publication record.',
            'linkPath' => '/news',
            'linkLabel' => 'Visit the news room',
            'e' => $e,
        ]) ?>
    </div>
</section>

