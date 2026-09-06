<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use DateTimeImmutable;
use PDO;

final class ScheduledNotificationProducer
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function queue(DateTimeImmutable $now): int
    {
        $certificates = $this->database->query(<<<'SQL'
            SELECT certificates.public_id, certificates.certificate_number, members.user_id
            FROM certificates
            INNER JOIN members ON members.id = certificates.member_id
            WHERE certificates.status = 'issued'
              AND NOT EXISTS (
                  SELECT 1
                  FROM notification_outbox existing
                  WHERE existing.deduplication_key_hash = SHA2(
                      CONCAT('certificate.issued|', certificates.public_id),
                      256
                  )
              )
            ORDER BY certificates.id
            LIMIT 500
            SQL)->fetchAll();

        $certificateNotifications = array_map(
            static fn (array $row): array => [
                'event_type' => 'certificate.issued',
                'deduplication_key' => (string) $row['public_id'],
                'user_id' => (int) $row['user_id'],
                'recipient_email' => null,
                'category' => 'certificates',
                'title' => 'Certificate issued',
                'body' => 'A new certificate has been issued to your account. Certificate number: ' . $row['certificate_number'],
                'action_url' => '/portal/certificates',
                'channels' => ['in_app', 'email'],
            ],
            $certificates,
        );
        $count = NotificationOutbox::enqueueBatch($this->database, $certificateNotifications, $now);

        // PDO native prepares cannot reuse one named parameter multiple times.
        $renewals = $this->database->prepare(<<<'SQL'
            SELECT members.public_id, members.user_id, members.expires_at
            FROM members
            INNER JOIN membership_renewal_policies policies
                ON policies.membership_grade_id = members.membership_grade_id
               AND policies.active = 1
               AND policies.effective_from <= :effective_from_date
               AND (policies.effective_until IS NULL OR policies.effective_until >= :effective_until_date)
            WHERE members.status = 'active'
              AND members.expires_at IS NOT NULL
              AND members.expires_at BETWEEN :window_start_date
                  AND DATE_ADD(:window_end_date, INTERVAL policies.renewal_window_days DAY)
              AND NOT EXISTS (
                  SELECT 1
                  FROM notification_outbox existing
                  WHERE existing.deduplication_key_hash = SHA2(
                      CONCAT('membership.renewal_approaching|', members.public_id, '|', members.expires_at),
                      256
                  )
              )
            ORDER BY members.id
            LIMIT 500
            SQL);
        $today = $now->format('Y-m-d');
        $renewals->execute([
            'effective_from_date' => $today,
            'effective_until_date' => $today,
            'window_start_date' => $today,
            'window_end_date' => $today,
        ]);

        $renewalNotifications = array_map(
            static fn (array $row): array => [
                'event_type' => 'membership.renewal_approaching',
                'deduplication_key' => $row['public_id'] . '|' . $row['expires_at'],
                'user_id' => (int) $row['user_id'],
                'recipient_email' => null,
                'category' => 'membership',
                'title' => 'Membership renewal approaching',
                'body' => 'Your membership is approaching its renewal date. Review your membership details and available renewal action.',
                'action_url' => '/portal/membership',
                'channels' => ['in_app', 'email'],
            ],
            $renewals->fetchAll(),
        );

        return $count + NotificationOutbox::enqueueBatch($this->database, $renewalNotifications, $now);
    }
}
