<?php

declare(strict_types=1);

namespace App\Services\Leadership;

interface LeadershipDirectoryInterface
{
    /** @return array{available: bool, groups: list<array<string, mixed>>} */
    public function directory(): array;
}
