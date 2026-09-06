<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Security\Security;
use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use Throwable;

final class NotificationOutbox
{
    /** @param list<string> $channels */
    public static function enqueue(PDO $database, string $eventType, string $deduplicationKey, ?int $userId, ?string $recipientEmail, string $category, string $title, string $body, ?string $actionUrl, array $channels, DateTimeImmutable $now): bool
    {
        if (!preg_match('/^[a-z][a-z0-9_.-]{2,79}$/', $eventType)) throw new InvalidArgumentException('Invalid notification event type.');
        if ($userId === null && ($recipientEmail === null || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL))) throw new InvalidArgumentException('A notification recipient is required.');
        if ($actionUrl !== null && (!str_starts_with($actionUrl, '/') || str_starts_with($actionUrl, '//'))) throw new InvalidArgumentException('Notification action URLs must be application-relative.');
        $channels = array_values(array_unique($channels));
        if (array_diff($channels, ['in_app', 'email', 'sms']) !== []) throw new InvalidArgumentException('Unsupported notification channel.');
        if ($userId === null) $channels = array_values(array_diff($channels, ['in_app']));
        $stamp = $now->format('Y-m-d H:i:s.u');
        try {
            $statement = $database->prepare(<<<'SQL'
                INSERT IGNORE INTO notification_outbox
                    (public_id,user_id,recipient_email,event_type,category,title,body,action_url,send_in_app,send_email,send_sms,deduplication_key_hash,available_at,created_at,updated_at)
                VALUES
                    (:public_id,:user_id,:recipient_email,:event_type,:category,:title,:body,:action_url,:in_app,:email,:sms,:dedupe,:available_at,:created_at,:updated_at)
                SQL);
            $statement->execute([
                'public_id' => Security::uuidV4(), 'user_id' => $userId,
                'recipient_email' => $recipientEmail !== null ? mb_strtolower(trim($recipientEmail)) : null,
                'event_type' => $eventType, 'category' => $category, 'title' => $title, 'body' => $body,
                'action_url' => $actionUrl, 'in_app' => in_array('in_app', $channels, true) ? 1 : 0,
                'email' => in_array('email', $channels, true) ? 1 : 0, 'sms' => in_array('sms', $channels, true) ? 1 : 0,
                'dedupe' => hash('sha256', $eventType . '|' . $deduplicationKey), 'available_at' => $stamp,
                'created_at' => $stamp, 'updated_at' => $stamp,
            ]);
            return $statement->rowCount() === 1;
        } catch (Throwable) {
            // Notification persistence is best-effort and must never invalidate the domain transaction.
            return false;
        }
    }

    /**
     * @param list<array{event_type:string,deduplication_key:string,user_id:?int,recipient_email:?string,category:string,title:string,body:string,action_url:?string,channels:list<string>}> $notifications
     */
    public static function enqueueBatch(PDO $database, array $notifications, DateTimeImmutable $now, int $chunkSize = 100): int
    {
        if ($notifications === []) {
            return 0;
        }

        $rows = [];
        foreach ($notifications as $notification) {
            $eventType = $notification['event_type'];
            $userId = $notification['user_id'];
            $recipientEmail = $notification['recipient_email'];
            $actionUrl = $notification['action_url'];
            $channels = array_values(array_unique($notification['channels']));
            if (!preg_match('/^[a-z][a-z0-9_.-]{2,79}$/', $eventType)) throw new InvalidArgumentException('Invalid notification event type.');
            if ($userId === null && ($recipientEmail === null || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL))) throw new InvalidArgumentException('A notification recipient is required.');
            if ($actionUrl !== null && (!str_starts_with($actionUrl, '/') || str_starts_with($actionUrl, '//'))) throw new InvalidArgumentException('Notification action URLs must be application-relative.');
            if (array_diff($channels, ['in_app', 'email', 'sms']) !== []) throw new InvalidArgumentException('Unsupported notification channel.');
            if ($userId === null) $channels = array_values(array_diff($channels, ['in_app']));
            $rows[] = array_replace($notification, ['channels' => $channels]);
        }

        $inserted = 0;
        $stamp = $now->format('Y-m-d H:i:s.u');
        foreach (array_chunk($rows, max(1, min(200, $chunkSize))) as $chunk) {
            $values = [];
            $parameters = [];
            foreach ($chunk as $index => $row) {
                $suffix = (string) $index;
                $values[] = "(:public_id_{$suffix},:user_id_{$suffix},:recipient_email_{$suffix},:event_type_{$suffix},:category_{$suffix},:title_{$suffix},:body_{$suffix},:action_url_{$suffix},:in_app_{$suffix},:email_{$suffix},:sms_{$suffix},:dedupe_{$suffix},:available_at_{$suffix},:created_at_{$suffix},:updated_at_{$suffix})";
                $parameters += [
                    "public_id_{$suffix}" => Security::uuidV4(),
                    "user_id_{$suffix}" => $row['user_id'],
                    "recipient_email_{$suffix}" => $row['recipient_email'] !== null ? mb_strtolower(trim($row['recipient_email'])) : null,
                    "event_type_{$suffix}" => $row['event_type'],
                    "category_{$suffix}" => $row['category'],
                    "title_{$suffix}" => $row['title'],
                    "body_{$suffix}" => $row['body'],
                    "action_url_{$suffix}" => $row['action_url'],
                    "in_app_{$suffix}" => in_array('in_app', $row['channels'], true) ? 1 : 0,
                    "email_{$suffix}" => in_array('email', $row['channels'], true) ? 1 : 0,
                    "sms_{$suffix}" => in_array('sms', $row['channels'], true) ? 1 : 0,
                    "dedupe_{$suffix}" => hash('sha256', $row['event_type'] . '|' . $row['deduplication_key']),
                    "available_at_{$suffix}" => $stamp,
                    "created_at_{$suffix}" => $stamp,
                    "updated_at_{$suffix}" => $stamp,
                ];
            }
            try {
                $statement = $database->prepare('INSERT IGNORE INTO notification_outbox (public_id,user_id,recipient_email,event_type,category,title,body,action_url,send_in_app,send_email,send_sms,deduplication_key_hash,available_at,created_at,updated_at) VALUES ' . implode(',', $values));
                $statement->execute($parameters);
                $inserted += $statement->rowCount();
            } catch (Throwable) {
                // Keep scheduled production best-effort, consistent with single notification enqueueing.
            }
        }

        return $inserted;
    }
}
