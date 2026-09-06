<?php

declare(strict_types=1);

namespace App\Services\MemberPortal;

final readonly class MemberPortalResult
{
    /** @param array<string, mixed>|null $data
     *  @param array<string, list<string>> $errors
     */
    private function __construct(
        public bool $successful,
        public int $status,
        public string $message,
        public ?array $data = null,
        public array $errors = [],
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function success(array $data, string $message = ''): self
    {
        return new self(true, 200, $message, $data);
    }

    /** @param array<string, list<string>> $errors */
    public static function failure(int $status, string $message, array $errors = []): self
    {
        return new self(false, $status, $message, null, $errors);
    }
}
