<?php

declare(strict_types=1);

use App\Database\Migration;

return new class implements Migration {
    public function up(\PDO $database): void
    {
        $database->exec(<<<'SQL'
            CREATE TABLE users (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                email VARCHAR(254) NOT NULL,
                password_hash VARCHAR(255) NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                email_verified_at DATETIME(6) NULL,
                failed_login_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                locked_until DATETIME(6) NULL,
                last_login_at DATETIME(6) NULL,
                password_changed_at DATETIME(6) NULL,
                remember_token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
                deleted_at DATETIME(6) NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_users_public_id (public_id),
                UNIQUE KEY uq_users_email (email),
                KEY idx_users_status (status),
                KEY idx_users_email_verified_at (email_verified_at),
                KEY idx_users_locked_until (locked_until),
                KEY idx_users_deleted_at (deleted_at),
                CONSTRAINT chk_users_status
                    CHECK (status IN ('pending', 'active', 'suspended', 'disabled'))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $database->exec(<<<'SQL'
            CREATE TABLE roles (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(100) NOT NULL,
                slug VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                description VARCHAR(255) NULL,
                is_system TINYINT(1) NOT NULL DEFAULT 0,
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
                PRIMARY KEY (id),
                UNIQUE KEY uq_roles_name (name),
                UNIQUE KEY uq_roles_slug (slug),
                KEY idx_roles_active (active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $database->exec(<<<'SQL'
            CREATE TABLE permissions (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(120) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                module VARCHAR(60) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                description VARCHAR(255) NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
                PRIMARY KEY (id),
                UNIQUE KEY uq_permissions_name (name),
                KEY idx_permissions_module (module)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $database->exec(<<<'SQL'
            CREATE TABLE user_roles (
                user_id BIGINT UNSIGNED NOT NULL,
                role_id BIGINT UNSIGNED NOT NULL,
                assigned_by BIGINT UNSIGNED NULL,
                assigned_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                expires_at DATETIME(6) NULL,
                PRIMARY KEY (user_id, role_id),
                KEY idx_user_roles_role_id (role_id),
                KEY idx_user_roles_assigned_by (assigned_by),
                KEY idx_user_roles_expires_at (expires_at),
                CONSTRAINT fk_user_roles_user
                    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
                CONSTRAINT fk_user_roles_role
                    FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE,
                CONSTRAINT fk_user_roles_assigned_by
                    FOREIGN KEY (assigned_by) REFERENCES users (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $database->exec(<<<'SQL'
            CREATE TABLE role_permissions (
                role_id BIGINT UNSIGNED NOT NULL,
                permission_id BIGINT UNSIGNED NOT NULL,
                granted_by BIGINT UNSIGNED NULL,
                granted_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                PRIMARY KEY (role_id, permission_id),
                KEY idx_role_permissions_permission_id (permission_id),
                KEY idx_role_permissions_granted_by (granted_by),
                CONSTRAINT fk_role_permissions_role
                    FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE,
                CONSTRAINT fk_role_permissions_permission
                    FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE,
                CONSTRAINT fk_role_permissions_granted_by
                    FOREIGN KEY (granted_by) REFERENCES users (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    public function down(\PDO $database): void
    {
        $database->exec('DROP TABLE role_permissions');
        $database->exec('DROP TABLE user_roles');
        $database->exec('DROP TABLE permissions');
        $database->exec('DROP TABLE roles');
        $database->exec('DROP TABLE users');
    }
};

