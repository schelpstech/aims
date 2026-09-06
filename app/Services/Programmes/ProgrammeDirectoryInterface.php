<?php

declare(strict_types=1);

namespace App\Services\Programmes;

interface ProgrammeDirectoryInterface
{
    /** @return array{available: bool, types: list<array<string, mixed>>, programmes: list<array<string, mixed>>} */
    public function catalogue(): array;

    /** @return array{available: bool, areas: list<array<string, mixed>>} */
    public function areas(): array;

    /** @return array<string, mixed>|null */
    public function programme(string $slug): ?array;

    /** @return array<string, mixed>|null */
    public function area(string $slug): ?array;
}
