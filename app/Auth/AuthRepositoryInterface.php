<?php

declare(strict_types=1);

namespace App\Auth;

use DateTimeImmutable;

interface AuthRepositoryInterface
{
    /** @return array<string, mixed>|null */
    public function findByEmail(string $email): ?array;

    /** @return array<string, mixed>|null */
    public function findActiveById(int $userId): ?array;

    /** @return array<string, mixed>|null */
    public function createUser(string $publicId, string $email, string $passwordHash, DateTimeImmutable $now): ?array;

    public function updatePasswordHash(int $userId, string $passwordHash, DateTimeImmutable $now): void;

    public function changePasswordAndRevokeSessions(
        int $userId,
        string $expectedPasswordHash,
        string $newPasswordHash,
        DateTimeImmutable $now,
    ): bool;

    public function storeToken(int $userId, string $purpose, string $tokenHash, DateTimeImmutable $expiresAt, DateTimeImmutable $now): void;

    public function revokeUnusedTokens(int $userId, string $purpose, DateTimeImmutable $now): void;

    public function consumeEmailVerificationToken(string $tokenHash, DateTimeImmutable $now): ?int;

    public function consumePasswordResetToken(string $tokenHash, string $passwordHash, DateTimeImmutable $now): ?int;

    /** @return array{identity: int, ip: int} */
    public function failedLoginCounts(string $identityHash, ?string $ipAddress, DateTimeImmutable $since): array;

    public function consumeRequestRateLimit(
        string $action,
        string $identityHash,
        ?string $ipAddress,
        int $identityMaximum,
        int $ipMaximum,
        int $windowSeconds,
        DateTimeImmutable $now,
    ): bool;

    public function recordLoginAttempt(
        ?int $userId,
        string $identityHash,
        ?string $ipAddress,
        string $userAgent,
        bool $successful,
        DateTimeImmutable $now,
    ): void;

    public function incrementFailedLogins(int $userId, int $maximumAttempts, DateTimeImmutable $lockedUntil): void;

    public function clearFailedLogins(int $userId): void;

    public function recordSuccessfulLogin(int $userId, DateTimeImmutable $now): void;

    public function recordSession(
        int $userId,
        string $sessionHash,
        ?string $ipAddress,
        string $userAgent,
        DateTimeImmutable $expiresAt,
        DateTimeImmutable $now,
    ): void;

    public function sessionIsActive(int $userId, string $sessionHash, DateTimeImmutable $now): bool;

    public function revokeSession(string $sessionHash, DateTimeImmutable $now): void;

    public function revokeAllSessions(int $userId, DateTimeImmutable $now): void;

    /** @param array<string, mixed> $context */
    public function audit(
        ?int $userId,
        string $action,
        string $description,
        ?string $ipAddress,
        string $userAgent,
        array $context,
        DateTimeImmutable $now,
    ): void;

    /** @param array<string, mixed> $context */
    public function securityEvent(
        ?int $userId,
        string $eventType,
        string $severity,
        string $description,
        ?string $ipAddress,
        string $userAgent,
        array $context,
        DateTimeImmutable $now,
    ): void;
}
