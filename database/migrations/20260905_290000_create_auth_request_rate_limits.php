<?php

declare(strict_types=1);

use App\Database\Migration;

return new class implements Migration {
    public function up(\PDO $database): void
    {
        $database->exec(<<<'SQL'
            CREATE TABLE auth_request_rate_limits (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                action VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                scope_type VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                scope_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                window_started_at DATETIME(6) NOT NULL,
                attempts INT UNSIGNED NOT NULL DEFAULT 1,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                PRIMARY KEY (id),
                UNIQUE KEY uq_auth_request_rate_limit_bucket (action, scope_type, scope_hash, window_started_at),
                KEY idx_auth_request_rate_limits_cleanup (window_started_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    public function down(\PDO $database): void
    {
        $database->exec('DROP TABLE auth_request_rate_limits');
    }
};
