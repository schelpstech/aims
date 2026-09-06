<?php

declare(strict_types=1);

use App\Database\Migration;

return new class implements Migration {
    public function up(\PDO $database): void
    {
        $database->exec(<<<'SQL'
            CREATE TABLE professional_areas (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                code VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NULL,
                name VARCHAR(160) NOT NULL,
                slug VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                description TEXT NULL,
                display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
                deleted_at DATETIME(6) NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_professional_areas_public_id (public_id),
                UNIQUE KEY uq_professional_areas_code (code),
                UNIQUE KEY uq_professional_areas_name (name),
                UNIQUE KEY uq_professional_areas_slug (slug),
                KEY idx_professional_areas_public_order (active, deleted_at, display_order, name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $database->exec(<<<'SQL'
            CREATE TABLE programme_types (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                name VARCHAR(160) NOT NULL,
                slug VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                description TEXT NULL,
                display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
                PRIMARY KEY (id),
                UNIQUE KEY uq_programme_types_public_id (public_id),
                UNIQUE KEY uq_programme_types_name (name),
                UNIQUE KEY uq_programme_types_slug (slug),
                KEY idx_programme_types_public_order (active, display_order, name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $database->exec(<<<'SQL'
            CREATE TABLE programmes (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                programme_type_id BIGINT UNSIGNED NOT NULL,
                code VARCHAR(60) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                name VARCHAR(200) NOT NULL,
                slug VARCHAR(200) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                description TEXT NULL,
                duration VARCHAR(120) NULL,
                entry_requirements TEXT NULL,
                delivery_mode VARCHAR(120) NULL,
                application_fee DECIMAL(12,2) NULL,
                tuition_fee DECIMAL(12,2) NULL,
                fee_currency CHAR(3) CHARACTER SET ascii COLLATE ascii_bin NULL,
                status VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'draft',
                display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
                deleted_at DATETIME(6) NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_programmes_public_id (public_id),
                UNIQUE KEY uq_programmes_code (code),
                UNIQUE KEY uq_programmes_slug (slug),
                KEY idx_programmes_type_status_order (programme_type_id, status, deleted_at, display_order),
                KEY idx_programmes_status_order (status, deleted_at, display_order, name),
                CONSTRAINT fk_programmes_type FOREIGN KEY (programme_type_id) REFERENCES programme_types (id) ON DELETE RESTRICT,
                CONSTRAINT chk_programmes_fees CHECK (
                    (application_fee IS NULL OR application_fee >= 0) AND (tuition_fee IS NULL OR tuition_fee >= 0)
                ),
                CONSTRAINT chk_programmes_status CHECK (status IN ('draft', 'published', 'archived'))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $database->exec(<<<'SQL'
            CREATE TABLE programme_areas (
                programme_id BIGINT UNSIGNED NOT NULL,
                professional_area_id BIGINT UNSIGNED NOT NULL,
                is_primary TINYINT(1) NOT NULL DEFAULT 0,
                display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                PRIMARY KEY (programme_id, professional_area_id),
                KEY idx_programme_areas_area_order (professional_area_id, display_order, programme_id),
                KEY idx_programme_areas_primary (programme_id, is_primary),
                CONSTRAINT fk_programme_areas_programme FOREIGN KEY (programme_id) REFERENCES programmes (id) ON DELETE CASCADE,
                CONSTRAINT fk_programme_areas_area FOREIGN KEY (professional_area_id) REFERENCES professional_areas (id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $database->exec(<<<'SQL'
            CREATE TABLE coordinator_assignments (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                person_id BIGINT UNSIGNED NOT NULL,
                programme_id BIGINT UNSIGNED NULL,
                professional_area_id BIGINT UNSIGNED NULL,
                role_title VARCHAR(160) NULL,
                display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                active TINYINT(1) NOT NULL DEFAULT 1,
                starts_at DATE NULL,
                ends_at DATE NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
                PRIMARY KEY (id),
                UNIQUE KEY uq_coordinator_assignments_public_id (public_id),
                KEY idx_coordinator_assignments_person_active (person_id, active),
                KEY idx_coordinator_assignments_programme (programme_id, active, display_order),
                KEY idx_coordinator_assignments_area (professional_area_id, active, display_order),
                CONSTRAINT fk_coordinator_assignments_person FOREIGN KEY (person_id) REFERENCES people (id) ON DELETE RESTRICT,
                CONSTRAINT fk_coordinator_assignments_programme FOREIGN KEY (programme_id) REFERENCES programmes (id) ON DELETE RESTRICT,
                CONSTRAINT fk_coordinator_assignments_area FOREIGN KEY (professional_area_id) REFERENCES professional_areas (id) ON DELETE RESTRICT,
                CONSTRAINT chk_coordinator_assignments_target CHECK (programme_id IS NOT NULL OR professional_area_id IS NOT NULL),
                CONSTRAINT chk_coordinator_assignments_dates CHECK (ends_at IS NULL OR starts_at IS NULL OR ends_at >= starts_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    public function down(\PDO $database): void
    {
        $database->exec('DROP TABLE coordinator_assignments');
        $database->exec('DROP TABLE programme_areas');
        $database->exec('DROP TABLE programmes');
        $database->exec('DROP TABLE programme_types');
        $database->exec('DROP TABLE professional_areas');
    }
};
