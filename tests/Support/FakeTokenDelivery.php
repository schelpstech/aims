<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Auth\TokenDeliveryInterface;
use DateTimeImmutable;

final class FakeTokenDelivery implements TokenDeliveryInterface
{
    /** @var list<array{email: string, token: string, expires_at: DateTimeImmutable}> */
    public array $verifications = [];

    /** @var list<array{email: string, token: string, expires_at: DateTimeImmutable}> */
    public array $resets = [];

    public function sendEmailVerification(string $email, string $token, DateTimeImmutable $expiresAt): bool
    {
        $this->verifications[] = ['email' => $email, 'token' => $token, 'expires_at' => $expiresAt];
        return true;
    }

    public function sendPasswordReset(string $email, string $token, DateTimeImmutable $expiresAt): bool
    {
        $this->resets[] = ['email' => $email, 'token' => $token, 'expires_at' => $expiresAt];
        return true;
    }
}
