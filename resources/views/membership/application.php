<?php

declare(strict_types=1);

$application = is_array($application) ? $application : null;
$editable = $application === null || in_array($application['status'] ?? null, ['draft', 'query_raised'], true);
$statusLabels = [
    'draft' => 'Draft',
    'submitted' => 'Submitted',
    'under_review' => 'Under Review',
    'query_raised' => 'Query Raised',
    'approved' => 'Approved',
    'rejected' => 'Rejected',
    'cancelled' => 'Cancelled',
];
$nested = static function (?array $source, string $section, string $key): mixed {
    return is_array($source[$section] ?? null) ? ($source[$section][$key] ?? '') : '';
};
$value = static function (string $key, ?string $section = null) use ($old, $application, $nested): mixed {
    if (array_key_exists($key, $old)) {
        return $old[$key];
    }
    if ($section === null) {
        return $application[$key] ?? '';
    }

    return $nested($application, $section, $key);
};
$fieldError = static function (string $field) use ($errors, $e): string {
    if (!isset($errors[$field][0])) {
        return '';
    }

    return '<span class="field-error">' . $e($errors[$field][0]) . '</span>';
};
$applicationId = is_string($application['public_id'] ?? null) ? $application['public_id'] : '';
?>
<section class="membership-shell section-space" aria-labelledby="membership-heading">
    <div class="container-wide">
        <div class="membership-page-heading">
            <div>
                <p class="eyebrow">Authenticated application</p>
                <h1 id="membership-heading">Membership application</h1>
                <p>Save your progress as a draft. Submission starts a separate review and does not create active membership.</p>
            </div>
            <?php if ($application !== null): ?>
                <div class="application-status-card">
                    <span>Status</span>
                    <strong><?= $e($statusLabels[$application['status'] ?? ''] ?? 'Unknown') ?></strong>
                    <?php if (!empty($application['application_reference'])): ?>
                        <small>Reference <?= $e($application['application_reference']) ?></small>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if (is_string($message) && $message !== ''): ?>
            <div class="alert alert-success" role="status"><?= $e($message) ?></div>
        <?php endif; ?>
        <?php if (is_string($error) && $error !== ''): ?>
            <div class="alert alert-danger" role="alert"><?= $e($error) ?></div>
        <?php endif; ?>

        <?php if (($application['applicant_messages'] ?? []) !== []): ?>
            <section class="applicant-review-messages" aria-labelledby="review-messages-heading">
                <h2 id="review-messages-heading">Messages from membership review</h2>
                <?php foreach ($application['applicant_messages'] as $reviewMessage): ?>
                    <article>
                        <strong><?= $e(ucwords($reviewMessage['comment_type'] ?? 'review')) ?></strong>
                        <p><?= nl2br($e($reviewMessage['body'] ?? '')) ?></p>
                        <small><?= $e($reviewMessage['created_at'] ?? '') ?></small>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>

        <?php if ($editable): ?>
            <form class="membership-form" method="post" action="/account/membership-application/draft" novalidate>
                <?= $csrf->field() ?>
                <?php if ($applicationId !== ''): ?>
                    <input type="hidden" name="application_public_id" value="<?= $e($applicationId) ?>">
                <?php endif; ?>

                <fieldset class="membership-section">
                    <legend><span>01</span> Personal information</legend>
                    <div class="membership-fields three-columns">
                        <label>First name <input class="form-control" name="first_name" maxlength="100" value="<?= $e($value('first_name', 'personal_information')) ?>"><?= $fieldError('first_name') ?></label>
                        <label>Middle name <input class="form-control" name="middle_name" maxlength="100" value="<?= $e($value('middle_name', 'personal_information')) ?>"><?= $fieldError('middle_name') ?></label>
                        <label>Last name <input class="form-control" name="last_name" maxlength="100" value="<?= $e($value('last_name', 'personal_information')) ?>"><?= $fieldError('last_name') ?></label>
                        <label>Date of birth <input class="form-control" type="date" name="date_of_birth" value="<?= $e($value('date_of_birth', 'personal_information')) ?>"><?= $fieldError('date_of_birth') ?></label>
                    </div>
                </fieldset>

                <fieldset class="membership-section">
                    <legend><span>02</span> Contact information</legend>
                    <div class="membership-fields two-columns">
                        <label>Phone <input class="form-control" type="tel" name="phone" maxlength="40" value="<?= $e($value('phone', 'contact_information')) ?>"><?= $fieldError('phone') ?></label>
                        <label>Contact email <input class="form-control" type="email" name="contact_email" maxlength="254" value="<?= $e($value('contact_email', 'contact_information')) ?>"><?= $fieldError('contact_email') ?></label>
                        <label class="full-column">Address <textarea class="form-control" name="address" maxlength="500" rows="3"><?= $e($value('address', 'contact_information')) ?></textarea><?= $fieldError('address') ?></label>
                        <label>City <input class="form-control" name="city" maxlength="100" value="<?= $e($value('city', 'contact_information')) ?>"></label>
                        <label>State/region <input class="form-control" name="state" maxlength="100" value="<?= $e($value('state', 'contact_information')) ?>"></label>
                        <label>Country <input class="form-control" name="country" maxlength="100" value="<?= $e($value('country', 'contact_information')) ?>"><?= $fieldError('country') ?></label>
                    </div>
                </fieldset>

                <fieldset class="membership-section">
                    <legend><span>03</span> Professional details</legend>
                    <div class="membership-fields three-columns">
                        <label>Professional area <input class="form-control" name="professional_area" maxlength="160" value="<?= $e($value('professional_area', 'professional_details')) ?>"><?= $fieldError('professional_area') ?></label>
                        <label>Current role <input class="form-control" name="current_role" maxlength="160" value="<?= $e($value('current_role', 'professional_details')) ?>"><?= $fieldError('current_role') ?></label>
                        <label>Years of experience <input class="form-control" type="number" name="years_experience" min="0" max="80" value="<?= $e($value('years_experience', 'professional_details')) ?>"></label>
                    </div>
                </fieldset>

                <fieldset class="membership-section">
                    <legend><span>04</span> Education</legend>
                    <label>Education summary <textarea class="form-control" name="education_summary" maxlength="5000" rows="5" placeholder="List verified qualifications, institutions and dates."><?= $e($value('summary', 'education')) ?></textarea><?= $fieldError('education_summary') ?></label>
                </fieldset>

                <fieldset class="membership-section">
                    <legend><span>05</span> Employment</legend>
                    <label>Employment history <textarea class="form-control" name="employment_summary" maxlength="5000" rows="5" placeholder="List relevant employers, roles and dates."><?= $e($value('summary', 'employment')) ?></textarea><?= $fieldError('employment_summary') ?></label>
                </fieldset>

                <fieldset class="membership-section">
                    <legend><span>06</span> Membership grade</legend>
                    <?php $selectedGrade = $old['membership_grade'] ?? ($application['grade_public_id'] ?? ''); ?>
                    <label>Requested grade
                        <select class="form-control" name="membership_grade">
                            <option value="">Select a grade</option>
                            <?php foreach ($grades as $grade): ?>
                                <option value="<?= $e($grade['public_id']) ?>"<?= $selectedGrade === $grade['public_id'] ? ' selected' : '' ?>>
                                    <?= $e($grade['name']) ?><?= !empty($grade['abbreviation']) ? ' (' . $e($grade['abbreviation']) . ')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?= $fieldError('membership_grade') ?>
                    </label>
                    <p class="form-help">Abbreviations and fees appear only after formally configured. None are assumed by this application.</p>
                </fieldset>

                <div class="membership-actions">
                    <button class="btn btn-primary" type="submit">Save draft</button>
                    <span>You can return and complete the remaining sections later.</span>
                </div>
            </form>

            <?php if ($applicationId !== ''): ?>
                <form method="post" action="/account/membership-application/cancel" class="membership-cancel-form">
                    <?= $csrf->field() ?>
                    <input type="hidden" name="application_public_id" value="<?= $e($applicationId) ?>">
                    <button class="btn btn-outline-navy" type="submit">Cancel application</button>
                </form>
            <?php endif; ?>

            <section class="membership-section document-section" aria-labelledby="documents-heading">
                <div class="section-inline-heading">
                    <div><span>07</span><h2 id="documents-heading">Supporting documents</h2></div>
                    <p>PDF, JPEG or PNG · maximum 5 MB</p>
                </div>
                <?php if ($applicationId === ''): ?>
                    <p class="form-help">Save the draft once before uploading documents.</p>
                <?php else: ?>
                    <form method="post" action="/account/membership-application/documents" enctype="multipart/form-data" class="document-upload-form">
                        <?= $csrf->field() ?>
                        <input type="hidden" name="application_public_id" value="<?= $e($applicationId) ?>">
                        <label>Document type
                            <select class="form-control" name="document_type" required>
                                <option value="qualification">Qualification</option>
                                <option value="professional_certificate">Professional certificate</option>
                                <option value="identification">Identification</option>
                                <option value="other">Other supporting document</option>
                            </select>
                        </label>
                        <label>Choose document <input class="form-control" type="file" name="document" accept="application/pdf,image/jpeg,image/png" required></label>
                        <button class="btn btn-outline-navy" type="submit">Upload securely</button>
                    </form>
                <?php endif; ?>
                <?php if (($application['documents'] ?? []) !== []): ?>
                    <ul class="document-list">
                        <?php foreach ($application['documents'] as $document): ?>
                            <li><span><?= $e(ucwords(str_replace('_', ' ', $document['document_type'] ?? 'document'))) ?></span><strong><?= $e($document['original_name'] ?? '') ?></strong><small><?= $e(number_format(((int) ($document['size_bytes'] ?? 0)) / 1024, 1)) ?> KB</small></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <?= $fieldError('documents') ?>
            </section>

            <?php if ($applicationId !== ''): ?>
                <section class="membership-section declaration-section" aria-labelledby="declaration-heading">
                    <div class="section-inline-heading"><div><span>08</span><h2 id="declaration-heading">Declaration and submission</h2></div></div>
                    <p>I declare that the information and documents provided are accurate and may be reviewed for membership assessment.</p>
                    <form method="post" action="/account/membership-application/submit" class="declaration-form">
                        <?= $csrf->field() ?>
                        <input type="hidden" name="application_public_id" value="<?= $e($applicationId) ?>">
                        <label>Full name <input class="form-control" name="declaration_name" maxlength="200" value="<?= $e($old['declaration_name'] ?? '') ?>" required><?= $fieldError('declaration_name') ?></label>
                        <label class="check-label"><input type="checkbox" name="declaration_accepted" value="1"<?= !empty($old['declaration_accepted']) ? ' checked' : '' ?> required> I accept this declaration.</label>
                        <?= $fieldError('declaration_accepted') ?>
                        <button class="btn btn-primary" type="submit">Submit application</button>
                        <p class="form-help">Submission is final for this review cycle. It does not create or activate membership.</p>
                    </form>
                </section>
            <?php endif; ?>
        <?php else: ?>
            <div class="application-locked-panel">
                <h2>Your application is no longer editable</h2>
                <p>Its current status is <strong><?= $e($statusLabels[$application['status'] ?? ''] ?? 'Unknown') ?></strong>. Membership activation requires a separate authorized approval process.</p>
                <?php if (in_array($application['status'] ?? null, ['rejected', 'cancelled'], true)): ?>
                    <form method="post" action="/account/membership-application/draft">
                        <?= $csrf->field() ?>
                        <button class="btn btn-primary" type="submit">Start a new application</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (($application['history'] ?? []) !== []): ?>
            <section class="membership-history" aria-labelledby="history-heading">
                <p class="eyebrow">Status history</p>
                <h2 id="history-heading">Application timeline</h2>
                <ol>
                    <?php foreach ($application['history'] as $history): ?>
                        <li><span></span><div><strong><?= $e($statusLabels[$history['to_status'] ?? ''] ?? 'Updated') ?></strong><p><?= $e($history['note'] ?? '') ?></p><small><?= $e($history['created_at'] ?? '') ?></small></div></li>
                    <?php endforeach; ?>
                </ol>
            </section>
        <?php endif; ?>
    </div>
</section>
