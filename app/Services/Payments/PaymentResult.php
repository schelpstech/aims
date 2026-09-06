<?php

declare(strict_types=1);

namespace App\Services\Payments;

final readonly class PaymentResult
{
    /** @param array<string, mixed> $data */
    private function __construct(public bool $successful, public int $status, public string $message, public array $data = []) {}
    /** @param array<string, mixed> $data */ public static function success(string $message, array $data = []): self { return new self(true, 200, $message, $data); }
    /** @param array<string, mixed> $data */ public static function failure(int $status, string $message, array $data = []): self { return new self(false, $status, $message, $data); }
}
