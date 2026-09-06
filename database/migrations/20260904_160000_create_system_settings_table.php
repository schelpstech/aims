<?php

declare(strict_types=1);

use App\Database\Migration;

return new class implements Migration {
    public function up(\PDO $database): void
    {
        $database->exec(<<<'SQL'
            CREATE TABLE system_settings (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                setting_group VARCHAR(100) NOT NULL DEFAULT 'organisation',
                setting_key VARCHAR(100) NOT NULL,
                setting_value LONGTEXT NULL,
                encrypted_value LONGTEXT NULL,
                value_type VARCHAR(20) NOT NULL DEFAULT 'string',
                is_sensitive TINYINT(1) NOT NULL DEFAULT 0,
                is_public TINYINT(1) NOT NULL DEFAULT 0,
                autoload TINYINT(1) NOT NULL DEFAULT 1,
                description VARCHAR(255) NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
                PRIMARY KEY (id),
                UNIQUE KEY uq_system_settings_key (setting_key),
                KEY idx_system_settings_group (setting_group),
                KEY idx_system_settings_autoload (autoload),
                KEY idx_system_settings_public (is_public),
                CONSTRAINT chk_system_settings_value_type
                    CHECK (value_type IN ('string', 'integer', 'boolean', 'json')),
                CONSTRAINT chk_system_settings_storage
                    CHECK (
                        (is_sensitive = 0 AND encrypted_value IS NULL)
                        OR (is_sensitive = 1 AND setting_value IS NULL)
                    ),
                CONSTRAINT chk_system_settings_sensitive_public
                    CHECK (is_sensitive = 0 OR is_public = 0)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    public function down(\PDO $database): void
    {
        $database->exec('DROP TABLE system_settings');
    }
};

