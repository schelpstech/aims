<?php

declare(strict_types=1);

echo $view->render('components.page-hero', compact('page', 'e'));
?>
<section class="section">
    <div class="container-narrow">
        <div class="content-split">
            <div>
                <p class="eyebrow">Who we are</p>
                <h2>A professional association experience designed for clarity and continuity.</h2>
            </div>
            <div class="prose">
                <p>The Association for Information and Management Sciences, Nigeria is establishing a secure digital platform for public information, membership, professional programmes, events, resources and verification services.</p>
                <p>This website is the public foundation of that wider platform. It will grow through approved stages while keeping institutional content configurable, traceable and easy to maintain.</p>
            </div>
        </div>
    </div>
</section>

<section class="section section-warm">
    <div class="container-wide">
        <div class="section-heading centered">
            <p class="eyebrow">Platform scope</p>
            <h2>Connected services without a fragmented experience.</h2>
            <p>Each area has a clear place in the wider platform architecture.</p>
        </div>
        <div class="feature-grid">
            <?php
            $features = [
                ['Membership', 'Applications, renewals and member services will be introduced through secure stages.'],
                ['Programmes', 'Approved professional programmes and enrolment information will be database-driven.'],
                ['Professional Areas', 'A flexible directory will connect disciplines, learning and future resources.'],
                ['Events', 'Official events and registration opportunities will use a structured publication workflow.'],
                ['Resources & News', 'Reviewed content will be clearly dated, categorised and published.'],
                ['Verification', 'Public checks will disclose only appropriate information through secure identifiers.'],
            ];
            ?>
            <?php foreach ($features as $index => [$title, $description]): ?>
                <article class="feature-card">
                    <span class="feature-icon" aria-hidden="true"><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
                    <h3><?= $e($title) ?></h3>
                    <p><?= $e($description) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section">
    <div class="container-narrow">
        <div class="link-panel">
            <div>
                <p class="eyebrow">Institutional information</p>
                <h2>Continue exploring AIMS Nigeria.</h2>
            </div>
            <div class="link-panel-links">
                <a href="/vision-mission">Vision &amp; Mission <span aria-hidden="true">→</span></a>
                <a href="/leadership">Leadership structure <span aria-hidden="true">→</span></a>
                <a href="/contact">Official contact channels <span aria-hidden="true">→</span></a>
            </div>
        </div>
    </div>
</section>

