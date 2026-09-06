<?php

declare(strict_types=1);

$initials = static function (string $name): string {
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $letters = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $letters .= mb_strtoupper(mb_substr($part, 0, 1));
    }

    return $letters !== '' ? $letters : 'A';
};

echo $view->render('components.page-hero', compact('page', 'e'));
?>
<section class="section">
    <div class="container-wide">
        <div class="section-heading centered">
            <p class="eyebrow">Leadership groups</p>
            <h2>Governance presented from one accountable source.</h2>
            <p>Only active, current assignments and confirmed profile fields are published from the organisation data layer.</p>
        </div>

        <?php if (!$leadershipDirectoryAvailable): ?>
            <div class="leadership-source-notice" role="status">
                Leadership records are temporarily unavailable. Confirmed group structures remain shown below without unverified profiles.
            </div>
        <?php endif; ?>

        <?php if ($leadershipGroups === []): ?>
            <div class="notice-panel">
                <span class="notice-icon" aria-hidden="true">i</span>
                <div>
                    <h2>No leadership groups are published yet</h2>
                    <p>Profiles will appear after approved groups, positions and assignments are added to the organisation data source.</p>
                </div>
            </div>
        <?php endif; ?>

        <div class="leadership-directory">
            <?php foreach ($leadershipGroups as $index => $group): ?>
                <section class="leadership-group-section"<?= ($group['slug'] ?? '') !== '' ? ' id="' . $e($group['slug']) . '"' : '' ?>>
                    <header class="leadership-group-heading">
                        <div>
                            <span class="leadership-number"><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
                            <h2><?= $e($group['name'] ?? '') ?></h2>
                            <?php if (!empty($group['description'])): ?>
                                <p><?= $e($group['description']) ?></p>
                            <?php endif; ?>
                        </div>
                        <span class="status-pill">
                            <?= ($group['members'] ?? []) === [] ? 'Profiles pending' : $e(count($group['members']) . ' published') ?>
                        </span>
                    </header>

                    <?php if (($group['members'] ?? []) === []): ?>
                        <div class="leadership-empty">
                            <p>No confirmed active profiles are published for this group.</p>
                        </div>
                    <?php else: ?>
                        <div class="leadership-profile-grid">
                            <?php foreach ($group['members'] as $member): ?>
                                <article class="leadership-profile">
                                    <div class="leadership-profile-media">
                                        <?php if (!empty($member['photo'])): ?>
                                            <img
                                                src="<?= $e($member['photo']) ?>"
                                                width="640"
                                                height="720"
                                                loading="lazy"
                                                alt="<?= $e(($member['name'] ?? '') . ' portrait') ?>"
                                            >
                                        <?php else: ?>
                                            <span class="leadership-avatar" aria-hidden="true"><?= $e($initials((string) ($member['name'] ?? ''))) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="leadership-profile-body">
                                        <p class="leadership-position"><?= $e($member['position']['name'] ?? '') ?></p>
                                        <h3>
                                            <?php if (!empty($member['title'])): ?>
                                                <span><?= $e($member['title']) ?></span>
                                            <?php endif; ?>
                                            <?= $e($member['name'] ?? '') ?>
                                        </h3>
                                        <?php if (!empty($member['qualifications'])): ?>
                                            <p class="leadership-qualifications"><?= $e($member['qualifications']) ?></p>
                                        <?php endif; ?>
                                        <?php if (!empty($member['professional_area'])): ?>
                                            <p class="leadership-area"><?= $e($member['professional_area']) ?></p>
                                        <?php endif; ?>
                                        <?php if (!empty($member['biography'])): ?>
                                            <p class="leadership-biography"><?= $e($member['biography']) ?></p>
                                        <?php endif; ?>
                                        <?php if (!empty($member['email']) || !empty($member['linkedin'])): ?>
                                            <div class="leadership-links">
                                                <?php if (!empty($member['email'])): ?>
                                                    <a href="mailto:<?= $e($member['email']) ?>">Email</a>
                                                <?php endif; ?>
                                                <?php if (!empty($member['linkedin'])): ?>
                                                    <a href="<?= $e($member['linkedin']) ?>" target="_blank" rel="noopener noreferrer">LinkedIn</a>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>
        </div>
    </div>
</section>
