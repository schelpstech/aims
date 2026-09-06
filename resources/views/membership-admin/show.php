<?php

declare(strict_types=1);

$status = (string) ($application['status'] ?? '');
$statusLabel = ucwords(str_replace('_', ' ', $status));
$applicationId = (string) ($application['public_id'] ?? '');
$sections = [
    'Personal information' => (array) ($application['personal_information'] ?? []),
    'Contact information' => (array) ($application['contact_information'] ?? []),
    'Professional details' => (array) ($application['professional_details'] ?? []),
    'Education' => (array) ($application['education'] ?? []),
    'Employment' => (array) ($application['employment'] ?? []),
];
?>
<section class="admin-membership-shell section-space" aria-labelledby="application-review-heading">
    <div class="container-wide">
        <p><a href="/admin/membership/applications">← Back to applications</a></p>
        <div class="membership-page-heading">
            <div>
                <p class="eyebrow">Application review</p>
                <h1 id="application-review-heading"><?= $e($application['application_reference'] ?? 'Membership application') ?></h1>
                <p>Account: <?= $e($application['account_email'] ?? '') ?></p>
            </div>
            <div class="application-status-card">
                <span>Status</span><strong><?= $e($statusLabel) ?></strong>
                <small><?= $e($application['grade_name'] ?? 'No grade selected') ?></small>
            </div>
        </div>

        <?php if (is_string($message) && $message !== ''): ?><div class="alert alert-success" role="status"><?= $e($message) ?></div><?php endif; ?>
        <?php if (is_string($error) && $error !== ''): ?><div class="alert alert-danger" role="alert"><?= $e($error) ?></div><?php endif; ?>

        <?php if (!empty($application['membership_number'])): ?>
            <div class="member-activation-banner"><span>Active member</span><strong><?= $e($application['membership_number']) ?></strong></div>
        <?php endif; ?>

        <div class="review-layout">
            <div class="review-main">
                <?php foreach ($sections as $heading => $section): ?>
                    <section class="membership-section review-section">
                        <h2><?= $e($heading) ?></h2>
                        <dl>
                            <?php foreach ($section as $label => $value): ?>
                                <?php if ($value !== null && $value !== ''): ?>
                                    <div><dt><?= $e(ucwords(str_replace('_', ' ', (string) $label))) ?></dt><dd><?= nl2br($e($value)) ?></dd></div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </dl>
                    </section>
                <?php endforeach; ?>

                <section class="membership-section review-section">
                    <h2>Declaration</h2>
                    <dl>
                        <div><dt>Name</dt><dd><?= $e($application['declaration_name'] ?? '') ?></dd></div>
                        <div><dt>Accepted</dt><dd><?= $e($application['declaration_accepted_at'] ?? '') ?></dd></div>
                    </dl>
                </section>

                <section class="membership-section review-section">
                    <h2>Supporting documents</h2>
                    <?php if (($application['documents'] ?? []) === []): ?><p>No supporting documents are attached.</p><?php endif; ?>
                    <ul class="document-list">
                        <?php foreach (($application['documents'] ?? []) as $document): ?>
                            <li>
                                <span><?= $e(ucwords(str_replace('_', ' ', $document['document_type'] ?? ''))) ?></span>
                                <strong><?= $e($document['original_name'] ?? '') ?></strong>
                                <a href="/admin/membership/documents/<?= $e(rawurlencode($document['public_id'] ?? '')) ?>">Download securely</a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>

                <section class="membership-history review-history">
                    <h2>Status history</h2>
                    <ol>
                        <?php foreach (($application['history'] ?? []) as $history): ?>
                            <li><span aria-hidden="true"></span><div><strong><?= $e(ucwords(str_replace('_', ' ', $history['to_status'] ?? ''))) ?></strong><p><?= $e($history['note'] ?? '') ?></p><small><?= $e($history['created_at'] ?? '') ?></small></div></li>
                        <?php endforeach; ?>
                    </ol>
                </section>
            </div>

            <aside class="review-sidebar" aria-label="Review actions and comments">
                <section class="membership-section">
                    <h2>Review comments</h2>
                    <?php if (($application['comments'] ?? []) === []): ?><p>No review comments recorded.</p><?php endif; ?>
                    <div class="review-comments">
                        <?php foreach (($application['comments'] ?? []) as $comment): ?>
                            <article>
                                <strong><?= $e(ucwords($comment['comment_type'] ?? 'review')) ?></strong>
                                <p><?= nl2br($e($comment['body'] ?? '')) ?></p>
                                <small><?= $e($comment['author_email'] ?? '') ?> · <?= $e($comment['created_at'] ?? '') ?><?= !empty($comment['visible_to_applicant']) ? ' · Applicant visible' : ' · Internal' ?></small>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>

                <?php if ($can['review'] && in_array($status, ['submitted', 'under_review', 'query_raised', 'approved', 'rejected'], true)): ?>
                    <form class="membership-section admin-action-form" method="post" action="/admin/membership/applications/<?= $e(rawurlencode($applicationId)) ?>/review">
                        <?= $csrf->field() ?><h2>Internal review note</h2>
                        <textarea class="form-control" name="comment" maxlength="5000" rows="4" required></textarea>
                        <button class="btn btn-outline-navy" type="submit">Record comment</button>
                    </form>
                <?php endif; ?>

                <?php if ($can['query'] && in_array($status, ['submitted', 'under_review'], true)): ?>
                    <form class="membership-section admin-action-form" method="post" action="/admin/membership/applications/<?= $e(rawurlencode($applicationId)) ?>/query">
                        <?= $csrf->field() ?><h2>Raise query</h2>
                        <p>This message will be visible to the applicant.</p>
                        <textarea class="form-control" name="comment" maxlength="5000" rows="4" required></textarea>
                        <button class="btn btn-outline-navy" type="submit">Raise query</button>
                    </form>
                <?php endif; ?>

                <?php if ($can['approve'] && in_array($status, ['submitted', 'under_review'], true)): ?>
                    <form class="membership-section admin-action-form approve-form" method="post" action="/admin/membership/applications/<?= $e(rawurlencode($applicationId)) ?>/approve">
                        <?= $csrf->field() ?><h2>Approve application</h2>
                        <p>Approval creates and activates the member record with a unique membership number.</p>
                        <textarea class="form-control" name="comment" maxlength="5000" rows="3" placeholder="Optional internal approval note"></textarea>
                        <button class="btn btn-primary" type="submit">Approve and activate</button>
                    </form>
                <?php endif; ?>

                <?php if ($can['reject'] && in_array($status, ['submitted', 'under_review', 'query_raised'], true)): ?>
                    <form class="membership-section admin-action-form reject-form" method="post" action="/admin/membership/applications/<?= $e(rawurlencode($applicationId)) ?>/reject">
                        <?= $csrf->field() ?><h2>Reject application</h2>
                        <p>The application and its audit history will be retained.</p>
                        <textarea class="form-control" name="comment" maxlength="5000" rows="4" required></textarea>
                        <button class="btn btn-outline-navy" type="submit">Reject application</button>
                    </form>
                <?php endif; ?>
            </aside>
        </div>
    </div>
</section>
