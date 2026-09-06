<?php

declare(strict_types=1);

namespace App\Services\Payments;

interface PaymentGatewayInterface
{
    public function name(): string;

    /** @param array{reference:string,invoice_number:string,amount_minor:int,currency:string,email:string,callback_url:string} $payment */
    public function initialize(array $payment): GatewayInitialization;

    public function verify(string $reference): GatewayVerification;

    /**
     * A concrete adapter must authenticate the provider signature before returning a notification.
     *
     * @param array<string, string> $headers
     */
    public function authenticateWebhook(string $rawBody, array $headers): ?WebhookNotification;
}
