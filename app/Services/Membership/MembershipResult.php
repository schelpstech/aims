<?php

declare(strict_types=1);

namespace App\Services\Membership;

final readonly class MembershipResult
{
    /** @param array<string, list<string>> $errors
     *  @param array<string, mixed>|null $application
     */
    private function __construct(
        public bool $successful,
        public string $code,
        public string $message,
        public array $errors = [],
        public ?array $application = null,
    ) {
    }

    /** @param array<string, mixed>|null $application */
    public static function success(string $code, string $message, ?array $application = null): self
    {
        return new self(true, $code, $message, [], $application);
    }

    /** @param array<string, list<string>> $errors */
    public static function failure(string $code, string $message, array $errors = []): self
    {
        return new self(false, $code, $message, $errors);
    }
}
