<?php declare(strict_types=1); ?>
<section class="auth-shell section-space" aria-labelledby="auth-heading">
    <div class="container-narrow">
        <div class="auth-card">
            <div class="eyebrow">Email confirmation</div>
            <h1 id="auth-heading">Verify your email address</h1>
            <p class="auth-intro">Confirm this one-time link to activate account access. Opening the link alone does not consume it.</p>

            <?php if (is_string($error) && $error !== ''): ?>
                <div class="alert alert-danger" role="alert"><?= $e($error) ?></div>
            <?php else: ?>
                <form method="post" action="/verify-email" class="auth-form">
                    <?= $csrf->field() ?>
                    <input type="hidden" name="token" value="<?= $e($token) ?>">
                    <button class="btn btn-gold w-100" type="submit">Confirm email address</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>
