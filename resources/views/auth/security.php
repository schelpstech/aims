<?php declare(strict_types=1); ?>
<section class="auth-shell section-space" aria-labelledby="security-heading">
    <div class="container-narrow">
        <div class="auth-card auth-card-wide">
            <p class="eyebrow">Account security</p>
            <h1 id="security-heading">Change your password</h1>
            <p class="auth-intro">
                Signed in as <strong><?= $e($user['email'] ?? '') ?></strong>. Changing your password signs out every other active session.
            </p>

            <?php if (is_string($message) && $message !== ''): ?>
                <div class="alert alert-success" role="status"><?= $e($message) ?></div>
            <?php endif; ?>
            <?php if (is_string($error) && $error !== ''): ?>
                <div class="alert alert-danger" role="alert"><?= $e($error) ?></div>
            <?php endif; ?>

            <form method="post" action="/account/security" class="auth-form">
                <?= $csrf->field() ?>
                <div>
                    <label class="form-label" for="current-password">Current password</label>
                    <input class="form-control" id="current-password" name="current_password" type="password" autocomplete="current-password" maxlength="<?= $e($passwordMaximum) ?>" required>
                </div>
                <div>
                    <label class="form-label" for="new-password">New password</label>
                    <input class="form-control" id="new-password" name="new_password" type="password" autocomplete="new-password" minlength="<?= $e($passwordMinimum) ?>" maxlength="<?= $e($passwordMaximum) ?>" aria-describedby="new-password-guidance" required>
                    <div class="form-text" id="new-password-guidance">Use at least <?= $e($passwordMinimum) ?> characters and choose a password that you do not use elsewhere.</div>
                </div>
                <div>
                    <label class="form-label" for="new-password-confirmation">Confirm new password</label>
                    <input class="form-control" id="new-password-confirmation" name="new_password_confirmation" type="password" autocomplete="new-password" minlength="<?= $e($passwordMinimum) ?>" maxlength="<?= $e($passwordMaximum) ?>" required>
                </div>
                <button class="btn btn-gold w-100" type="submit">Change password</button>
            </form>

            <div class="cta-row auth-secondary-actions">
                <a class="btn btn-outline-navy" href="/account">Back to account</a>
                <a class="btn btn-outline-navy" href="/admin">Admin dashboard</a>
            </div>
        </div>
    </div>
</section>
