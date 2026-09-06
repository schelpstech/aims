<?php

declare(strict_types=1);

use App\Database\Migration;

return new class implements Migration {
    public function up(\PDO $database): void
    {
        $database->exec(<<<'SQL'
            CREATE TABLE programme_applications (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                programme_id BIGINT UNSIGNED NOT NULL,
                application_reference VARCHAR(50) CHARACTER SET ascii COLLATE ascii_bin NULL,
                status VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'draft',
                motivation TEXT NULL,
                professional_background TEXT NULL,
                highest_qualification VARCHAR(160) NULL,
                current_organisation VARCHAR(200) NULL,
                declaration_name VARCHAR(200) NULL,
                declaration_accepted_at DATETIME(6) NULL,
                review_note TEXT NULL,
                decision_note TEXT NULL,
                submitted_at DATETIME(6) NULL,
                reviewed_at DATETIME(6) NULL,
                reviewed_by_user_id BIGINT UNSIGNED NULL,
                approved_at DATETIME(6) NULL,
                rejected_at DATETIME(6) NULL,
                withdrawn_at DATETIME(6) NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
                PRIMARY KEY (id),
                UNIQUE KEY uq_programme_applications_public_id (public_id),
                UNIQUE KEY uq_programme_applications_reference (application_reference),
                UNIQUE KEY uq_programme_applications_user_programme (user_id, programme_id),
                KEY idx_programme_applications_status_submitted (status, submitted_at),
                KEY idx_programme_applications_programme_status (programme_id, status),
                KEY idx_programme_applications_reviewer (reviewed_by_user_id),
                CONSTRAINT fk_programme_applications_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT,
                CONSTRAINT fk_programme_applications_programme FOREIGN KEY (programme_id) REFERENCES programmes (id) ON DELETE RESTRICT,
                CONSTRAINT fk_programme_applications_reviewer FOREIGN KEY (reviewed_by_user_id) REFERENCES users (id) ON DELETE SET NULL,
                CONSTRAINT chk_programme_applications_status CHECK (status IN ('draft','submitted','review','approved','rejected','enrolled','completed','withdrawn'))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $database->exec(<<<'SQL'
            CREATE TABLE programme_enrolments (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                programme_application_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                member_id BIGINT UNSIGNED NULL,
                programme_id BIGINT UNSIGNED NOT NULL,
                enrolment_number VARCHAR(60) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                status VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'enrolled',
                enrolled_by_user_id BIGINT UNSIGNED NOT NULL,
                enrolled_at DATETIME(6) NOT NULL,
                completed_at DATETIME(6) NULL,
                withdrawn_at DATETIME(6) NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
                PRIMARY KEY (id),
                UNIQUE KEY uq_programme_enrolments_public_id (public_id),
                UNIQUE KEY uq_programme_enrolments_application (programme_application_id),
                UNIQUE KEY uq_programme_enrolments_number (enrolment_number),
                KEY idx_programme_enrolments_user_status (user_id, status),
                KEY idx_programme_enrolments_member_status (member_id, status),
                KEY idx_programme_enrolments_programme_status (programme_id, status),
                KEY idx_programme_enrolments_actor (enrolled_by_user_id),
                CONSTRAINT fk_programme_enrolments_application FOREIGN KEY (programme_application_id) REFERENCES programme_applications (id) ON DELETE RESTRICT,
                CONSTRAINT fk_programme_enrolments_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT,
                CONSTRAINT fk_programme_enrolments_member FOREIGN KEY (member_id) REFERENCES members (id) ON DELETE SET NULL,
                CONSTRAINT fk_programme_enrolments_programme FOREIGN KEY (programme_id) REFERENCES programmes (id) ON DELETE RESTRICT,
                CONSTRAINT fk_programme_enrolments_actor FOREIGN KEY (enrolled_by_user_id) REFERENCES users (id) ON DELETE RESTRICT,
                CONSTRAINT chk_programme_enrolments_status CHECK (status IN ('enrolled','completed','withdrawn'))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    public function down(\PDO $database): void
    {
        $database->exec('DROP TABLE programme_enrolments');
        $database->exec('DROP TABLE programme_applications');
    }
};
