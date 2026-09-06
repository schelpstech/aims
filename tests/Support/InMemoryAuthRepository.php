<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Auth\AuthRepositoryInterface;
use DateTimeImmutable;

final class InMemoryAuthRepository implements AuthRepositoryInterface
{
    /** @var array<int, array<string, mixed>> */
    public array $users = [];

    /** @var array<string, array<string, array<string, mixed>>> */
    public array $tokens = ['email_verification' => [], 'password_reset' => []];

    /** @var list<array<string, mixed>> */
    public array $attempts = [];

    /** @var array<string, int> */
    public array $requestRateLimits = [];

    /** @var array<string, array<string, mixed>> */
    public array $sessions = [];

    /** @var list<array<string, mixed>> */
    public array $audits = [];

    /** @var list<array<string, mixed>> */
    public array $securityEvents = [];

    /** @param list<array<string, mixed>> $users */
    public function __construct(array $users = [])
    {
        foreach ($users as $user) {
            $this->users[(int) $user['id']] = $user;
        }
    }

    public function findByEmail(string $email): ?array
    {
        foreach ($this->users as $user) {
            if ($user['email'] === $email && empty($user['deleted_at'])) {
                return $user;
            }
        }

        return null;
    }

    public function findActiveById(int $userId): ?array
    {
        $user = $this->users[$userId] ?? null;
        if ($user === null || $user['status'] !== 'active' || empty($user['email_verified_at']) || !empty($user['deleted_at'])) {
            return null;
        }

        return $user;
    }

    public function createUser(string $publicId, string $email, string $passwordHash, DateTimeImmutable $now): ?array
    {
        if ($this->identityExists($email)) {
            return null;
        }

        $id = $this->users === [] ? 1 : max(array_keys($this->users)) + 1;
        $this->users[$id] = [
            'id' => $id,
            'public_id' => $publicId,
            'email' => $email,
            'password_hash' => $passwordHash,
            'status' => 'pending',
            'email_verified_at' => null,
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'deleted_at' => null,
            'created_at' => $now->format('Y-m-d H:i:s.u'),
        ];

        return $this->users[$id];
    }

    public function updatePasswordHash(int $userId, string $passwordHash, DateTimeImmutable $now): void
    {
        $this->users[$userId]['password_hash'] = $passwordHash;
        $this->users[$userId]['password_changed_at'] = $now->format('Y-m-d H:i:s.u');
    }

    public function storeToken(int $userId, string $purpose, string $tokenHash, DateTimeImmutable $expiresAt, DateTimeImmutable $now): void
    {
        $this->tokens[$purpose][$tokenHash] = [
            'user_id' => $userId,
            'expires_at' => $expiresAt,
            'used_at' => null,
            'created_at' => $now,
        ];
    }

    public function revokeUnusedTokens(int $userId, string $purpose, DateTimeImmutable $now): void
    {
        foreach ($this->tokens[$purpose] as &$token) {
            if ($token['user_id'] === $userId && $token['used_at'] === null) {
                $token['used_at'] = $now;
            }
        }
        unset($token);
    }

    public function consumeEmailVerificationToken(string $tokenHash, DateTimeImmutable $now): ?int
    {
        if (!isset($this->tokens['email_verification'][$tokenHash])) {
            return null;
        }
        $token = &$this->tokens['email_verification'][$tokenHash];
        if (!$this->usable($token, $now)) {
            return null;
        }

        $token['used_at'] = $now;
        $userId = (int) $token['user_id'];
        $this->users[$userId]['email_verified_at'] = $now->format('Y-m-d H:i:s.u');
        if ($this->users[$userId]['status'] === 'pending') {
            $this->users[$userId]['status'] = 'active';
        }

        return $userId;
    }

    public function consumePasswordResetToken(string $tokenHash, string $passwordHash, DateTimeImmutable $now): ?int
    {
        if (!isset($this->tokens['password_reset'][$tokenHash])) {
            return null;
        }
        $token = &$this->tokens['password_reset'][$tokenHash];
        if (!$this->usable($token, $now)) {
            return null;
        }

        $token['used_at'] = $now;
        $userId = (int) $token['user_id'];
        $this->users[$userId]['password_hash'] = $passwordHash;
        $this->users[$userId]['password_changed_at'] = $now->format('Y-m-d H:i:s.u');
        $this->users[$userId]['failed_login_attempts'] = 0;
        $this->users[$userId]['locked_until'] = null;
        $this->revokeUnusedTokens($userId, 'password_reset', $now);
        $this->revokeAllSessions($userId, $now);

        return $userId;
    }

    public function failedLoginCounts(string $identityHash, ?string $ipAddress, DateTimeImmutable $since): array
    {
        $identity = 0;
        $ip = 0;
        foreach ($this->attempts as $attempt) {
            if ($attempt['successful'] || $attempt['at'] < $since) {
                continue;
            }
            if ($attempt['identity_hash'] === $identityHash) {
                $identity++;
            }
            if ($ipAddress !== null && $attempt['ip'] === $ipAddress) {
                $ip++;
            }
        }

        return compact('identity', 'ip');
    }

