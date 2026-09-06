<?php

declare(strict_types=1);

namespace App\Services\ProgrammeApplications;

use DateTimeImmutable;

interface ProgrammeApplicationRepositoryInterface
{
    /** @return list<array<string, mixed>> */ public function publishedProgrammes(): array;
    /** @return list<array<string, mixed>> */ public function applicationsForUser(int $userId): array;
    /** @return array<string, mixed>|null */ public function applicationForUser(int $userId, string $publicId): ?array;
    /** @param array<string, mixed> $data
     *  @return array<string, mixed>
     */ public function saveDraft(int $userId, ?string $publicId, string $programmePublicId, array $data, DateTimeImmutable $now): array;
    /** @return array<string, mixed> */ public function submit(int $userId, string $publicId, string $reference, string $declarationName, DateTimeImmutable $now): array;
    public function withdraw(int $userId, string $publicId, DateTimeImmutable $now): void;
    /** @param array<string, mixed> $filters
     *  @return array<string, mixed>
     */ public function search(array $filters): array;
    /** @return array<string, mixed>|null */ public function administrativeApplication(string $publicId): ?array;
    /** @return array<string, mixed> */ public function review(int $actor, string $publicId, ?string $note, DateTimeImmutable $now): array;
    /** @return array<string, mixed> */ public function approve(int $actor, string $publicId, ?string $note, DateTimeImmutable $now): array;
    /** @return array<string, mixed> */ public function reject(int $actor, string $publicId, string $note, DateTimeImmutable $now): array;
    /** @return array<string, mixed> */ public function enrol(int $actor, string $publicId, string $numberPrefix, DateTimeImmutable $now): array;
    /** @return array<string, mixed> */ public function complete(int $actor, string $publicId, DateTimeImmutable $now): array;
    /** @return array<string, mixed> */ public function withdrawEnrolment(int $actor, string $publicId, string $note, DateTimeImmutable $now): array;
}
