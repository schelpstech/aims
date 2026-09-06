<?php

declare(strict_types=1);

namespace App\Services\Membership;

use DateTimeImmutable;

interface MembershipRepositoryInterface
{
    /** @return list<array<string, mixed>> */
    public function activeGrades(): array;

    /** @return array<string, mixed>|null */
    public function currentApplication(int $userId): ?array;

    /** @param array<string, mixed> $sections
     *  @return array<string, mixed>
     */
    public function saveDraft(int $userId, ?string $applicationPublicId, ?string $gradePublicId, array $sections, DateTimeImmutable $now): array;

    /** @return array<string, mixed>|null */
    public function applicationForUser(int $userId, string $applicationPublicId): ?array;

    /** @return array<string, mixed> */
    public function addDocument(int $userId, string $applicationPublicId, array $document, DateTimeImmutable $now): array;

    /** @return array<string, mixed>|null */
    public function submit(int $userId, string $applicationPublicId, string $reference, string $declarationName, DateTimeImmutable $now): ?array;

    /** @return array<string, mixed>|null */
    public function cancel(int $userId, string $applicationPublicId, DateTimeImmutable $now): ?array;
}
