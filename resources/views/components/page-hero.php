<?php

declare(strict_types=1);
?>
<section class="page-hero">
    <div class="page-hero-pattern" aria-hidden="true"></div>
    <div class="container-narrow">
        <nav class="breadcrumb-nav" aria-label="Breadcrumb">
            <ol>
                <li><a href="/">Home</a></li>
                <li aria-current="page"><?= $e($page['title']) ?></li>
            </ol>
        </nav>
        <p class="eyebrow eyebrow-light"><?= $e($page['eyebrow']) ?></p>
        <h1><?= $e($page['heading']) ?></h1>
        <p class="page-hero-summary"><?= $e($page['summary']) ?></p>
    </div>
</section>

