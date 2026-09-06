<?php

declare(strict_types=1);

namespace App\Auth;

final class AuthResult
{
    private function __construct(
        public readonly bool $successful,
        public readonly string $code,
        public readonly string $message,
        public readonly ?int $retryAfter = null,
    ) {
    }

    public static function success(string $code, string $message): self
    {
        return new self(true, $code, $message);
    }

    public static function failure(string $code, string $message, ?int $retryAfter = null): self
    {
        return new self(false, $code, $message, $retryAfter);
    }
}
