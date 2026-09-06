<?php

declare(strict_types=1);

use App\Database\Migration;

return new class implements Migration {
    public function up(\PDO $database): void
    {
        $database->exec(<<<'SQL'
            CREATE TABLE member_profiles (
                member_id BIGINT UNSIGNED NOT NULL,
                preferred_name VARCHAR(120) NULL,
                phone VARCHAR(40) NULL,
                alternate_email VARCHAR(254) NULL,
                address VARCHAR(500) NULL,
                city VARCHAR(100) NULL,
                state_region VARCHAR(100) NULL,
                country VARCHAR(100) NULL,
                professional_area VARCHAR(160) NULL,
                current_role VARCHAR(160) NULL,
                biography TEXT NULL,
                updated_by_user_id BIGINT UNSIGNED NOT NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
                PRIMARY KEY (member_id),
                KEY idx_member_profiles_area (professional_area),
                KEY idx_member_profiles_updated_by (updated_by_user_id),
                CONSTRAINT fk_member_profiles_member FOREIGN KEY (member_id) REFERENCES members (id) ON DELETE RESTRICT,
                CONSTRAINT fk_member_profiles_updated_by FOREIGN KEY (updated_by_user_id) REFERENCES users (id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $database->exec(<<<'SQL'
            CREATE TABLE member_notifications (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                category VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'general',
                title VARCHAR(200) NOT NULL,
                body VARCHAR(1000) NOT NULL,
                action_url VARCHAR(500) NULL,
                read_at DATETIME(6) NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                PRIMARY KEY (id),
                UNIQUE KEY uq_member_notifications_public_id (public_id),
                KEY idx_member_notifications_user_created (user_id, created_at, id),
                KEY idx_member_notifications_user_read (user_id, read_at, created_at),
                CONSTRAINT fk_member_notifications_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    public function down(\PDO $database): void
    {
        $database->exec('DROP TABLE member_notifications');
        $database->exec('DROP TABLE member_profiles');
    }
};
