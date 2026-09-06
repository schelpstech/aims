<?php declare(strict_types=1); $member = (array) ($portal['member'] ?? []); $counts = (array) ($portal['counts'] ?? []); ?>
<section class="member-portal-shell section-space" aria-labelledby="member-dashboard-heading">
    <div class="container-wide">
        <?= $view->render('components.member-portal-nav', compact('activeSection', 'e')) ?>
        <?php if (is_string($message) && $message !== ''): ?><div class="alert alert-success" role="status"><?= $e($message) ?></div><?php endif; ?>
        <?php if (is_string($error) && $error !== ''): ?><div class="alert alert-danger" role="alert"><?= $e($error) ?></div><?php endif; ?>
        <div class="portal-welcome">
            <div><p class="eyebrow">Member dashboard</p><h1 id="member-dashboard-heading">Welcome, <?= $e($member['name'] ?? 'Member') ?></h1><p>Your verified membership details and current activity at a glance.</p></div>
            <div class="application-status-card"><span>Membership status</span><strong><?= $e(ucwords($member['status'] ?? '')) ?></strong><small><?= $e($member['membership_number'] ?? '') ?></small></div>
        </div>
        <div class="member-summary-grid">
            <article><span>Membership grade</span><strong><?= $e($member['membership_grade'] ?? '') ?><?= !empty($member['grade_abbreviation']) ? ' (' . $e($member['grade_abbreviation']) . ')' : '' ?></strong></article>
            <article><span>Join date</span><strong><?= $e($member['joined_at'] ?? 'Not available') ?></strong></article>
            <article><span>Renewal</span><strong><?= $e($member['renewal_status']['label'] ?? 'Not configured') ?></strong><?php if (!empty($member['renewal_status']['date'])): ?><small><?= $e($member['renewal_status']['date']) ?></small><?php endif; ?></article>
        </div>
        <div class="portal-count-grid">
            <a href="/portal/certificates"><span>Certificates</span><strong><?= $e($counts['certificates'] ?? 0) ?></strong></a>
            <a href="/portal/programmes"><span>Programme enrolments</span><strong><?= $e($counts['programme_enrolments'] ?? 0) ?></strong></a>
            <a href="/portal/events"><span>Event registrations</span><strong><?= $e($counts['event_registrations'] ?? 0) ?></strong></a>
        </div>
        <section class="portal-notifications" aria-labelledby="recent-notifications-heading">
            <div class="section-inline-heading"><div><h2 id="recent-notifications-heading">Recent notifications</h2></div><a href="/portal/notifications">View all</a></div>
            <?php if (($portal['notifications'] ?? []) === []): ?><p>No recent notifications.</p><?php endif; ?>
            <?php foreach (($portal['notifications'] ?? []) as $notification): ?>
                <article><div><strong><?= $e($notification['title'] ?? '') ?></strong><p><?= $e($notification['body'] ?? '') ?></p><small><?= $e($notification['created_at'] ?? '') ?></small></div><?php if (!empty($notification['action_url'])): ?><a href="<?= $e($notification['action_url']) ?>">Open</a><?php endif; ?></article>
            <?php endforeach; ?>
        </section>
    </div>
</section>
