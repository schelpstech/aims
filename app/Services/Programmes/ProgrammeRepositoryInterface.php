<?php

declare(strict_types=1);

namespace App\Services\Programmes;

interface ProgrammeRepositoryInterface
{
    /** @return list<array<string, mixed>> */
    public function programmeRows(?string $slug = null): array;

    /** @return list<array<string, mixed>> */
    public function areaRows(?string $slug = null): array;

    /** @return list<array<string, mixed>> */
    public function programmeTypes(): array;
}
