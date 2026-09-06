<?php

declare(strict_types=1);

use App\Database\Migration;

return new class implements Migration {
    public function up(\PDO $database): void
    {
        $database->exec(<<<'SQL'
            CREATE TABLE audit_logs (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                actor_user_id BIGINT UNSIGNED NULL,
                action VARCHAR(120) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                auditable_type VARCHAR(120) CHARACTER SET ascii COLLATE ascii_bin NULL,
                auditable_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
                description VARCHAR(500) NULL,
                old_values JSON NULL,
                new_values JSON NULL,
                ip_address VARBINARY(16) NULL,
                user_agent VARCHAR(1024) NULL,
                request_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                PRIMARY KEY (id),
                KEY idx_audit_logs_actor_created (actor_user_id, created_at),
                KEY idx_audit_logs_action_created (action, created_at),
                KEY idx_audit_logs_auditable (auditable_type, auditable_id, created_at),
                KEY idx_audit_logs_request_id (request_id),
                KEY idx_audit_logs_created_at (created_at),
                CONSTRAINT fk_audit_logs_actor
                    FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $database->exec(<<<'SQL'
            CREATE TABLE security_events (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NULL,
                event_type VARCHAR(120) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                severity VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'info',
                description VARCHAR(500) NOT NULL,
                ip_address VARBINARY(16) NULL,
                user_agent VARCHAR(1024) NULL,
                context JSON NULL,
                fingerprint CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
                occurred_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                resolved_at DATETIME(6) NULL,
                resolved_by BIGINT UNSIGNED NULL,
                PRIMARY KEY (id),
                KEY idx_security_events_user_occurred (user_id, occurred_at),
                KEY idx_security_events_type_occurred (event_type, occurred_at),
                KEY idx_security_events_severity_occurred (severity, occurred_at),
                KEY idx_security_events_fingerprint (fingerprint),
                KEY idx_security_events_resolution (resolved_at, severity),
                CONSTRAINT fk_security_events_user
                    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
                CONSTRAINT fk_security_events_resolved_by
                    FOREIGN KEY (resolved_by) REFERENCES users (id) ON DELETE SET NULL,
                CONSTRAINT chk_security_events_severity
                    CHECK (severity IN ('info', 'low', 'medium', 'high', 'critical'))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    public function down(\PDO $database): void
    {
        $database->exec('DROP TABLE security_events');
        $database->exec('DROP TABLE audit_logs');
    }
};

