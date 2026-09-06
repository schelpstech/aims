<?php declare(strict_types=1); ?>
<section class="auth-shell section-space" aria-labelledby="auth-heading">
    <div class="container-narrow">
        <div class="auth-card auth-card-wide">
            <div class="eyebrow">Authenticated account</div>
            <h1 id="auth-heading">Your account is secure</h1>
            <p class="auth-intro">You are signed in as <strong><?= $e($user['email'] ?? '') ?></strong>.</p>

            <?php if (is_string($message) && $message !== ''): ?>
                <div class="alert alert-success" role="status"><?= $e($message) ?></div>
            <?php endif; ?>

            <div class="auth-status-panel">
                <span class="status-dot" aria-hidden="true"></span>
                <div>
                    <strong>Account access is active</strong>
                    <p>Your authenticated membership application workspace is available. Active membership requires separate approval.</p>
                </div>
            </div>

            <p><a class="btn btn-primary" href="/account/membership-application">Open membership application</a></p>
            <p><a class="btn btn-outline-navy" href="/account/programme-applications">Open programme applications</a></p>

            <?php if (!empty($canAccessMemberPortal)): ?>
                <p><a class="btn btn-primary" href="/portal">Open member portal</a></p>
            <?php endif; ?>

            <?php if (!empty($canManageMembership)): ?>
                <p><a class="btn btn-outline-navy" href="/admin/membership/applications">Open membership administration</a></p>
            <?php endif; ?>

            <?php if (!empty($canAccessReports)): ?>
                <p><a class="btn btn-outline-navy" href="/admin/reports">Open reporting dashboard</a></p>
            <?php endif; ?>

            <form method="post" action="/logout">
                <?= $csrf->field() ?>
                <button class="btn btn-outline-navy" type="submit">Sign out</button>
            </form>
        </div>
    </div>
</section>
