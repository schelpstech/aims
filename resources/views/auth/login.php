<?php declare(strict_types=1); ?>
<section class="auth-shell section-space" aria-labelledby="auth-heading">
    <div class="container-narrow">
        <div class="auth-card">
            <div class="eyebrow">Secure account access</div>
            <h1 id="auth-heading">Sign in to your account</h1>
            <p class="auth-intro">Use the email address and password attached to your AIMS Nigeria account.</p>

            <?php if (is_string($message) && $message !== ''): ?>
                <div class="alert alert-success" role="status"><?= $e($message) ?></div>
            <?php endif; ?>
            <?php if (is_string($error) && $error !== ''): ?>
                <div class="alert alert-danger" role="alert"><?= $e($error) ?></div>
            <?php endif; ?>

            <form method="post" action="/login" class="auth-form">
                <?= $csrf->field() ?>
                <div>
                    <label class="form-label" for="login-email">Email address</label>
                    <input class="form-control" id="login-email" name="email" type="email" value="<?= $e($email) ?>" autocomplete="username" inputmode="email" maxlength="254" required>
                </div>
                <div>
                    <div class="d-flex justify-content-between align-items-baseline gap-3">
                        <label class="form-label" for="login-password">Password</label>
                        <a class="auth-inline-link" href="/forgot-password">Forgot password?</a>
                    </div>
                    <input class="form-control" id="login-password" name="password" type="password" autocomplete="current-password" maxlength="<?= $e($passwordMaximum) ?>" required>
                </div>
                <button class="btn btn-gold w-100" type="submit">Sign in securely</button>
            </form>

            <p class="auth-switch">New to the platform? <a href="/register">Create an account</a>.</p>
        </div>
    </div>
</section>
