<?php

declare(strict_types=1);

$aboutActive = in_array($activePage, ['about', 'vision'], true);
?>
<header class="site-header" data-site-header>
    <nav class="navbar navbar-expand-xl" aria-label="Primary navigation">
        <div class="container-wide">
            <a class="navbar-brand" href="/" aria-label="AIMS Nigeria home">
                <span class="brand-mark" aria-hidden="true">A</span>
                <span class="brand-copy">
                    <strong><?= $e($site['short_name']) ?></strong>
                    <small>Professional Association</small>
                </span>
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#primaryNavigation" aria-controls="primaryNavigation" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="primaryNavigation">
                <ul class="navbar-nav mx-xl-auto">
                    <?php foreach ($navigation as $item): ?>
                        <?php
                        $active = $requestPath === $item['path']
                            || ($item['path'] === '/about' && $aboutActive);
                        ?>
                        <li class="nav-item">
                            <a class="nav-link<?= $active ? ' active' : '' ?>" href="<?= $e($item['path']) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
                                <?= $e($item['label']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <div class="header-actions">
                    <a class="btn btn-link-login" href="/login">Member Login</a>
                    <a class="btn btn-gold btn-sm" href="/register">Become a Member</a>
                </div>
            </div>
        </div>
    </nav>
</header>
