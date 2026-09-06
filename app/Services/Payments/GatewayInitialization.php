<?php

declare(strict_types=1);

namespace App\Services\Payments;

final readonly class GatewayInitialization
{
    /** @param array<string, mixed> $rawResponse */
    public function __construct(
        public bool $accepted,
        public ?string $authorizationUrl,
        public array $rawResponse = [],
        public ?string $message = null,
    ) {
    }
}
