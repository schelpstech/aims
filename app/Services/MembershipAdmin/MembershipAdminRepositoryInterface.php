<?php

declare(strict_types=1);

namespace App\Services\MembershipAdmin;

use DateTimeImmutable;

interface MembershipAdminRepositoryInterface
{
    /** @param array<string, mixed> $filters
     *  @return array{items: list<array<string, mixed>>, total: int, page: int, pages: int}
     */
    public function search(array $filters): array;

    /** @return array<string, mixed>|null */
    public function application(string $applicationPublicId): ?array;

    /** @return array<string, mixed>|null */
    public function document(string $documentPublicId): ?array;

    /** @return array<string, mixed> */
    public function addReviewComment(int $actorUserId, string $applicationPublicId, string $comment, DateTimeImmutable $now): array;

    /** @return array<string, mixed> */
    public function raiseQuery(int $actorUserId, string $applicationPublicId, string $query, DateTimeImmutable $now): array;

    /** @return array<string, mixed> */
    public function reject(int $actorUserId, string $applicationPublicId, string $reason, DateTimeImmutable $now): array;

    /** @return array<string, mixed> */
    public function approve(int $actorUserId, string $applicationPublicId, ?string $comment, string $numberPrefix, DateTimeImmutable $now): array;
}
