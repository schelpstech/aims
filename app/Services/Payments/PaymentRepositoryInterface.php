<?php

declare(strict_types=1);

namespace App\Services\Payments;

use DateTimeImmutable;

interface PaymentRepositoryInterface
{
    /** @return list<array<string, mixed>> */
    public function invoicesForUser(int $userId): array;

    /** @return array<string, mixed> */
    public function initiate(int $userId, string $invoicePublicId, string $gateway, string $reference, string $keyHash, string $requestHash, DateTimeImmutable $now): array;

    /** @param array<string, mixed> $payload */
    public function recordInitialization(string $paymentPublicId, bool $accepted, array $payload, DateTimeImmutable $now): void;

    /** @return array<string, mixed> */
    public function settle(string $gateway, string $reference, GatewayVerification $verification, string $transactionType, ?string $providerEventId, DateTimeImmutable $now): array;
}
