<?php

declare(strict_types=1);

namespace App\Services\Invoices;

use DateTimeImmutable;
use DomainException;

final class InvoiceService
{
    private const FEE_TYPES = ['membership_application', 'membership_renewal', 'programme_application', 'programme_tuition', 'event_registration', 'other'];
    private const SOURCE_TYPES = ['membership_application', 'membership_renewal', 'programme_application', 'programme_enrolment', 'event_registration', 'other'];

    public function __construct(private readonly InvoiceRepositoryInterface $repository) {}

    /** @param array<string, mixed> $input */
    public function issue(array $input, string $idempotencyKey): InvoiceResult
    {
        $user = filter_var($input['user_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $name = is_string($input['bill_to_name'] ?? null) ? trim($input['bill_to_name']) : '';
        $email = is_string($input['bill_to_email'] ?? null) ? strtolower(trim($input['bill_to_email'])) : '';
        $sourceType = is_string($input['source_type'] ?? null) ? trim($input['source_type']) : '';
        $sourceId = is_string($input['source_public_id'] ?? null) ? strtolower(trim($input['source_public_id'])) : '';
        $currency = is_string($input['currency'] ?? null) ? strtoupper(trim($input['currency'])) : '';
        $items = is_array($input['items'] ?? null) ? $input['items'] : [];
        if ($user === false || mb_strlen($name) < 2 || mb_strlen($name) > 200 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) return InvoiceResult::failure(422, 'Valid invoice ownership and billing details are required.');
        if (!in_array($sourceType, self::SOURCE_TYPES, true) || !$this->uuid($sourceId) || preg_match('/^[A-Z]{3}$/D', $currency) !== 1) return InvoiceResult::failure(422, 'Invoice source or currency is invalid.');
        $key = trim($idempotencyKey);
        if (strlen($key) < 16 || strlen($key) > 200 || preg_match('/^[A-Za-z0-9._:-]+$/D', $key) !== 1) return InvoiceResult::failure(422, 'A valid idempotency key is required.');
        $normalized = [];
        $total = 0;
        foreach ($items as $item) {
            if (!is_array($item)) return InvoiceResult::failure(422, 'Invoice items are invalid.');
            $type = is_string($item['fee_type'] ?? null) ? trim($item['fee_type']) : '';
            $description = is_string($item['description'] ?? null) ? trim($item['description']) : '';
            $quantity = filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
            $amount = filter_var($item['unit_amount_minor'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
            if (!in_array($type, self::FEE_TYPES, true) || $quantity === false || $amount === false || $description === '' || mb_strlen($description) > 255) return InvoiceResult::failure(422, 'Invoice line item is invalid.');
            $line = $quantity * $amount;
            if ($line > PHP_INT_MAX - $total) return InvoiceResult::failure(422, 'Invoice total is too large.');
            $total += $line;
            $feeSetting = is_string($item['fee_setting_public_id'] ?? null) ? strtolower(trim($item['fee_setting_public_id'])) : null;
            if ($feeSetting === '') $feeSetting = null;
            if ($feeSetting !== null && !$this->uuid($feeSetting)) return InvoiceResult::failure(422, 'Fee setting reference is invalid.');
            $normalized[] = ['fee_type' => $type, 'description' => $description, 'quantity' => $quantity, 'unit_amount_minor' => $amount, 'line_total_minor' => $line, 'fee_setting_public_id' => $feeSetting];
        }
        if ($normalized === [] || $total < 1) return InvoiceResult::failure(422, 'Invoice must contain a positive total.');
        $due = $this->due($input['due_at'] ?? null);
        if (($input['due_at'] ?? null) !== null && ($input['due_at'] ?? '') !== '' && $due === null) return InvoiceResult::failure(422, 'Invoice due date is invalid.');
        $invoice = ['user_id' => $user, 'bill_to_name' => $name, 'bill_to_email' => $email, 'source_type' => $sourceType, 'source_public_id' => $sourceId, 'currency' => $currency, 'total_minor' => $total, 'due_at' => $due];
        $requestHash = hash('sha256', json_encode([$invoice, $normalized], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        try { return InvoiceResult::success('Invoice issued.', $this->repository->issue($invoice, $normalized, hash('sha256', $key), $requestHash, new DateTimeImmutable('now'))); }
        catch (DomainException $e) { return InvoiceResult::failure(409, $e->getMessage()); }
    }

    private function due(mixed $value): ?string { if ($value === null || $value === '') return null; if (!is_string($value)) return null; $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', trim($value)); return $date && $date->format('Y-m-d H:i:s') === trim($value) ? trim($value) : null; }
    private function uuid(string $value): bool { return preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D', $value) === 1; }
}
