<?php

declare(strict_types=1);

use App\Database\Migration;

return new class implements Migration {
    public function up(\PDO $database): void
    {
        $database->exec(<<<'SQL'
            CREATE TABLE notification_outbox (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                user_id BIGINT UNSIGNED NULL,
                recipient_email VARCHAR(254) NULL,
                event_type VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                category VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                title VARCHAR(200) NOT NULL,
                body VARCHAR(1000) NOT NULL,
                action_url VARCHAR(500) NULL,
                send_in_app TINYINT(1) NOT NULL DEFAULT 1,
                send_email TINYINT(1) NOT NULL DEFAULT 1,
                send_sms TINYINT(1) NOT NULL DEFAULT 0,
                deduplication_key_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                status ENUM('pending','processing','completed','retry','dead') NOT NULL DEFAULT 'pending',
                attempt_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                available_at DATETIME(6) NOT NULL,
                locked_at DATETIME(6) NULL,
                lock_token CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
                processed_at DATETIME(6) NULL,
                last_error VARCHAR(500) NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
                PRIMARY KEY (id),
                UNIQUE KEY uq_notification_outbox_public_id (public_id),
                UNIQUE KEY uq_notification_outbox_dedupe (deduplication_key_hash),
                KEY idx_notification_outbox_dispatch (status, available_at, id),
                KEY idx_notification_outbox_user (user_id, created_at),
                CONSTRAINT fk_notification_outbox_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $database->exec(<<<'SQL'
            CREATE TABLE notification_deliveries (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                notification_outbox_id BIGINT UNSIGNED NOT NULL,
                channel ENUM('in_app','email','sms') NOT NULL,
                status ENUM('sent','failed','skipped') NOT NULL,
                attempt_number SMALLINT UNSIGNED NOT NULL,
                provider_message_id VARCHAR(191) NULL,
                error_code VARCHAR(80) NULL,
                error_message VARCHAR(500) NULL,
                attempted_at DATETIME(6) NOT NULL,
                sent_at DATETIME(6) NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_notification_delivery_attempt (notification_outbox_id, channel, attempt_number),
                KEY idx_notification_deliveries_status (channel, status, attempted_at),
                CONSTRAINT fk_notification_deliveries_outbox FOREIGN KEY (notification_outbox_id) REFERENCES notification_outbox (id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $database->exec(<<<'SQL'
            ALTER TABLE member_notifications
                ADD COLUMN notification_outbox_id BIGINT UNSIGNED NULL AFTER id,
                ADD UNIQUE KEY uq_member_notifications_outbox (notification_outbox_id),
                ADD CONSTRAINT fk_member_notifications_outbox FOREIGN KEY (notification_outbox_id) REFERENCES notification_outbox (id) ON DELETE RESTRICT
            SQL);
    }

    public function down(\PDO $database): void
    {
        $database->exec('ALTER TABLE member_notifications DROP FOREIGN KEY fk_member_notifications_outbox, DROP INDEX uq_member_notifications_outbox, DROP COLUMN notification_outbox_id');
        $database->exec('DROP TABLE notification_deliveries');
        $database->exec('DROP TABLE notification_outbox');
    }
};
