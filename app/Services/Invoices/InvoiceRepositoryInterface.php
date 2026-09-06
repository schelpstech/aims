<?php

declare(strict_types=1);

namespace App\Services\Invoices;

use DateTimeImmutable;

interface InvoiceRepositoryInterface
{
    /** @param array<string, mixed> $invoice @param list<array<string, mixed>> $items @return array<string, mixed> */
    public function issue(array $invoice, array $items, string $keyHash, string $requestHash, DateTimeImmutable $now): array;
}
