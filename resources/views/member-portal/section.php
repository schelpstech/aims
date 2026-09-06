<?php declare(strict_types=1); $member = (array) ($portal['member'] ?? []); $counts = (array) ($portal['counts'] ?? []); ?>
<section class="member-portal-shell section-space" aria-labelledby="portal-section-heading">
    <div class="container-wide">
        <?= $view->render('components.member-portal-nav', compact('activeSection', 'e')) ?>
        <div class="portal-welcome"><div><p class="eyebrow">Member portal</p><h1 id="portal-section-heading"><?= $e($sectionTitle) ?></h1><p><?= $e($sectionDescription) ?></p></div></div>
        <div class="portal-section-card">
            <?php if ($activeSection === 'membership'): ?>
                <dl class="portal-membership-details">
                    <div><dt>Member</dt><dd><?= $e($member['name'] ?? '') ?></dd></div>
                    <div><dt>Membership number</dt><dd><?= $e($member['membership_number'] ?? '') ?></dd></div>
                    <div><dt>Grade</dt><dd><?= $e($member['membership_grade'] ?? '') ?></dd></div>
                    <div><dt>Status</dt><dd><?= $e(ucwords($member['status'] ?? '')) ?></dd></div>
                    <div><dt>Join date</dt><dd><?= $e($member['joined_at'] ?? 'Not available') ?></dd></div>
                    <div><dt>Renewal</dt><dd><?= $e($member['renewal_status']['label'] ?? 'Not configured') ?></dd></div>
                </dl>
            <?php elseif ($activeSection === 'notifications' && ($portal['notifications'] ?? []) !== []): ?>
                <div class="portal-notifications">
                    <?php foreach ($portal['notifications'] as $notification): ?>
                        <article><div><strong><?= $e($notification['title'] ?? '') ?></strong><p><?= $e($notification['body'] ?? '') ?></p><small><?= $e($notification['created_at'] ?? '') ?></small></div><?php if (!empty($notification['action_url'])): ?><a href="<?= $e($notification['action_url']) ?>">Open</a><?php endif; ?></article>
                    <?php endforeach; ?>
                </div>
            <?php elseif (isset(['programmes' => 1, 'events' => 1, 'certificates' => 1][$activeSection])): ?>
                <?php $countKey = ['programmes' => 'programme_enrolments', 'events' => 'event_registrations', 'certificates' => 'certificates'][$activeSection]; ?>
                <strong class="portal-large-count"><?= $e($counts[$countKey] ?? 0) ?></strong>
            <?php endif; ?>
            <p><?= $e($sectionNotice) ?></p>
            <?php if ($activeSection === 'security'): ?><a class="btn btn-outline-navy" href="/account">Open account security</a><?php endif; ?>
            <?php if ($activeSection === 'programmes'): ?><a class="btn btn-outline-navy" href="/account/programme-applications">Open programme applications</a><?php endif; ?>
            <?php if ($activeSection === 'events'): ?><a class="btn btn-outline-navy" href="/account/event-registrations">Open event registrations</a><?php endif; ?>
            <?php if ($activeSection === 'payments'): ?><a class="btn btn-outline-navy" href="/account/invoices">Open invoices and payments</a><?php endif; ?>
            <?php if ($activeSection === 'membership'): ?><a class="btn btn-outline-navy" href="/account/membership-renewal">Open membership renewal</a><?php endif; ?>
            <?php if ($activeSection === 'certificates'): ?><a class="btn btn-outline-navy" href="/account/certificates">Open my certificates</a><?php endif; ?>
        </div>
    </div>
</section>
