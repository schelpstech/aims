<?php

declare(strict_types=1);

$items = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'path' => '/portal'],
    ['key' => 'profile', 'label' => 'My Profile', 'path' => '/portal/profile'],
    ['key' => 'membership', 'label' => 'Membership', 'path' => '/portal/membership'],
    ['key' => 'payments', 'label' => 'Payments', 'path' => '/portal/payments'],
    ['key' => 'programmes', 'label' => 'Programmes', 'path' => '/portal/programmes'],
    ['key' => 'events', 'label' => 'Events', 'path' => '/portal/events'],
    ['key' => 'certificates', 'label' => 'Certificates', 'path' => '/portal/certificates'],
    ['key' => 'downloads', 'label' => 'Downloads', 'path' => '/portal/downloads'],
    ['key' => 'notifications', 'label' => 'Notifications', 'path' => '/portal/notifications'],
    ['key' => 'security', 'label' => 'Security', 'path' => '/portal/security'],
    ['key' => 'support', 'label' => 'Support', 'path' => '/portal/support'],
];
?>
<nav class="member-portal-nav" aria-label="Member portal">
    <?php foreach ($items as $item): ?>
        <a href="<?= $e($item['path']) ?>"<?= $activeSection === $item['key'] ? ' class="active" aria-current="page"' : '' ?>><?= $e($item['label']) ?></a>
    <?php endforeach; ?>
</nav>
