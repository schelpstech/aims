<?php

declare(strict_types=1);

namespace App\Auth;

use App\Database\Connection;
use App\Repositories\Repository;
use DateTimeImmutable;
use JsonException;
use PDO;
use PDOException;
use Throwable;

final class PdoAuthRepository extends Repository implements AuthRepositoryInterface
{
    public function __construct(Connection $database)
    {
        parent::__construct($database);
    }

    public function findByEmail(string $email): ?array
    {
        $statement = $this->connection()->prepare(
            'SELECT id, public_id, email, password_hash, status, email_verified_at, failed_login_attempts, locked_until
             FROM users WHERE email = :email AND deleted_at IS NULL LIMIT 1',
        );
        $statement->execute(['email' => $email]);
        $user = $statement->fetch();

        return is_array($user) ? $user : null;
    }

    public function findActiveById(int $userId): ?array
    {
        $statement = $this->connection()->prepare(
            "SELECT id, public_id, email, status, email_verified_at
             FROM users
             WHERE id = :id AND status = 'active' AND email_verified_at IS NOT NULL AND deleted_at IS NULL
             LIMIT 1",
        );
        $statement->execute(['id' => $userId]);
        $user = $statement->fetch();

        return is_array($user) ? $user : null;
    }

    public function createUser(string $publicId, string $email, string $passwordHash, DateTimeImmutable $now): ?array
    {
        try {
            $statement = $this->connection()->prepare(<<<'SQL'
                INSERT INTO users (public_id, email, password_hash, status, password_changed_at, created_at, updated_at)
                VALUES (:public_id, :email, :password_hash, 'pending', :password_changed_at, :created_at, :updated_at)
                SQL);
            $statement->execute([
                'public_id' => $publicId,
                'email' => $email,
                'password_hash' => $passwordHash,
                'password_changed_at' => $this->date($now),
                'created_at' => $this->date($now),
                'updated_at' => $this->date($now),
            ]);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                return null;
            }
            throw $exception;
        }

