<?php

declare(strict_types=1);

namespace App\Services\Payments;

final readonly class GatewayVerification
{
    /** @param array<string, mixed> $rawResponse */
    public function __construct(
        public string $reference,
        public string $status,
        public int $amountMinor,
        public string $currency,
        public ?string $invoiceNumber,
        public ?string $providerTransactionId,
        public array $rawResponse = [],
    ) {
    }
}
