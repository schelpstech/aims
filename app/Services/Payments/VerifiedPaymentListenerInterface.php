<?php

declare(strict_types=1);

namespace App\Services\Payments;

interface VerifiedPaymentListenerInterface
{
    public function paymentVerified(string $invoicePublicId): void;
}
