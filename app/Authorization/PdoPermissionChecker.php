<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Database\Connection;

final class PdoPermissionChecker implements PermissionCheckerInterface
{
    public function __construct(private readonly Connection $database)
    {
    }

    public function allows(int $userId, string $permission): bool
    {
        if ($userId < 1 || preg_match('/^[a-z][a-z0-9_.]{1,119}$/D', $permission) !== 1) {
            return false;
        }

        $statement = $this->database->get()->prepare(<<<'SQL'
            SELECT 1
            FROM user_roles
            INNER JOIN roles ON roles.id = user_roles.role_id AND roles.active = 1
            INNER JOIN role_permissions ON role_permissions.role_id = roles.id
            INNER JOIN permissions ON permissions.id = role_permissions.permission_id
            WHERE user_roles.user_id = :user_id
              AND (user_roles.expires_at IS NULL OR user_roles.expires_at > CURRENT_TIMESTAMP(6))
              AND permissions.name = :permission
            LIMIT 1
            SQL);
        $statement->execute(['user_id' => $userId, 'permission' => $permission]);

        return $statement->fetchColumn() !== false;
    }
}
