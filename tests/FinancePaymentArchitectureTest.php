<?php

declare(strict_types=1);

use App\Services\Payments\GatewayInitialization;
use App\Services\Payments\GatewayVerification;
use App\Services\Payments\PaymentGatewayInterface;
use App\Services\Payments\PaymentRepositoryInterface;
use App\Services\Payments\PaymentService;
use App\Services\Payments\UnavailablePaymentGateway;
use App\Services\Payments\WebhookNotification;
use App\Services\Payments\VerifiedPaymentListenerInterface;
use App\Services\Invoices\InvoiceRepositoryInterface;
use App\Services\Invoices\InvoiceService;

require dirname(__DIR__) . '/vendor/autoload.php';

$assert = static function (bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); };

try {
    $invoiceId = '10000000-0000-4000-8000-000000000001';
    $repository = new class implements PaymentRepositoryInterface {
        public int $credits = 0;
        public array $events = [];
        public array $expected = ['reference' => 'AIMS-REFERENCE', 'amount' => 150000, 'currency' => 'NGN', 'invoice' => 'INV-2026-0001'];
        public function invoicesForUser(int $userId): array { return []; }
        public function initiate(int $userId, string $invoicePublicId, string $gateway, string $reference, string $keyHash, string $requestHash, DateTimeImmutable $now): array { return ['payment_public_id' => '20000000-0000-4000-8000-000000000001', 'gateway_reference' => $this->expected['reference'], 'invoice_number' => $this->expected['invoice'], 'amount_minor' => $this->expected['amount'], 'currency' => $this->expected['currency'], 'email' => 'member@example.test', 'idempotent' => false]; }
        public function recordInitialization(string $paymentPublicId, bool $accepted, array $payload, DateTimeImmutable $now): void {}
        public function settle(string $gateway, string $reference, GatewayVerification $verification, string $transactionType, ?string $providerEventId, DateTimeImmutable $now): array {
            if ($providerEventId !== null && isset($this->events[$providerEventId])) return ['accepted' => true, 'idempotent' => true, 'status' => 'already_processed', 'invoice_public_id' => '10000000-0000-4000-8000-000000000001'];
            if ($providerEventId !== null) $this->events[$providerEventId] = true;
            if ($reference !== $this->expected['reference']) return ['accepted' => false, 'idempotent' => false, 'reason' => 'Payment reference does not match an invoice.'];
            if ($verification->reference !== $reference || $verification->amountMinor !== $this->expected['amount'] || $verification->currency !== $this->expected['currency'] || $verification->invoiceNumber !== $this->expected['invoice'] || $verification->status !== 'successful') return ['accepted' => false, 'idempotent' => false, 'reason' => 'Provider data does not match the invoice.'];
            if ($this->credits > 0) return ['accepted' => true, 'idempotent' => true, 'status' => 'paid', 'invoice_public_id' => '10000000-0000-4000-8000-000000000001'];
            $this->credits++;
            return ['accepted' => true, 'idempotent' => false, 'status' => 'paid', 'invoice_public_id' => '10000000-0000-4000-8000-000000000001'];
        }
    };
    $gateway = new class implements PaymentGatewayInterface {
        public int $verifications = 0;
        public int $amount = 150000;
        public string $invoice = 'INV-2026-0001';
        public string $reference = 'AIMS-REFERENCE';
        public function name(): string { return 'test-gateway'; }
        public function initialize(array $payment): GatewayInitialization { return new GatewayInitialization(true, 'https://gateway.example.test/pay', ['accepted' => true]); }
        public function verify(string $reference): GatewayVerification { $this->verifications++; return new GatewayVerification($this->reference, 'successful', $this->amount, 'NGN', $this->invoice, 'provider-1', ['status' => 'successful', 'card' => ['pan' => 'forbidden']]); }
        public function authenticateWebhook(string $rawBody, array $headers): ?WebhookNotification { return ($headers['x-test-signature'] ?? '') === hash('sha256', $rawBody) ? new WebhookNotification('event-1', $this->reference) : null; }
    };
    $listener = new class implements VerifiedPaymentListenerInterface { public int $calls=0;public function paymentVerified(string$invoicePublicId):void{$this->calls++;} };
    $service = new PaymentService($repository, $gateway, 'https://aims.example.test/payments/callback', $listener);

    $initialized = $service->initialize(7, $invoiceId, 'client-key-1234567890');
    $assert($initialized->successful && str_starts_with((string) $initialized->data['authorization_url'], 'https://'), 'Secure initialization failed.');
    $invalidReference = $service->callback('UNKNOWN-REFERENCE');
    $assert(!$invalidReference->successful && $repository->credits === 0, 'Invalid invoice reference was credited.');
    $gateway->amount = 1;
    $wrongAmount = $service->callback('AIMS-REFERENCE');
    $assert(!$wrongAmount->successful && $repository->credits === 0 && $listener->calls===0, 'Incorrect amount was credited or notified downstream.');
    $gateway->amount = 150000;
    $paid = $service->callback('AIMS-REFERENCE');
    $assert($paid->successful && $repository->credits === 1 && $gateway->verifications === 3 && $listener->calls===1, 'Server-side callback verification failed to notify the verified-payment listener.');
    $duplicateCallback = $service->callback('AIMS-REFERENCE');
    $assert($duplicateCallback->successful && ($duplicateCallback->data['idempotent'] ?? false) && $repository->credits === 1, 'Duplicate callback double-credited payment.');
    $body = '{"event":"charge.success"}';
    $badWebhook = $service->webhook('test-gateway', $body, []);
    $assert($badWebhook->status === 401, 'Unsigned webhook was accepted.');
    $headers = ['x-test-signature' => hash('sha256', $body)];
    $firstWebhook = $service->webhook('test-gateway', $body, $headers);
    $replayWebhook = $service->webhook('test-gateway', $body, $headers);
    $assert($firstWebhook->successful && $replayWebhook->successful && ($replayWebhook->data['idempotent'] ?? false) && $repository->credits === 1, 'Webhook replay was not repeat-safe.');
    $assert($gateway->verifications === 6, 'Each accepted callback/webhook did not perform provider verification.');
    $disabled = new PaymentService($repository, new UnavailablePaymentGateway(), '');
    $assert($disabled->initialize(7, $invoiceId, 'client-key-1234567890')->status === 503, 'Disabled gateway did not fail closed.');

    $invoiceRepository = new class implements InvoiceRepositoryInterface {
        public array $saved = [];
        public function issue(array $invoice, array $items, string $keyHash, string $requestHash, DateTimeImmutable $now): array { $this->saved = compact('invoice', 'items', 'keyHash', 'requestHash'); return ['public_id' => '30000000-0000-4000-8000-000000000001', 'invoice_number' => 'AIMS-INV-2026-TEST', 'idempotent' => false]; }
    };
    $invoiceService = new InvoiceService($invoiceRepository);
    $issued = $invoiceService->issue(['user_id' => 7, 'bill_to_name' => 'Test Member', 'bill_to_email' => 'member@example.test', 'source_type' => 'programme_enrolment', 'source_public_id' => $invoiceId, 'currency' => 'NGN', 'items' => [['fee_type' => 'programme_tuition', 'description' => 'Programme tuition', 'quantity' => 2, 'unit_amount_minor' => 75000]]], 'invoice-key-1234567890');
    $assert($issued->successful && $invoiceRepository->saved['invoice']['total_minor'] === 150000, 'Integer-minor-unit invoice issuing failed.');
    $tamperedInvoice = $invoiceService->issue(['user_id' => 7, 'bill_to_name' => 'Test Member', 'bill_to_email' => 'member@example.test', 'source_type' => 'programme_enrolment', 'source_public_id' => $invoiceId, 'currency' => 'NGN', 'items' => [['fee_type' => 'unsupported', 'description' => 'Tampered', 'quantity' => 1, 'unit_amount_minor' => -1]]], 'invoice-key-1234567890');
    $assert(!$tamperedInvoice->successful, 'Invalid invoice fee data was accepted.');

    $migration = (string) file_get_contents(dirname(__DIR__) . '/database/migrations/20260905_230000_create_finance_payment_tables.php');
    foreach (['CREATE TABLE fee_settings', 'CREATE TABLE invoices', 'CREATE TABLE invoice_items', 'CREATE TABLE payments', 'CREATE TABLE payment_transactions', 'CREATE TABLE idempotency_keys', 'amount_minor BIGINT UNSIGNED', 'uq_invoices_source', 'uq_payments_gateway_reference', 'uq_payment_transactions_event', 'ENGINE=InnoDB'] as $needle) $assert(str_contains($migration, $needle), "Finance migration missing {$needle}.");
    $repositoryCode = (string) file_get_contents(dirname(__DIR__) . '/app/Repositories/PaymentRepository.php');
    foreach (['FOR UPDATE', "status='paid'", 'payment.verification_failed', 'payment.verification_mismatch', 'sanitizePayload', 'amountMinor', 'invoiceNumber', 'hash_equals', 'transaction(function'] as $needle) $assert(str_contains($repositoryCode, $needle), "Payment repository safety control missing {$needle}.");
    $routes = (string) file_get_contents(dirname(__DIR__) . '/routes/web.php');
    $assert(str_contains($routes, '/payments/webhook') && str_contains($routes, '/payments/callback'), 'Payment ingress routes missing.');
    echo "Finance/payment checks passed: exact invoice verification, server-side callback verification, duplicate/replay safety, authenticated webhooks, disabled-by-default gateway, and no double credit.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Finance/payment check failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
