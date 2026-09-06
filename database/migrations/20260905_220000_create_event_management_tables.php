<?php

declare(strict_types=1);

use App\Database\Migration;

return new class implements Migration {
    public function up(\PDO $database): void
    {
        $database->exec(<<<'SQL'
            CREATE TABLE event_types (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                name VARCHAR(100) NOT NULL,
                slug VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
                PRIMARY KEY (id), UNIQUE KEY uq_event_types_public_id (public_id),
                UNIQUE KEY uq_event_types_name (name), UNIQUE KEY uq_event_types_slug (slug),
                KEY idx_event_types_active_order (active,display_order,name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        $database->exec(<<<'SQL'
            CREATE TABLE events (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                event_type_id BIGINT UNSIGNED NOT NULL,
                created_by_user_id BIGINT UNSIGNED NOT NULL,
                title VARCHAR(220) NOT NULL,
                slug VARCHAR(220) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                description TEXT NULL,
                event_date DATE NOT NULL,
                start_time TIME NULL,
                end_time TIME NULL,
                venue VARCHAR(300) NULL,
                online_url VARCHAR(2048) NULL,
                registration_deadline DATETIME(6) NULL,
                capacity INT UNSIGNED NULL,
                fee DECIMAL(12,2) NULL,
                fee_currency CHAR(3) CHARACTER SET ascii COLLATE ascii_bin NULL,
                eligibility TEXT NULL,
                registration_access VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'public',
                banner_path VARCHAR(500) NULL,
                status VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'draft',
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
                deleted_at DATETIME(6) NULL,
                PRIMARY KEY (id), UNIQUE KEY uq_events_public_id (public_id), UNIQUE KEY uq_events_slug (slug),
                KEY idx_events_public_date (status,deleted_at,event_date), KEY idx_events_type_date (event_type_id,event_date),
                KEY idx_events_creator (created_by_user_id),
                CONSTRAINT fk_events_type FOREIGN KEY (event_type_id) REFERENCES event_types(id) ON DELETE RESTRICT,
                CONSTRAINT fk_events_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
                CONSTRAINT chk_events_status CHECK (status IN ('draft','published','cancelled','completed')),
                CONSTRAINT chk_events_access CHECK (registration_access IN ('public','member')),
                CONSTRAINT chk_events_fee CHECK (fee IS NULL OR fee >= 0),
                CONSTRAINT chk_events_times CHECK (end_time IS NULL OR start_time IS NULL OR end_time > start_time)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        $database->exec(<<<'SQL'
            CREATE TABLE event_registrations (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                event_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NULL,
                registration_reference VARCHAR(60) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                registrant_name VARCHAR(200) NOT NULL,
                registrant_email VARCHAR(254) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
                registrant_phone VARCHAR(40) NULL,
                status VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'registered',
                attendance_token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
                registered_at DATETIME(6) NOT NULL,
                cancelled_at DATETIME(6) NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
                PRIMARY KEY (id), UNIQUE KEY uq_event_registrations_public_id (public_id),
                UNIQUE KEY uq_event_registrations_reference (registration_reference),
                UNIQUE KEY uq_event_registrations_event_email (event_id,registrant_email),
                UNIQUE KEY uq_event_registrations_event_user (event_id,user_id),
                UNIQUE KEY uq_event_registrations_attendance_token (attendance_token_hash),
                KEY idx_event_registrations_event_status (event_id,status,registered_at), KEY idx_event_registrations_user (user_id,status),
                CONSTRAINT fk_event_registrations_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE RESTRICT,
                CONSTRAINT fk_event_registrations_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
                CONSTRAINT chk_event_registrations_status CHECK (status IN ('registered','cancelled','attended')),
                CONSTRAINT chk_event_attendance_token_hash CHECK (attendance_token_hash IS NULL OR attendance_token_hash REGEXP '^[0-9a-f]{64}$')
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        $database->exec(<<<'SQL'
            CREATE TABLE event_attendance (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                event_registration_id BIGINT UNSIGNED NOT NULL,
                checked_in_by_user_id BIGINT UNSIGNED NOT NULL,
                check_in_method VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'manual',
                checked_in_at DATETIME(6) NOT NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                PRIMARY KEY (id), UNIQUE KEY uq_event_attendance_public_id (public_id),
                UNIQUE KEY uq_event_attendance_registration (event_registration_id), KEY idx_event_attendance_actor (checked_in_by_user_id),
                CONSTRAINT fk_event_attendance_registration FOREIGN KEY (event_registration_id) REFERENCES event_registrations(id) ON DELETE RESTRICT,
                CONSTRAINT fk_event_attendance_actor FOREIGN KEY (checked_in_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
                CONSTRAINT chk_event_attendance_method CHECK (check_in_method IN ('manual','qr'))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }
    public function down(\PDO $database): void { $database->exec('DROP TABLE event_attendance');$database->exec('DROP TABLE event_registrations');$database->exec('DROP TABLE events');$database->exec('DROP TABLE event_types'); }
};
