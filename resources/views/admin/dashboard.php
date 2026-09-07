<?php declare(strict_types=1); ?>
<section class="section-space admin-dashboard" aria-labelledby="admin-dashboard-heading">
    <div class="container-wide">
        <div class="portal-welcome admin-dashboard-welcome">
            <div>
                <p class="eyebrow">Restricted administration</p>
                <h1 id="admin-dashboard-heading">Admin dashboard</h1>
                <p>Signed in as <strong><?= $e($user['email'] ?? '') ?></strong>. Only modules authorized for this account are shown.</p>
            </div>
            <div class="cta-row">
                <a class="btn btn-outline-navy" href="/">View public website</a>
                <a class="btn btn-outline-navy" href="/account/security">Change password</a>
            </div>
        </div>

        <div class="admin-module-grid" role="list" aria-label="Authorized administration modules">
            <?php foreach ($modules as $module): ?>
                <article class="admin-module-card" role="listitem">
                    <div>
                        <p class="eyebrow">Administration</p>
                        <h2><?= $e($module['label']) ?></h2>
                        <p><?= $e($module['description']) ?></p>
                    </div>
                    <a class="btn btn-navy" href="<?= $e($module['path']) ?>">Open <?= $e($module['label']) ?></a>
                </article>
            <?php endforeach; ?>
        </div>

        <section class="admin-panel admin-account-panel" aria-labelledby="admin-account-heading">
            <div>
                <p class="eyebrow">Account controls</p>
                <h2 id="admin-account-heading">Security and session</h2>
                <p>Use account security to change your password. A successful change revokes every other active session.</p>
            </div>
            <div class="cta-row">
                <a class="btn btn-outline-navy" href="/account">Account overview</a>
                <a class="btn btn-outline-navy" href="/account/security">Account security</a>
                <form method="post" action="/logout">
                    <?= $csrf->field() ?>
                    <button class="btn btn-outline-navy" type="submit">Sign out</button>
                </form>
            </div>
        </section>
    </div>
</section>
