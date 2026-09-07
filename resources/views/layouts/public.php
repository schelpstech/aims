<?php

declare(strict_types=1);

$metaTitle = $page['meta_title'] ?? ($page['title'] . ' | ' . $site['short_name']);
$canonicalBase = rtrim((string) ($site['website'] ?? ''), '/');
$canonical = preg_match('#^https?://#i', $canonicalBase) === 1
    ? $canonicalBase . ($requestPath === '/' ? '' : $requestPath)
    : '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#07182f">
    <meta name="description" content="<?= $e($page['description']) ?>">
    <meta name="robots" content="<?= $e($page['robots'] ?? 'index, follow') ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= $e($site['short_name']) ?>">
    <meta property="og:title" content="<?= $e($metaTitle) ?>">
    <meta property="og:description" content="<?= $e($page['description']) ?>">
    <?php if ($canonical !== ''): ?>
        <link rel="canonical" href="<?= $e($canonical) ?>">
        <meta property="og:url" content="<?= $e($canonical) ?>">
    <?php endif; ?>
    <title><?= $e($metaTitle) ?></title>
    <link rel="stylesheet" href="/assets/vendor/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/css/site.css?v=20260907.2">
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to main content</a>
    <?= $view->render('components.header', compact('site', 'navigation', 'activePage', 'requestPath', 'e')) ?>
    <main id="main-content">
        <?php if ($requestPath !== '/admin' && str_starts_with($requestPath, '/admin/')): ?>
            <nav class="admin-dashboard-return" aria-label="Administration navigation">
                <div class="container-wide">
                    <a class="btn btn-outline-navy" href="/admin"><span aria-hidden="true">←</span> Back to admin dashboard</a>
                </div>
            </nav>
        <?php endif; ?>
        <?= $content ?>
    </main>
    <?= $view->render('components.footer', compact('site', 'navigation', 'e')) ?>
    <script src="/assets/js/site.js?v=20260905.2" defer></script>
</body>
</html>
