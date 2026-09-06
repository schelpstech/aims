<?php

declare(strict_types=1);

namespace App\Services\Leadership;

interface LeadershipRepositoryInterface
{
    /** @return list<array<string, mixed>> */
    public function publicDirectoryRows(): array;
}
