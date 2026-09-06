<?php

declare(strict_types=1);

use App\Database\Migration;

return new class implements Migration {
    public function up(\PDO $database): void
    {
        $database->exec(<<<'SQL'
            CREATE TABLE people (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                full_name VARCHAR(200) NOT NULL,
                title VARCHAR(120) NULL,
                qualifications VARCHAR(500) NULL,
                biography TEXT NULL,
                photo_path VARCHAR(500) NULL,
                professional_area VARCHAR(160) NULL,
                email VARCHAR(254) NULL,
                linkedin_url VARCHAR(500) NULL,
                display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
                deleted_at DATETIME(6) NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_people_public_id (public_id),
                KEY idx_people_active_order (active, display_order, full_name),
                KEY idx_people_professional_area (professional_area),
                KEY idx_people_email (email),
                KEY idx_people_deleted_at (deleted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $database->exec(<<<'SQL'
            CREATE TABLE leadership_groups (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(160) NOT NULL,
                slug VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                description VARCHAR(500) NULL,
                display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
                PRIMARY KEY (id),
                UNIQUE KEY uq_leadership_groups_name (name),
                UNIQUE KEY uq_leadership_groups_slug (slug),
                KEY idx_leadership_groups_active_order (active, display_order, name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $database->exec(<<<'SQL'
            CREATE TABLE leadership_positions (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                leadership_group_id BIGINT UNSIGNED NOT NULL,
                name VARCHAR(160) NOT NULL,
                description VARCHAR(500) NULL,
                display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
                PRIMARY KEY (id),
                UNIQUE KEY uq_leadership_positions_group_name (leadership_group_id, name),
                KEY idx_leadership_positions_group_active_order (leadership_group_id, active, display_order),
                CONSTRAINT fk_leadership_positions_group
                    FOREIGN KEY (leadership_group_id) REFERENCES leadership_groups (id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $database->exec(<<<'SQL'
            CREATE TABLE leadership_assignments (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                person_id BIGINT UNSIGNED NOT NULL,
                leadership_position_id BIGINT UNSIGNED NOT NULL,
                display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                active TINYINT(1) NOT NULL DEFAULT 1,
                starts_at DATE NULL,
                ends_at DATE NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
                PRIMARY KEY (id),
                UNIQUE KEY uq_leadership_assignments_person_position (person_id, leadership_position_id),
                KEY idx_leadership_assignments_position_active_order (leadership_position_id, active, display_order),
                KEY idx_leadership_assignments_person_active (person_id, active),
                KEY idx_leadership_assignments_dates (starts_at, ends_at),
                CONSTRAINT fk_leadership_assignments_person
                    FOREIGN KEY (person_id) REFERENCES people (id) ON DELETE RESTRICT,
                CONSTRAINT fk_leadership_assignments_position
                    FOREIGN KEY (leadership_position_id) REFERENCES leadership_positions (id) ON DELETE RESTRICT,
                CONSTRAINT chk_leadership_assignments_dates
                    CHECK (ends_at IS NULL OR starts_at IS NULL OR ends_at >= starts_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    public function down(\PDO $database): void
    {
        $database->exec('DROP TABLE leadership_assignments');
        $database->exec('DROP TABLE leadership_positions');
        $database->exec('DROP TABLE leadership_groups');
        $database->exec('DROP TABLE people');
    }
};
