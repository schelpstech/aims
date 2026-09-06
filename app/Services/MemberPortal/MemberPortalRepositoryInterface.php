<?php

declare(strict_types=1);

namespace App\Services\MemberPortal;

use DateTimeImmutable;

interface MemberPortalRepositoryInterface
{
    /** @return array<string, mixed>|null */
    public function memberForUser(int $userId): ?array;

    /** @param array<string, mixed> $profile
     *  @return array<string, mixed>|null
     */
    public function updateProfileForUser(int $userId, array $profile, DateTimeImmutable $now): ?array;

    /** @return list<array<string, mixed>> */
    public function recentNotificationsForUser(int $userId, int $limit): array;
}
