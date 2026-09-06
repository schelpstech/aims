<?php

declare(strict_types=1);

namespace App\Services\Payments;

use RuntimeException;

final class UnavailablePaymentGateway implements PaymentGatewayInterface
{
    public function __construct(private readonly string $gatewayName = 'disabled')
    {
    }

    public function name(): string { return $this->gatewayName; }
    public function initialize(array $payment): GatewayInitialization { throw new RuntimeException('Online payment is not configured.'); }
    public function verify(string $reference): GatewayVerification { throw new RuntimeException('Online payment is not configured.'); }
    public function authenticateWebhook(string $rawBody, array $headers): ?WebhookNotification { return null; }
}
