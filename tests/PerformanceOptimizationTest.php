<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

try {
    $memberCounts = (string) file_get_contents(dirname(__DIR__) . '/app/Services/MemberPortal/PdoMemberActivitySummary.php');
    $assert(substr_count($memberCounts, '->prepare(') === 1, 'Member dashboard counts still use multiple database round trips.');
    $migrations = implode("\n", array_map(static fn (string $path): string => (string) file_get_contents($path), glob(dirname(__DIR__) . '/database/migrations/*.php') ?: []));
    foreach (['idx_programme_enrolments_user_status', 'idx_event_registrations_user', 'idx_certificates_member_status'] as $index) {
        $assert(str_contains($migrations, $index), "Existing supporting index {$index} was not confirmed.");
    }

    $reporting = (string) file_get_contents(dirname(__DIR__) . '/app/Repositories/ReportingRepository.php');
    $assert(!str_contains($reporting, 'SELECT COUNT(*) total_members'), 'Reporting still performs a duplicate full member aggregation.');
    $assert(str_contains($reporting, 'array_reduce($byGrade'), 'Reporting totals are not derived from the existing grouped result.');

    $producer = (string) file_get_contents(dirname(__DIR__) . '/app/Services/Notifications/ScheduledNotificationProducer.php');
    $outbox = (string) file_get_contents(dirname(__DIR__) . '/app/Services/Notifications/NotificationOutbox.php');
    $renewals = (string) file_get_contents(dirname(__DIR__) . '/app/Repositories/MembershipRenewalRepository.php');
    $assert(str_contains($producer, 'enqueueBatch') && substr_count($producer, 'LIMIT 500') === 2, 'Scheduled notifications are not bounded and batched.');
    $assert(str_contains($producer, 'NOT EXISTS') && str_contains($outbox, 'array_chunk'), 'Notification batching cannot progress past deduplicated rows safely.');
    $assert(str_contains($renewals, 'LIMIT 200 FOR UPDATE'), 'Renewal expiry still locks an unbounded work set.');

    $sessionMiddleware = (string) file_get_contents(dirname(__DIR__) . '/app/Middleware/SessionMiddleware.php');
    $assert(str_contains($sessionMiddleware, "['GET', 'HEAD', 'OPTIONS']"), 'Read-only requests still start sessions eagerly.');

    $layout = (string) file_get_contents(dirname(__DIR__) . '/resources/views/layouts/public.php');
    $script = (string) file_get_contents(dirname(__DIR__) . '/public/assets/js/site.js');
    $assert(!str_contains($layout, 'bootstrap.bundle.min.js'), 'The unnecessary Bootstrap JavaScript bundle is still loaded.');
    $assert(str_contains($script, "classList.toggle('show'"), 'Accessible mobile navigation fallback is missing.');
    $assert(str_contains((string) file_get_contents(dirname(__DIR__) . '/public/.htaccess'), 'AddOutputFilterByType DEFLATE'), 'Static text compression is not configured.');

    echo "Performance checks passed: dashboard/report query consolidation, bounded batch work, lean frontend JavaScript, and static compression/cache policy.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'Performance check failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