    public function consumeRequestRateLimit(
        string $action,
        string $identityHash,
        ?string $ipAddress,
        int $identityMaximum,
        int $ipMaximum,
        int $windowSeconds,
        DateTimeImmutable $now,
    ): bool {
        $bucket = (string) ((int) floor($now->getTimestamp() / max(60, $windowSeconds)));
        $identityKey = implode('|', [$action, 'identity', $identityHash, $bucket]);
        $this->requestRateLimits[$identityKey] = ($this->requestRateLimits[$identityKey] ?? 0) + 1;
        $ipCount = 0;
        if ($ipAddress !== null) {
            $ipKey = implode('|', [$action, 'ip', hash('sha256', $ipAddress), $bucket]);
            $this->requestRateLimits[$ipKey] = ($this->requestRateLimits[$ipKey] ?? 0) + 1;
            $ipCount = $this->requestRateLimits[$ipKey];
        }

        return $this->requestRateLimits[$identityKey] <= max(1, $identityMaximum)
            && ($ipAddress === null || $ipCount <= max(1, $ipMaximum));
    }

    public function recordLoginAttempt(
        ?int $userId,
        string $identityHash,
        ?string $ipAddress,
        string $userAgent,
        bool $successful,
        DateTimeImmutable $now,
    ): void {
        $this->attempts[] = [
            'user_id' => $userId,
            'identity_hash' => $identityHash,
            'ip' => $ipAddress,
            'user_agent' => $userAgent,
            'successful' => $successful,
            'at' => $now,
        ];
    }

    public function incrementFailedLogins(int $userId, int $maximumAttempts, DateTimeImmutable $lockedUntil): void
    {
        $this->users[$userId]['failed_login_attempts']++;
        if ($this->users[$userId]['failed_login_attempts'] >= $maximumAttempts) {
            $this->users[$userId]['locked_until'] = $lockedUntil->format('Y-m-d H:i:s.u');
        }
    }

    public function clearFailedLogins(int $userId): void
    {
        $this->users[$userId]['failed_login_attempts'] = 0;
        $this->users[$userId]['locked_until'] = null;
    }

    public function recordSuccessfulLogin(int $userId, DateTimeImmutable $now): void
    {
        $this->users[$userId]['last_login_at'] = $now->format('Y-m-d H:i:s.u');
    }

    public function recordSession(
        int $userId,
        string $sessionHash,
        ?string $ipAddress,
        string $userAgent,
        DateTimeImmutable $expiresAt,
        DateTimeImmutable $now,
    ): void {
        $this->sessions[$sessionHash] = [
            'user_id' => $userId,
            'ip' => $ipAddress,
            'user_agent' => $userAgent,
            'expires_at' => $expiresAt,
            'last_activity_at' => $now,
            'revoked_at' => null,
        ];
    }

    public function sessionIsActive(int $userId, string $sessionHash, DateTimeImmutable $now): bool
    {
        $session = &$this->sessions[$sessionHash];
        if (!is_array($session)
            || $session['user_id'] !== $userId
            || $session['revoked_at'] !== null
            || $session['expires_at'] <= $now
        ) {
            return false;
        }

        $session['last_activity_at'] = $now;
        return true;
    }

    public function revokeSession(string $sessionHash, DateTimeImmutable $now): void
    {
        if (isset($this->sessions[$sessionHash]) && $this->sessions[$sessionHash]['revoked_at'] === null) {
            $this->sessions[$sessionHash]['revoked_at'] = $now;
        }
    }

    public function revokeAllSessions(int $userId, DateTimeImmutable $now): void
    {
        foreach ($this->sessions as &$session) {
            if ($session['user_id'] === $userId && $session['revoked_at'] === null) {
                $session['revoked_at'] = $now;
            }
        }
        unset($session);
    }

    public function audit(
        ?int $userId,
        string $action,
        string $description,
        ?string $ipAddress,
        string $userAgent,
        array $context,
        DateTimeImmutable $now,
    ): void {
        $this->audits[] = compact('userId', 'action', 'description', 'ipAddress', 'userAgent', 'context', 'now');
    }

    public function securityEvent(
        ?int $userId,
        string $eventType,
        string $severity,
        string $description,
        ?string $ipAddress,
        string $userAgent,
        array $context,
        DateTimeImmutable $now,
    ): void {
        $this->securityEvents[] = compact(
            'userId',
            'eventType',
            'severity',
            'description',
            'ipAddress',
            'userAgent',
            'context',
            'now',
        );
    }

    private function identityExists(string $email): bool
    {
        return $this->findByEmail($email) !== null;
    }

    /** @param array<string, mixed>|null $token */
    private function usable(?array $token, DateTimeImmutable $now): bool
    {
        return is_array($token) && $token['used_at'] === null && $token['expires_at'] > $now;
    }
}
