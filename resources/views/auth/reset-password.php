<?php declare(strict_types=1); ?>
<section class="auth-shell section-space" aria-labelledby="auth-heading">
    <div class="container-narrow">
        <div class="auth-card">
            <div class="eyebrow">Protected recovery</div>
            <h1 id="auth-heading">Choose a new password</h1>
            <p class="auth-intro">Successful recovery invalidates the link and revokes existing account sessions.</p>

            <?php if (is_string($error) && $error !== ''): ?>
                <div class="alert alert-danger" role="alert"><?= $e($error) ?></div>
            <?php endif; ?>

            <?php if (is_string($token) && preg_match('/^[a-f0-9]{64}$/D', $token) === 1): ?>
                <form method="post" action="/reset-password" class="auth-form">
                    <?= $csrf->field() ?>
                    <input type="hidden" name="token" value="<?= $e($token) ?>">
                    <div>
                        <label class="form-label" for="reset-password">New password</label>
                        <input class="form-control" id="reset-password" name="password" type="password" autocomplete="new-password" minlength="<?= $e($passwordMinimum) ?>" maxlength="<?= $e($passwordMaximum) ?>" required>
                    </div>
                    <div>
                        <label class="form-label" for="reset-password-confirmation">Confirm new password</label>
                        <input class="form-control" id="reset-password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="<?= $e($passwordMinimum) ?>" maxlength="<?= $e($passwordMaximum) ?>" required>
                    </div>
                    <button class="btn btn-gold w-100" type="submit">Reset password</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>
