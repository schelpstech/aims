<?php declare(strict_types=1); ?>
<section class="auth-shell section-space" aria-labelledby="auth-heading">
    <div class="container-narrow">
        <div class="auth-card">
            <div class="eyebrow">Registration foundation</div>
            <h1 id="auth-heading">Create a secure account</h1>
            <p class="auth-intro">This creates account access only. The full membership application will be introduced in a later stage.</p>

            <?php if (is_string($message) && $message !== ''): ?>
                <div class="alert alert-success" role="status"><?= $e($message) ?></div>
            <?php endif; ?>
            <?php if (is_string($error) && $error !== ''): ?>
                <div class="alert alert-danger" role="alert"><?= $e($error) ?></div>
            <?php endif; ?>

            <form method="post" action="/register" class="auth-form">
                <?= $csrf->field() ?>
                <div>
                    <label class="form-label" for="register-email">Email address</label>
                    <input class="form-control" id="register-email" name="email" type="email" value="<?= $e($email) ?>" autocomplete="email" inputmode="email" maxlength="254" required>
                </div>
                <div>
                    <label class="form-label" for="register-password">Password</label>
                    <input class="form-control" id="register-password" name="password" type="password" autocomplete="new-password" minlength="<?= $e($passwordMinimum) ?>" maxlength="<?= $e($passwordMaximum) ?>" aria-describedby="password-guidance" required>
                    <div class="form-text" id="password-guidance">Use at least <?= $e($passwordMinimum) ?> characters. A password manager-generated passphrase is recommended.</div>
                </div>
                <div>
                    <label class="form-label" for="register-password-confirmation">Confirm password</label>
                    <input class="form-control" id="register-password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="<?= $e($passwordMinimum) ?>" maxlength="<?= $e($passwordMaximum) ?>" required>
                </div>
                <button class="btn btn-gold w-100" type="submit">Create account</button>
            </form>

            <p class="auth-switch">Already registered? <a href="/login">Sign in</a>.</p>
        </div>
    </div>
</section>
