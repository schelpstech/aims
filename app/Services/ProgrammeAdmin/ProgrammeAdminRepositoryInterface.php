<?php

declare(strict_types=1);

namespace App\Services\ProgrammeAdmin;

interface ProgrammeAdminRepositoryInterface
{
    /** @return array<string, mixed> */
    public function dashboard(): array;
    /** @param array<string, mixed> $data */
    public function saveProgramme(int $actor, ?string $publicId, array $data): string;
    public function archiveProgramme(int $actor, string $publicId): void;
    /** @param array<string, mixed> $data */
    public function saveArea(int $actor, ?string $publicId, array $data): string;
    public function archiveArea(int $actor, string $publicId): void;
    /** @param array<string, mixed> $data */
    public function assignCoordinator(int $actor, array $data): void;
    public function removeCoordinator(int $actor, string $publicId): void;
}
