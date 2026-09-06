<?php

declare(strict_types=1);

namespace App\Auth;

use DateTimeImmutable;

interface TokenDeliveryInterface
{
    public function sendEmailVerification(string $email, string $token, DateTimeImmutable $expiresAt): bool;

    public function sendPasswordReset(string $email, string $token, DateTimeImmutable $expiresAt): bool;
}
