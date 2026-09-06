<?php

declare(strict_types=1);

namespace App\Services\Membership;

interface DocumentStorageInterface
{
    /** @param array<string, mixed> $file
     *  @return array{original_name: string, storage_path: string, mime_type: string, size_bytes: int, sha256: string}
     */
    public function store(array $file): array;

    public function delete(string $relativePath): void;

    public function read(string $relativePath): string;
}
