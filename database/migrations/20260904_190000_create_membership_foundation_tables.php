<?php

declare(strict_types=1);

use App\Database\Migration;

return new class implements Migration {
    public function up(\PDO $database): void
    {
        $database->exec(<<<'SQL'
            CREATE TABLE membership_grades (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                name VARCHAR(120) NOT NULL,
                abbreviation VARCHAR(40) NULL,
                description TEXT NULL,
                eligibility TEXT NULL,
                benefits TEXT NULL,
                application_fee DECIMAL(12,2) NULL,
                annual_fee DECIMAL(12,2) NULL,
                fee_currency CHAR(3) CHARACTER SET ascii COLLATE ascii_bin NULL,
                display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
                PRIMARY KEY (id),
                UNIQUE KEY uq_membership_grades_public_id (public_id),
                UNIQUE KEY uq_membership_grades_name (name),
                KEY idx_membership_grades_active_order (active, display_order, name),
                CONSTRAINT chk_membership_grades_fees CHECK (
                    (application_fee IS NULL OR application_fee >= 0)
                    AND (annual_fee IS NULL OR annual_fee >= 0)
                )
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $database->exec(<<<'SQL'
            CREATE TABLE membership_applications (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                membership_grade_id BIGINT UNSIGNED NULL,
                application_reference VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NULL,
                status VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'draft',
                personal_information JSON NULL,
                contact_information JSON NULL,
                professional_details JSON NULL,
                education JSON NULL,
                employment JSON NULL,
                declaration_name VARCHAR(200) NULL,
                declaration_accepted_at DATETIME(6) NULL,
                submitted_at DATETIME(6) NULL,
                cancelled_at DATETIME(6) NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
                PRIMARY KEY (id),
                UNIQUE KEY uq_membership_applications_public_id (public_id),
                UNIQUE KEY uq_membership_applications_reference (application_reference),
                KEY idx_membership_applications_user_status (user_id, status, updated_at),
                KEY idx_membership_applications_grade_status (membership_grade_id, status, submitted_at),
                KEY idx_membership_applications_status_submitted (status, submitted_at),
                CONSTRAINT fk_membership_applications_user
                    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT,
                CONSTRAINT fk_membership_applications_grade
                    FOREIGN KEY (membership_grade_id) REFERENCES membership_grades (id) ON DELETE RESTRICT,
                CONSTRAINT chk_membership_applications_status CHECK (
                    status IN ('draft', 'submitted', 'under_review', 'query_raised', 'approved', 'rejected', 'cancelled')
                )
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $database->exec(<<<'SQL'
            CREATE TABLE members (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                membership_grade_id BIGINT UNSIGNED NOT NULL,
                approved_application_id BIGINT UNSIGNED NOT NULL,
                membership_number VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NULL,
                status VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'pending_activation',
                joined_at DATE NULL,
                expires_at DATE NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
                PRIMARY KEY (id),
                UNIQUE KEY uq_members_public_id (public_id),
                UNIQUE KEY uq_members_user (user_id),
                UNIQUE KEY uq_members_approved_application (approved_application_id),
                UNIQUE KEY uq_members_membership_number (membership_number),
                KEY idx_members_grade_status (membership_grade_id, status),
                KEY idx_members_status_expiry (status, expires_at),
                CONSTRAINT fk_members_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT,
                CONSTRAINT fk_members_grade FOREIGN KEY (membership_grade_id) REFERENCES membership_grades (id) ON DELETE RESTRICT,
                CONSTRAINT fk_members_application FOREIGN KEY (approved_application_id) REFERENCES membership_applications (id) ON DELETE RESTRICT,
                CONSTRAINT chk_members_status CHECK (status IN ('pending_activation', 'active', 'suspended', 'lapsed', 'ceased'))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $database->exec(<<<'SQL'
            CREATE TABLE membership_application_documents (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                membership_application_id BIGINT UNSIGNED NOT NULL,
                uploaded_by_user_id BIGINT UNSIGNED NOT NULL,
                document_type VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                original_name VARCHAR(255) NOT NULL,
                storage_path VARCHAR(500) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                mime_type VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                size_bytes BIGINT UNSIGNED NOT NULL,
                sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                deleted_at DATETIME(6) NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_membership_documents_public_id (public_id),
                UNIQUE KEY uq_membership_documents_storage_path (storage_path),
                KEY idx_membership_documents_application (membership_application_id, deleted_at, created_at),
                KEY idx_membership_documents_uploader (uploaded_by_user_id, created_at),
                KEY idx_membership_documents_sha256 (sha256),
                CONSTRAINT fk_membership_documents_application
                    FOREIGN KEY (membership_application_id) REFERENCES membership_applications (id) ON DELETE RESTRICT,
                CONSTRAINT fk_membership_documents_uploader
                    FOREIGN KEY (uploaded_by_user_id) REFERENCES users (id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $database->exec(<<<'SQL'
            CREATE TABLE membership_history (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                membership_application_id BIGINT UNSIGNED NOT NULL,
                from_status VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NULL,
                to_status VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                note VARCHAR(500) NULL,
                changed_by_user_id BIGINT UNSIGNED NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                PRIMARY KEY (id),
                KEY idx_membership_history_application_created (membership_application_id, created_at, id),
                KEY idx_membership_history_actor_created (changed_by_user_id, created_at),
                CONSTRAINT fk_membership_history_application
                    FOREIGN KEY (membership_application_id) REFERENCES membership_applications (id) ON DELETE RESTRICT,
                CONSTRAINT fk_membership_history_actor
                    FOREIGN KEY (changed_by_user_id) REFERENCES users (id) ON DELETE SET NULL,
                CONSTRAINT chk_membership_history_from_status CHECK (
                    from_status IS NULL OR from_status IN ('draft', 'submitted', 'under_review', 'query_raised', 'approved', 'rejected', 'cancelled')
                ),
                CONSTRAINT chk_membership_history_to_status CHECK (
                    to_status IN ('draft', 'submitted', 'under_review', 'query_raised', 'approved', 'rejected', 'cancelled')
                )
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    public function down(\PDO $database): void
    {
        $database->exec('DROP TABLE membership_history');
        $database->exec('DROP TABLE membership_application_documents');
        $database->exec('DROP TABLE members');
        $database->exec('DROP TABLE membership_applications');
        $database->exec('DROP TABLE membership_grades');
    }
};
