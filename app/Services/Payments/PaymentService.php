<?php

declare(strict_types=1);

namespace App\Services\Payments;

use DateTimeImmutable;
use DomainException;
use Throwable;

final class PaymentService
{
    public function __construct(
        private readonly PaymentRepositoryInterface $repository,
        private readonly PaymentGatewayInterface $gateway,
        private readonly string $callbackUrl,
        private readonly ?VerifiedPaymentListenerInterface $verifiedPaymentListener = null,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function invoices(int $userId): array { return $userId > 0 ? $this->repository->invoicesForUser($userId) : []; }

    public function initialize(int $userId, string $invoiceId, string $idempotencyKey): PaymentResult
    {
        if ($userId < 1 || !$this->uuid($invoiceId)) return PaymentResult::failure(404, 'Invoice not found.');
        $key = trim($idempotencyKey);
        if (strlen($key) < 16 || strlen($key) > 200 || preg_match('/^[A-Za-z0-9._:-]+$/D', $key) !== 1) return PaymentResult::failure(422, 'A valid idempotency key is required.');
        if ($this->gateway->name() === 'disabled') return PaymentResult::failure(503, 'Online payment is not configured.');
        try {
            $requestHash = hash('sha256', $userId . '|' . strtolower($invoiceId) . '|' . $this->gateway->name());
            $payment = $this->repository->initiate($userId, $invoiceId, $this->gateway->name(), 'AIMS-' . strtoupper(bin2hex(random_bytes(12))), hash('sha256', $key), $requestHash, new DateTimeImmutable('now'));
            if (($payment['idempotent'] ?? false) && isset($payment['authorization_url'])) return PaymentResult::success('Payment initialization already processed.', $payment);
            $initialization = $this->gateway->initialize([
                'reference' => (string) $payment['gateway_reference'], 'invoice_number' => (string) $payment['invoice_number'],
                'amount_minor' => (int) $payment['amount_minor'], 'currency' => (string) $payment['currency'],
                'email' => (string) $payment['email'], 'callback_url' => $this->callbackUrl,
            ]);
            $this->repository->recordInitialization((string) $payment['payment_public_id'], $initialization->accepted, $initialization->rawResponse, new DateTimeImmutable('now'));
            if (!$initialization->accepted || !$this->safeHttps($initialization->authorizationUrl)) return PaymentResult::failure(502, 'The payment provider did not accept this transaction.');
            return PaymentResult::success('Payment initialized.', ['authorization_url' => $initialization->authorizationUrl, 'reference' => $payment['gateway_reference']]);
        } catch (DomainException $e) { return PaymentResult::failure(409, $e->getMessage()); }
        catch (Throwable) { return PaymentResult::failure(502, 'Payment initialization is temporarily unavailable.'); }
    }

    public function callback(string $reference): PaymentResult
    {
        return $this->verifyAndSettle($reference, 'verify', null);
    }

    /** @param array<string, string> $headers */
    public function webhook(string $gateway, string $rawBody, array $headers): PaymentResult
    {
        if (!hash_equals($this->gateway->name(), strtolower(trim($gateway)))) return PaymentResult::failure(404, 'Payment gateway not found.');
        $notification = $this->gateway->authenticateWebhook($rawBody, $headers);
        if ($notification === null) return PaymentResult::failure(401, 'Webhook authentication failed.');
        return $this->verifyAndSettle($notification->reference, 'webhook', $notification->eventId);
    }

    private function verifyAndSettle(string $reference, string $type, ?string $eventId): PaymentResult
    {
        $reference = trim($reference);
        if ($reference === '' || strlen($reference) > 120 || preg_match('/^[A-Za-z0-9._:-]+$/D', $reference) !== 1) return PaymentResult::failure(422, 'Invalid payment reference.');
        if ($this->gateway->name() === 'disabled') return PaymentResult::failure(503, 'Online payment is not configured.');
        try {
            // Redirects and webhook bodies are never proof: the adapter performs a fresh server-to-server lookup.
            $verified = $this->gateway->verify($reference);
            $settled = $this->repository->settle($this->gateway->name(), $reference, $verified, $type, $eventId, new DateTimeImmutable('now'));
            if (!($settled['accepted'] ?? false)) return PaymentResult::failure(409, (string) ($settled['reason'] ?? 'Payment verification was rejected.'), $settled);
            if (isset($settled['invoice_public_id']) && is_string($settled['invoice_public_id'])) $this->verifiedPaymentListener?->paymentVerified($settled['invoice_public_id']);
            return PaymentResult::success(($settled['idempotent'] ?? false) ? 'Payment was already processed.' : 'Payment verified.', $settled);
        } catch (DomainException $e) { return PaymentResult::failure(409, $e->getMessage()); }
        catch (Throwable) { return PaymentResult::failure(502, 'Payment verification is temporarily unavailable.'); }
    }

    private function uuid(string $value): bool { return preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/Di', $value) === 1; }
    private function safeHttps(?string $url): bool { return is_string($url) && filter_var($url, FILTER_VALIDATE_URL) !== false && strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https'; }
}
