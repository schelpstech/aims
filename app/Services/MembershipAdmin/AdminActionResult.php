<?php

declare(strict_types=1);

namespace App\Services\MembershipAdmin;

final readonly class AdminActionResult
{
    /** @param array<string, mixed>|null $data */
    private function __construct(
        public bool $successful,
        public int $status,
        public string $message,
        public ?array $data = null,
    ) {
    }

    /** @param array<string, mixed>|null $data */
    public static function success(string $message, ?array $data = null): self
    {
        return new self(true, 200, $message, $data);
    }

    public static function failure(int $status, string $message): self
    {
        return new self(false, $status, $message);
    }
}
