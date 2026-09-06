<?php

declare(strict_types=1);

$member = (array) ($portal['member'] ?? []);
$value = static fn (string $field): mixed => array_key_exists($field, $old) ? $old[$field] : ($member[$field] ?? '');
$fieldError = static fn (string $field): string => isset($errors[$field][0]) ? '<span class="field-error">' . $e($errors[$field][0]) . '</span>' : '';
?>
<section class="member-portal-shell section-space" aria-labelledby="member-profile-heading">
    <div class="container-wide">
        <?= $view->render('components.member-portal-nav', compact('activeSection', 'e')) ?>
        <div class="portal-welcome"><div><p class="eyebrow">Member portal</p><h1 id="member-profile-heading">My profile</h1><p>Update approved contact and professional profile fields.</p></div></div>
        <?php if (is_string($message) && $message !== ''): ?><div class="alert alert-success" role="status"><?= $e($message) ?></div><?php endif; ?>
        <?php if (is_string($error) && $error !== ''): ?><div class="alert alert-danger" role="alert"><?= $e($error) ?></div><?php endif; ?>

        <section class="member-readonly-panel" aria-labelledby="verified-membership-heading">
            <h2 id="verified-membership-heading">Verified membership fields</h2>
            <dl>
                <div><dt>Membership number</dt><dd><?= $e($member['membership_number'] ?? '') ?></dd></div>
                <div><dt>Grade</dt><dd><?= $e($member['membership_grade'] ?? '') ?></dd></div>
                <div><dt>Status</dt><dd><?= $e(ucwords($member['status'] ?? '')) ?></dd></div>
                <div><dt>Join date</dt><dd><?= $e($member['joined_at'] ?? 'Not available') ?></dd></div>
            </dl>
            <p>These fields require authorized administrative action and cannot be changed here.</p>
        </section>

        <?php if (($member['status'] ?? '') === 'active'): ?>
            <form class="membership-section member-profile-form" method="post" action="/portal/profile">
                <?= $csrf->field() ?>
                <div class="membership-fields two-columns">
                    <label>Preferred display name <input class="form-control" name="preferred_name" maxlength="120" value="<?= $e($value('preferred_name')) ?>"><?= $fieldError('preferred_name') ?></label>
                    <label>Phone <input class="form-control" type="tel" name="phone" maxlength="40" value="<?= $e($value('phone')) ?>"><?= $fieldError('phone') ?></label>
                    <label>Alternate email <input class="form-control" type="email" name="alternate_email" maxlength="254" value="<?= $e($value('alternate_email')) ?>"><?= $fieldError('alternate_email') ?></label>
                    <label>Professional area <input class="form-control" name="professional_area" maxlength="160" value="<?= $e($value('professional_area')) ?>"><?= $fieldError('professional_area') ?></label>
                    <label>Current role <input class="form-control" name="current_role" maxlength="160" value="<?= $e($value('current_role')) ?>"><?= $fieldError('current_role') ?></label>
                    <label>City <input class="form-control" name="city" maxlength="100" value="<?= $e($value('city')) ?>"><?= $fieldError('city') ?></label>
                    <label>State/region <input class="form-control" name="state_region" maxlength="100" value="<?= $e($value('state_region')) ?>"><?= $fieldError('state_region') ?></label>
                    <label>Country <input class="form-control" name="country" maxlength="100" value="<?= $e($value('country')) ?>"><?= $fieldError('country') ?></label>
                    <label class="full-column">Address <textarea class="form-control" name="address" maxlength="500" rows="3"><?= $e($value('address')) ?></textarea><?= $fieldError('address') ?></label>
                    <label class="full-column">Professional biography <textarea class="form-control" name="biography" maxlength="2000" rows="5"><?= $e($value('biography')) ?></textarea><?= $fieldError('biography') ?></label>
                </div>
                <button class="btn btn-primary" type="submit">Save permitted fields</button>
            </form>
        <?php else: ?>
            <div class="application-locked-panel"><h2>Profile editing unavailable</h2><p>Only active members may update profile information. Your membership status remains visible above.</p></div>
        <?php endif; ?>
    </div>
</section>
