<?php

declare(strict_types=1);

use App\Database\Migration;

return new class implements Migration {
    public function up(\PDO $database): void
    {
        $database->exec(<<<'SQL'
            CREATE TABLE sessions (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NOT NULL,
                session_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                ip_address VARBINARY(16) NULL,
                user_agent VARCHAR(1024) NULL,
                last_activity_at DATETIME(6) NOT NULL,
                expires_at DATETIME(6) NOT NULL,
                revoked_at DATETIME(6) NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                PRIMARY KEY (id),
                UNIQUE KEY uq_sessions_hash (session_hash),
                KEY idx_sessions_user_active (user_id, revoked_at, expires_at),
                KEY idx_sessions_expires_at (expires_at),
                CONSTRAINT fk_sessions_user
                    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $database->exec(<<<'SQL'
            CREATE TABLE login_attempts (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NULL,
                identity_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                ip_address VARBINARY(16) NULL,
                user_agent VARCHAR(1024) NULL,
                successful TINYINT(1) NOT NULL DEFAULT 0,
                attempted_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                PRIMARY KEY (id),
                KEY idx_login_attempts_identity_time (identity_hash, attempted_at),
                KEY idx_login_attempts_ip_time (ip_address, attempted_at),
                KEY idx_login_attempts_user_time (user_id, attempted_at),
                CONSTRAINT fk_login_attempts_user
                    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $database->exec(<<<'SQL'
            CREATE TABLE email_verification_tokens (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NOT NULL,
                token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                expires_at DATETIME(6) NOT NULL,
                used_at DATETIME(6) NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                PRIMARY KEY (id),
                UNIQUE KEY uq_email_verification_tokens_hash (token_hash),
                KEY idx_email_verification_tokens_user_pending (user_id, used_at, expires_at),
                KEY idx_email_verification_tokens_expires_at (expires_at),
                CONSTRAINT fk_email_verification_tokens_user
                    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $database->exec(<<<'SQL'
            CREATE TABLE password_reset_tokens (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NOT NULL,
                token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                expires_at DATETIME(6) NOT NULL,
                used_at DATETIME(6) NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                PRIMARY KEY (id),
                UNIQUE KEY uq_password_reset_tokens_hash (token_hash),
                KEY idx_password_reset_tokens_user_pending (user_id, used_at, expires_at),
                KEY idx_password_reset_tokens_expires_at (expires_at),
                CONSTRAINT fk_password_reset_tokens_user
                    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    public function down(\PDO $database): void
    {
        $database->exec('DROP TABLE password_reset_tokens');
        $database->exec('DROP TABLE email_verification_tokens');
        $database->exec('DROP TABLE login_attempts');
        $database->exec('DROP TABLE sessions');
    }
};
