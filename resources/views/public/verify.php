<?php

declare(strict_types=1);

echo $view->render('components.page-hero', compact('page', 'e'));
?>
<section class="section verification-section">
    <div class="container-narrow">
        <div class="verification-card">
            <div class="verification-heading">
                <span class="verification-mark" aria-hidden="true">✓</span>
                <div>
                    <p class="eyebrow">Verification service</p>
                    <h2>Check an AIMS Nigeria record</h2>
                    <p>Confirm a public membership or certificate record using its official reference.</p>
                </div>
            </div>

            <form method="post" action="/verify" aria-describedby="verification-status">
                <?= $csrf->field() ?>
                <fieldset>
                    <legend>What would you like to verify?</legend>
                    <div class="verification-options">
                        <label><input type="radio" name="type" value="membership_number" required> Membership</label>
                        <label><input type="radio" name="type" value="certificate_number" required> Certificate</label>
                    </div>
                    <label for="verification-number">Membership or certificate number</label>
                    <div class="verification-input-group">
                        <input class="form-control" id="verification-number" name="value" type="text" maxlength="500" autocomplete="off" required>
                        <button class="btn btn-gold" type="submit">Verify Record</button>
                    </div>
                </fieldset>
                <p class="form-status" id="verification-status">Only a one-way digest of the submitted reference is retained in verification logs.</p>
            </form>

            <?php if(isset($verificationResult)): $record=$verificationResult->data['record']??null; ?>
                <div class="<?= $verificationResult->successful&&$record?'notice':'form-error' ?>" role="<?= $verificationResult->successful&&$record?'status':'alert' ?>"><strong><?= $e($verificationResult->message) ?></strong>
                <?php if(is_array($record)): ?><dl class="portal-membership-details"><div><dt>Record</dt><dd><?= $e(ucwords($record['record_type'])) ?></dd></div><div><dt>Holder</dt><dd><?= $e($record['holder_name']) ?></dd></div><div><dt>Number</dt><dd><?= $e($record['membership_number']??$record['certificate_number']??'') ?></dd></div><div><dt>Status</dt><dd><?= $e(ucwords($record['status'])) ?></dd></div><?php if(isset($record['membership_grade'])):?><div><dt>Grade</dt><dd><?= $e($record['membership_grade']) ?></dd></div><?php endif;?><?php if(isset($record['certificate_type'])):?><div><dt>Certificate type</dt><dd><?= $e($record['certificate_type']) ?></dd></div><div><dt>Issue date</dt><dd><?= $e($record['issue_date']) ?></dd></div><div><dt>Expiry date</dt><dd><?= $e($record['expiry_date']??'No expiry') ?></dd></div><?php if($record['programme_name']):?><div><dt>Programme</dt><dd><?= $e($record['programme_name']) ?></dd></div><?php endif;?><?php if($record['event_title']):?><div><dt>Event</dt><dd><?= $e($record['event_title']) ?></dd></div><?php endif;?><?php endif;?></dl><?php endif;?></div>
            <?php endif; ?>

            <div class="privacy-note">
                <strong>Privacy by design</strong>
                <p>Results expose only the holder name, public record number, type, status, relevant dates, and applicable programme or event.</p>
            </div>
        </div>
    </div>
</section>

<section class="section section-warm">
    <div class="container-narrow">
        <div class="feature-grid feature-grid-three">
            <article class="feature-card"><span class="feature-icon">01</span><h2>Membership number</h2><p>Confirm the public standing of an issued membership number.</p></article>
            <article class="feature-card"><span class="feature-icon">02</span><h2>Certificate number</h2><p>Confirm certificate type, holder, dates and current status.</p></article>
            <article class="feature-card"><span class="feature-icon">03</span><h2>Secure QR token</h2><p>Signed QR links are verified without exposing account or contact information.</p></article>
        </div>
    </div>
</section>
