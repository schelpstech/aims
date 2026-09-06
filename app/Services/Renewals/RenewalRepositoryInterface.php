<?php

declare(strict_types=1);

namespace App\Services\Renewals;

use DateTimeImmutable;

interface RenewalRepositoryInterface
{
    /** @return array<string, mixed> */ public function dashboardForUser(int $userId): array;
    /** @return array<string, mixed> */ public function generateForUser(int $userId, DateTimeImmutable $now): array;
    public function applyVerifiedInvoice(string $invoicePublicId, DateTimeImmutable $now): void;
    public function expireDue(DateTimeImmutable $now): int;
    /** @return list<array<string, mixed>> */ public function adminRenewals(): array;
    /** @return array<string, mixed> */ public function renewalConfiguration(): array;
    /** @param array<string, mixed> $policy */
    public function savePolicy(int $actorUserId, ?string $policyPublicId, array $policy, DateTimeImmutable $now): string;
    /** @return array<string, mixed> */ public function override(int $actorUserId, string $renewalPublicId, string $action, string $reason, DateTimeImmutable $now): array;
}