        return $this->findByEmail($email);
    }

    public function updatePasswordHash(int $userId, string $passwordHash, DateTimeImmutable $now): void
    {
        $statement = $this->connection()->prepare(
            'UPDATE users SET password_hash = :password_hash, password_changed_at = :now WHERE id = :id',
        );
        $statement->execute(['password_hash' => $passwordHash, 'now' => $this->date($now), 'id' => $userId]);
    }

    public function changePasswordAndRevokeSessions(
        int $userId,
        string $expectedPasswordHash,
        string $newPasswordHash,
        DateTimeImmutable $now,
    ): bool {
        return $this->transaction(function (PDO $database) use ($userId, $expectedPasswordHash, $newPasswordHash, $now): bool {
            $timestamp = $this->date($now);
            $update = $database->prepare(<<<'SQL'
                UPDATE users
                SET password_hash = :password_hash,
                    password_changed_at = :changed_at,
                    failed_login_attempts = 0,
                    locked_until = NULL,
                    updated_at = :updated_at
                WHERE id = :id
                  AND password_hash = :expected_password_hash
                  AND status = 'active'
                  AND deleted_at IS NULL
                SQL);
            $update->execute([
                'password_hash' => $newPasswordHash,
                'expected_password_hash' => $expectedPasswordHash,
                'changed_at' => $timestamp,
                'updated_at' => $timestamp,
                'id' => $userId,
            ]);
            if ($update->rowCount() !== 1) {
                return false;
            }

            $tokens = $database->prepare(
                'UPDATE password_reset_tokens SET used_at = :now WHERE user_id = :user_id AND used_at IS NULL',
            );
            $tokens->execute(['now' => $timestamp, 'user_id' => $userId]);

            $sessions = $database->prepare(
                'UPDATE sessions SET revoked_at = :now WHERE user_id = :user_id AND revoked_at IS NULL',
            );
            $sessions->execute(['now' => $timestamp, 'user_id' => $userId]);

            return true;
        });
    }

    public function storeToken(int $userId, string $purpose, string $tokenHash, DateTimeImmutable $expiresAt, DateTimeImmutable $now): void
    {
        $table = $this->tokenTable($purpose);
        $statement = $this->connection()->prepare(
            "INSERT INTO {$table} (user_id, token_hash, expires_at, created_at)
             VALUES (:user_id, :token_hash, :expires_at, :created_at)",
        );
        $statement->execute([
            'user_id' => $userId,
            'token_hash' => $tokenHash,
            'expires_at' => $this->date($expiresAt),
            'created_at' => $this->date($now),
        ]);
    }

    public function revokeUnusedTokens(int $userId, string $purpose, DateTimeImmutable $now): void
    {
        $table = $this->tokenTable($purpose);
        $statement = $this->connection()->prepare(
            "UPDATE {$table} SET used_at = :now WHERE user_id = :user_id AND used_at IS NULL",
        );
        $statement->execute(['now' => $this->date($now), 'user_id' => $userId]);
    }

    public function consumeEmailVerificationToken(string $tokenHash, DateTimeImmutable $now): ?int
    {
        return $this->transaction(function (PDO $database) use ($tokenHash, $now): ?int {
            $userId = $this->lockUsableToken($database, 'email_verification_tokens', $tokenHash, $now);
            if ($userId === null) {
                return null;
            }

            $this->markTokenUsed($database, 'email_verification_tokens', $tokenHash, $now);
            $statement = $database->prepare(<<<'SQL'
                UPDATE users
                SET email_verified_at = COALESCE(email_verified_at, :verified_at),
                    status = CASE WHEN status = 'pending' THEN 'active' ELSE status END,
                    updated_at = :updated_at
                WHERE id = :id AND deleted_at IS NULL
                SQL);
            $statement->execute([
                'verified_at' => $this->date($now),
                'updated_at' => $this->date($now),
                'id' => $userId,
            ]);

            return $statement->rowCount() === 1 ? $userId : null;
        });
    }

    public function consumePasswordResetToken(string $tokenHash, string $passwordHash, DateTimeImmutable $now): ?int
    {
        return $this->transaction(function (PDO $database) use ($tokenHash, $passwordHash, $now): ?int {
            $userId = $this->lockUsableToken($database, 'password_reset_tokens', $tokenHash, $now);
            if ($userId === null) {
                return null;
            }

            $this->markTokenUsed($database, 'password_reset_tokens', $tokenHash, $now);
            $statement = $database->prepare(<<<'SQL'
                UPDATE users
                SET password_hash = :password_hash,
                    password_changed_at = :password_changed_at,
                    failed_login_attempts = 0,
                    locked_until = NULL,
                    updated_at = :updated_at
                WHERE id = :id AND deleted_at IS NULL
                SQL);
            $statement->execute([
                'password_hash' => $passwordHash,
                'password_changed_at' => $this->date($now),
                'updated_at' => $this->date($now),
                'id' => $userId,
            ]);
            if ($statement->rowCount() !== 1) {
                return null;
            }

            $revokeTokens = $database->prepare(
                'UPDATE password_reset_tokens SET used_at = :now WHERE user_id = :user_id AND used_at IS NULL',
            );
            $revokeTokens->execute(['now' => $this->date($now), 'user_id' => $userId]);

            $revokeSessions = $database->prepare(
                'UPDATE sessions SET revoked_at = :now WHERE user_id = :user_id AND revoked_at IS NULL',
            );
            $revokeSessions->execute(['now' => $this->date($now), 'user_id' => $userId]);

            return $userId;
        });
    }

    public function failedLoginCounts(string $identityHash, ?string $ipAddress, DateTimeImmutable $since): array
    {
        $statement = $this->connection()->prepare(<<<'SQL'
            SELECT COUNT(*) FROM login_attempts
            WHERE identity_hash = :identity_hash AND successful = 0 AND attempted_at >= :since
            SQL);
        $statement->execute(['identity_hash' => $identityHash, 'since' => $this->date($since)]);
        $identityCount = (int) $statement->fetchColumn();
        $ipCount = 0;

        if ($ipAddress !== null) {
            $statement = $this->connection()->prepare(<<<'SQL'
                SELECT COUNT(*) FROM login_attempts
                WHERE ip_address = INET6_ATON(:ip_address) AND successful = 0 AND attempted_at >= :since
                SQL);
            $statement->execute(['ip_address' => $ipAddress, 'since' => $this->date($since)]);
            $ipCount = (int) $statement->fetchColumn();
        }

        return ['identity' => $identityCount, 'ip' => $ipCount];
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
        if (preg_match('/^[a-z][a-z0-9._-]{1,39}$/D', $action) !== 1
            || preg_match('/^[a-f0-9]{64}$/D', $identityHash) !== 1
        ) {
            throw new \InvalidArgumentException('Invalid authentication rate-limit scope.');
        }

        $windowSeconds = max(60, $windowSeconds);
        $windowStart = (new DateTimeImmutable('@' . ((int) floor($now->getTimestamp() / $windowSeconds) * $windowSeconds)))
            ->setTimezone($now->getTimezone());

        return $this->transaction(function (PDO $database) use ($action, $identityHash, $ipAddress, $identityMaximum, $ipMaximum, $windowStart, $now): bool {
            $identityCount = $this->incrementRequestRateLimit($database, $action, 'identity', $identityHash, $windowStart, $now);
            $ipCount = 0;
            if ($ipAddress !== null) {
                $ipCount = $this->incrementRequestRateLimit($database, $action, 'ip', hash('sha256', $ipAddress), $windowStart, $now);
            }

            return $identityCount <= max(1, $identityMaximum)
                && ($ipAddress === null || $ipCount <= max(1, $ipMaximum));
        });
    }

    public function recordLoginAttempt(
        ?int $userId,
        string $identityHash,
        ?string $ipAddress,
        string $userAgent,
        bool $successful,
        DateTimeImmutable $now,
    ): void {
        $statement = $this->connection()->prepare(<<<'SQL'
            INSERT INTO login_attempts (user_id, identity_hash, ip_address, user_agent, successful, attempted_at)
            VALUES (:user_id, :identity_hash, INET6_ATON(NULLIF(:ip_address, '')), :user_agent, :successful, :attempted_at)
            SQL);
        $statement->execute([
            'user_id' => $userId,
            'identity_hash' => $identityHash,
            'ip_address' => $ipAddress ?? '',
            'user_agent' => $this->userAgent($userAgent),
            'successful' => $successful ? 1 : 0,
            'attempted_at' => $this->date($now),
        ]);
    }

    public function incrementFailedLogins(int $userId, int $maximumAttempts, DateTimeImmutable $lockedUntil): void
    {
        $statement = $this->connection()->prepare(<<<'SQL'
            UPDATE users
            SET locked_until = CASE
                    WHEN failed_login_attempts + 1 >= :maximum_attempts THEN :locked_until
                    ELSE locked_until
                END,
                failed_login_attempts = failed_login_attempts + 1
            WHERE id = :id
            SQL);
        $statement->execute([
            'maximum_attempts' => $maximumAttempts,
            'locked_until' => $this->date($lockedUntil),
            'id' => $userId,
        ]);
    }

    public function clearFailedLogins(int $userId): void
    {
        $statement = $this->connection()->prepare(
            'UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE id = :id',
        );
        $statement->execute(['id' => $userId]);
    }

    public function recordSuccessfulLogin(int $userId, DateTimeImmutable $now): void
    {
        $statement = $this->connection()->prepare(
            'UPDATE users SET last_login_at = :now WHERE id = :id',
        );
        $statement->execute(['now' => $this->date($now), 'id' => $userId]);
    }

    public function recordSession(
        int $userId,
        string $sessionHash,
        ?string $ipAddress,
        string $userAgent,
        DateTimeImmutable $expiresAt,
        DateTimeImmutable $now,
    ): void {
        $statement = $this->connection()->prepare(<<<'SQL'
            INSERT INTO sessions (user_id, session_hash, ip_address, user_agent, last_activity_at, expires_at, created_at)
            VALUES (:user_id, :session_hash, INET6_ATON(NULLIF(:ip_address, '')), :user_agent, :last_activity_at, :expires_at, :created_at)
            SQL);
        $statement->execute([
            'user_id' => $userId,
            'session_hash' => $sessionHash,
            'ip_address' => $ipAddress ?? '',
            'user_agent' => $this->userAgent($userAgent),
            'last_activity_at' => $this->date($now),
            'expires_at' => $this->date($expiresAt),
            'created_at' => $this->date($now),
        ]);
    }

    public function sessionIsActive(int $userId, string $sessionHash, DateTimeImmutable $now): bool
    {
        $statement = $this->connection()->prepare(<<<'SQL'
            UPDATE sessions
            SET last_activity_at = :last_activity_at
            WHERE user_id = :user_id
              AND session_hash = :session_hash
              AND revoked_at IS NULL
              AND expires_at > :expires_after
            SQL);
        $statement->execute([
            'last_activity_at' => $this->date($now),
            'expires_after' => $this->date($now),
            'user_id' => $userId,
            'session_hash' => $sessionHash,
        ]);

        return $statement->rowCount() === 1;
    }

    public function revokeSession(string $sessionHash, DateTimeImmutable $now): void
    {
        $statement = $this->connection()->prepare(
            'UPDATE sessions SET revoked_at = :now WHERE session_hash = :session_hash AND revoked_at IS NULL',
        );
        $statement->execute(['now' => $this->date($now), 'session_hash' => $sessionHash]);
    }

    public function revokeAllSessions(int $userId, DateTimeImmutable $now): void
    {
        $statement = $this->connection()->prepare(
            'UPDATE sessions SET revoked_at = :now WHERE user_id = :user_id AND revoked_at IS NULL',
        );
        $statement->execute(['now' => $this->date($now), 'user_id' => $userId]);
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
        $statement = $this->connection()->prepare(<<<'SQL'
            INSERT INTO audit_logs (
                actor_user_id, action, auditable_type, auditable_id, description,
                new_values, ip_address, user_agent, request_id, created_at
            ) VALUES (
                :actor_user_id, :action, :auditable_type, :auditable_id, :description,
                :new_values, INET6_ATON(NULLIF(:ip_address, '')), :user_agent, :request_id, :created_at
            )
            SQL);
        $statement->execute([
            'actor_user_id' => $userId,
            'action' => $action,
            'auditable_type' => $userId === null ? null : 'user',
            'auditable_id' => $userId === null ? null : (string) $userId,
            'description' => $description,
            'new_values' => $this->json($context),
            'ip_address' => $ipAddress ?? '',
            'user_agent' => $this->userAgent($userAgent),
            'request_id' => bin2hex(random_bytes(16)),
            'created_at' => $this->date($now),
        ]);
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
        $fingerprint = hash('sha256', implode('|', [$eventType, (string) $userId, (string) $ipAddress]));
        $statement = $this->connection()->prepare(<<<'SQL'
            INSERT INTO security_events (
                user_id, event_type, severity, description, ip_address,
                user_agent, context, fingerprint, occurred_at
            ) VALUES (
                :user_id, :event_type, :severity, :description, INET6_ATON(NULLIF(:ip_address, '')),
                :user_agent, :context, :fingerprint, :occurred_at
            )
            SQL);
        $statement->execute([
            'user_id' => $userId,
            'event_type' => $eventType,
            'severity' => $severity,
            'description' => $description,
            'ip_address' => $ipAddress ?? '',
            'user_agent' => $this->userAgent($userAgent),
            'context' => $this->json($context),
            'fingerprint' => $fingerprint,
            'occurred_at' => $this->date($now),
        ]);
    }

    private function lockUsableToken(PDO $database, string $table, string $tokenHash, DateTimeImmutable $now): ?int
    {
        $statement = $database->prepare(
            "SELECT user_id FROM {$table}
             WHERE token_hash = :token_hash AND used_at IS NULL AND expires_at > :now
             LIMIT 1 FOR UPDATE",
        );
        $statement->execute(['token_hash' => $tokenHash, 'now' => $this->date($now)]);
        $userId = $statement->fetchColumn();

        return $userId === false ? null : (int) $userId;
    }

    private function markTokenUsed(PDO $database, string $table, string $tokenHash, DateTimeImmutable $now): void
    {
        $statement = $database->prepare(
            "UPDATE {$table} SET used_at = :now WHERE token_hash = :token_hash AND used_at IS NULL",
        );
        $statement->execute(['now' => $this->date($now), 'token_hash' => $tokenHash]);
    }

    private function tokenTable(string $purpose): string
    {
        return match ($purpose) {
            'email_verification' => 'email_verification_tokens',
            'password_reset' => 'password_reset_tokens',
            default => throw new \InvalidArgumentException('Unsupported authentication token purpose.'),
        };
    }

    private function incrementRequestRateLimit(
        PDO $database,
        string $action,
        string $scopeType,
        string $scopeHash,
        DateTimeImmutable $windowStart,
        DateTimeImmutable $now,
    ): int {
        $statement = $database->prepare(<<<'SQL'
            INSERT INTO auth_request_rate_limits (
                action, scope_type, scope_hash, window_started_at, attempts, created_at, updated_at
            ) VALUES (
                :action, :scope_type, :scope_hash, :window_started_at, 1, :created_at, :updated_at
            )
            ON DUPLICATE KEY UPDATE attempts = attempts + 1, updated_at = VALUES(updated_at)
            SQL);
        $statement->execute([
            'action' => $action,
            'scope_type' => $scopeType,
            'scope_hash' => $scopeHash,
            'window_started_at' => $this->date($windowStart),
            'created_at' => $this->date($now),
            'updated_at' => $this->date($now),
        ]);

        $count = $database->prepare(<<<'SQL'
            SELECT attempts FROM auth_request_rate_limits
            WHERE action = :action AND scope_type = :scope_type
              AND scope_hash = :scope_hash AND window_started_at = :window_started_at
            LIMIT 1
            SQL);
        $count->execute([
            'action' => $action,
            'scope_type' => $scopeType,
            'scope_hash' => $scopeHash,
            'window_started_at' => $this->date($windowStart),
        ]);

        return (int) $count->fetchColumn();
    }

    /** @template T @param callable(PDO): T $callback @return T */
    private function transaction(callable $callback): mixed
    {
        $database = $this->connection();
        $database->beginTransaction();

        try {
            $result = $callback($database);
            $database->commit();

            return $result;
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
    }

    private function date(DateTimeImmutable $date): string
    {
        return $date->format('Y-m-d H:i:s.u');
    }

    private function userAgent(string $userAgent): string
    {
        return mb_substr($userAgent, 0, 1024);
    }

    /** @param array<string, mixed> $value
     *  @throws JsonException
     */
    private function json(array $value): ?string
    {
        return $value === [] ? null : json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }
}
