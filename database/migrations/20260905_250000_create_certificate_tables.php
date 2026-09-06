<?php

declare(strict_types=1);

use App\Database\Migration;

return new class implements Migration {
    public function up(\PDO $database):void
    {
        $database->exec(<<<'SQL'
            CREATE TABLE certificate_types (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                code VARCHAR(60) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, name VARCHAR(180) NOT NULL, description TEXT NULL,
                validity_value SMALLINT UNSIGNED NULL, validity_unit VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NULL,
                template_key VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NULL, active TINYINT(1) NOT NULL DEFAULT 1,
                created_by_user_id BIGINT UNSIGNED NOT NULL, created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
                PRIMARY KEY(id), UNIQUE KEY uq_certificate_types_public_id(public_id), UNIQUE KEY uq_certificate_types_code(code), KEY idx_certificate_types_active(active,name),
                CONSTRAINT fk_certificate_types_creator FOREIGN KEY(created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
                CONSTRAINT chk_certificate_type_validity CHECK ((validity_value IS NULL AND validity_unit IS NULL) OR (validity_value>0 AND validity_unit IN ('days','months','years')))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        $database->exec(<<<'SQL'
            CREATE TABLE certificates (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                certificate_number VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, certificate_type_id BIGINT UNSIGNED NOT NULL,
                member_id BIGINT UNSIGNED NULL, person_id BIGINT UNSIGNED NULL, programme_id BIGINT UNSIGNED NULL, event_id BIGINT UNSIGNED NULL,
                issued_by_user_id BIGINT UNSIGNED NOT NULL, issue_date DATE NOT NULL, expiry_date DATE NULL,
                status VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'issued', verification_token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                revoked_at DATETIME(6) NULL, revoked_by_user_id BIGINT UNSIGNED NULL, revocation_reason VARCHAR(500) NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
                PRIMARY KEY(id), UNIQUE KEY uq_certificates_public_id(public_id), UNIQUE KEY uq_certificates_number(certificate_number), UNIQUE KEY uq_certificates_token_hash(verification_token_hash),
                KEY idx_certificates_member_status(member_id,status,issue_date), KEY idx_certificates_person_status(person_id,status,issue_date), KEY idx_certificates_type_status(certificate_type_id,status), KEY idx_certificates_programme(programme_id), KEY idx_certificates_event(event_id),
                CONSTRAINT fk_certificates_type FOREIGN KEY(certificate_type_id) REFERENCES certificate_types(id) ON DELETE RESTRICT,
                CONSTRAINT fk_certificates_member FOREIGN KEY(member_id) REFERENCES members(id) ON DELETE RESTRICT,
                CONSTRAINT fk_certificates_person FOREIGN KEY(person_id) REFERENCES people(id) ON DELETE RESTRICT,
                CONSTRAINT fk_certificates_programme FOREIGN KEY(programme_id) REFERENCES programmes(id) ON DELETE RESTRICT,
                CONSTRAINT fk_certificates_event FOREIGN KEY(event_id) REFERENCES events(id) ON DELETE RESTRICT,
                CONSTRAINT fk_certificates_issuer FOREIGN KEY(issued_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
                CONSTRAINT fk_certificates_revoker FOREIGN KEY(revoked_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
                CONSTRAINT chk_certificates_holder CHECK ((member_id IS NOT NULL AND person_id IS NULL) OR (member_id IS NULL AND person_id IS NOT NULL)),
                CONSTRAINT chk_certificates_status CHECK (status IN ('issued','revoked','expired')),
                CONSTRAINT chk_certificates_dates CHECK (expiry_date IS NULL OR expiry_date>=issue_date),
                CONSTRAINT chk_certificates_revocation CHECK ((status='revoked' AND revoked_at IS NOT NULL AND revocation_reason IS NOT NULL) OR (status<>'revoked' AND revoked_at IS NULL AND revoked_by_user_id IS NULL AND revocation_reason IS NULL))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        $database->exec(<<<'SQL'
            CREATE TABLE certificate_verifications (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                certificate_id BIGINT UNSIGNED NULL, verification_method VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                lookup_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, outcome VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                ip_address VARBINARY(16) NULL, user_agent VARCHAR(1024) NULL, verified_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                PRIMARY KEY(id), UNIQUE KEY uq_certificate_verifications_public_id(public_id), KEY idx_certificate_verifications_certificate(certificate_id,verified_at), KEY idx_certificate_verifications_lookup(lookup_hash,verified_at), KEY idx_certificate_verifications_outcome(outcome,verified_at),
                CONSTRAINT fk_certificate_verifications_certificate FOREIGN KEY(certificate_id) REFERENCES certificates(id) ON DELETE SET NULL,
                CONSTRAINT chk_certificate_verification_method CHECK (verification_method IN ('membership_number','certificate_number','qr_token')),
                CONSTRAINT chk_certificate_verification_outcome CHECK (outcome IN ('valid','revoked','expired','not_found'))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }
    public function down(\PDO$database):void{$database->exec('DROP TABLE certificate_verifications');$database->exec('DROP TABLE certificates');$database->exec('DROP TABLE certificate_types');}
};
