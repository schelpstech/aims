<?php

declare(strict_types=1);

namespace App\Authorization;

interface PermissionCheckerInterface
{
    public function allows(int $userId, string $permission): bool;
}
