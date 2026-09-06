<?php declare(strict_types=1); ?>
<section class="auth-shell section-space" aria-labelledby="auth-heading">
    <div class="container-narrow">
        <div class="auth-card">
            <div class="eyebrow">Account recovery</div>
            <h1 id="auth-heading">Reset your password</h1>
            <p class="auth-intro">Enter your email address. The response is intentionally the same whether or not an eligible account exists.</p>

            <?php if (is_string($message) && $message !== ''): ?>
                <div class="alert alert-success" role="status"><?= $e($message) ?></div>
            <?php endif; ?>
            <?php if (is_string($error) && $error !== ''): ?>
                <div class="alert alert-danger" role="alert"><?= $e($error) ?></div>
            <?php endif; ?>

            <form method="post" action="/forgot-password" class="auth-form">
                <?= $csrf->field() ?>
                <div>
                    <label class="form-label" for="recovery-email">Email address</label>
                    <input class="form-control" id="recovery-email" name="email" type="email" value="<?= $e($email) ?>" autocomplete="email" inputmode="email" maxlength="254" required>
                </div>
                <button class="btn btn-gold w-100" type="submit">Send recovery instructions</button>
            </form>

            <p class="auth-switch"><a href="/login">Return to sign in</a>.</p>
        </div>
    </div>
</section>
