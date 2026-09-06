<?php

declare(strict_types=1);

namespace App\Auth;

use App\Security\Csrf;
use App\Security\Security;
use App\Security\SessionManager;
use Closure;
use DateTimeImmutable;

final class AuthService
{
    private const GENERIC_LOGIN_MESSAGE = 'The email address or password is incorrect.';
    private const GENERIC_RECOVERY_MESSAGE = 'If an eligible account matches that address, recovery instructions will be sent.';
    private const GENERIC_REGISTRATION_MESSAGE = 'If the address can be registered, verification instructions will be sent.';

    private readonly Closure $clock;

    /** @param array<string, mixed> $config */
    public function __construct(
        private readonly AuthRepositoryInterface $repository,
        private readonly SessionManager $session,
        private readonly Csrf $csrf,
        private readonly PasswordPolicy $passwordPolicy,
        private readonly TokenDeliveryInterface $delivery,
        private readonly array $config,
        private readonly int $sessionLifetimeMinutes,
        ?Closure $clock = null,
    ) {
        $this->clock = $clock ?? static fn (): DateTimeImmutable => new DateTimeImmutable('now');
    }

    public function register(string $email, string $password, ?string $ipAddress, string $userAgent): AuthResult
    {
        $email = $this->normalizeEmail($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return AuthResult::failure('validation_failed', 'Enter a valid email address.');
        }

        $passwordErrors = $this->passwordPolicy->errors($password);
        if ($passwordErrors !== []) {
            return AuthResult::failure('validation_failed', implode(' ', $passwordErrors));
        }

        $now = $this->now();
        $ipAddress = $this->validIp($ipAddress);
        if (!$this->requestAllowed('registration', $email, $ipAddress, $now)) {
            $this->repository->securityEvent(null, 'auth.registration_throttled', 'medium', 'A registration request was rejected by rate limiting.', $ipAddress, $userAgent, ['identity_digest' => hash('sha256', $email)], $now);
            return AuthResult::success('registration_received', self::GENERIC_REGISTRATION_MESSAGE);
        }

        $passwordHash = Security::hashPassword($password);
        if ($this->repository->findByEmail($email) !== null) {
            return AuthResult::success('registration_received', self::GENERIC_REGISTRATION_MESSAGE);
        }

        $user = $this->repository->createUser(
            Security::uuidV4(),
            $email,
            $passwordHash,
            $now,
        );
        if ($user === null) {
            return AuthResult::success('registration_received', self::GENERIC_REGISTRATION_MESSAGE);
        }

        $userId = (int) $user['id'];
        $token = Security::randomToken();
        $expiresAt = $now->modify('+' . max(1, (int) ($this->config['verification_ttl_minutes'] ?? 1440)) . ' minutes');
        $this->repository->revokeUnusedTokens($userId, 'email_verification', $now);
        $this->repository->storeToken($userId, 'email_verification', Security::tokenDigest($token), $expiresAt, $now);
        $this->delivery->sendEmailVerification($email, $token, $expiresAt);
        $this->repository->audit(
            $userId,
            'auth.registration_created',
            'A user registration was created pending email verification.',
            $ipAddress,
            $userAgent,
            [],
            $now,
        );

        return AuthResult::success('registration_received', self::GENERIC_REGISTRATION_MESSAGE);
    }

    public function login(string $email, string $password, ?string $ipAddress, string $userAgent): AuthResult
    {
        $now = $this->now();
        $email = $this->normalizeEmail($email);
        $identityHash = hash('sha256', $email);
        $ipAddress = $this->validIp($ipAddress);
        $loginConfig = (array) ($this->config['login'] ?? []);
        $identityMaximum = max(1, (int) ($loginConfig['identity_attempts'] ?? 5));
        $ipMaximum = max($identityMaximum, (int) ($loginConfig['ip_attempts'] ?? 50));
        $decayMinutes = max(1, (int) ($loginConfig['decay_minutes'] ?? 15));
        $lockMinutes = max(1, (int) ($loginConfig['lock_minutes'] ?? 15));
        $counts = $this->repository->failedLoginCounts(
            $identityHash,
            $ipAddress,
            $now->modify('-' . $decayMinutes . ' minutes'),
        );

        if ($counts['identity'] >= $identityMaximum || $counts['ip'] >= $ipMaximum) {
            $this->repository->securityEvent(
                null,
                'auth.login_throttled',
                'medium',
                'A login request was rejected by rate limiting.',
                $ipAddress,
                $userAgent,
                ['identity_digest' => $identityHash],
                $now,
            );

            return AuthResult::failure('throttled', 'Too many login attempts. Please try again later.', $decayMinutes * 60);
        }

        $user = filter_var($email, FILTER_VALIDATE_EMAIL) ? $this->repository->findByEmail($email) : null;
        $userId = $user === null ? null : (int) $user['id'];
        $hash = is_string($user['password_hash'] ?? null) ? $user['password_hash'] : $this->dummyHash();
        $passwordWithinLimit = mb_strlen($password) <= $this->passwordPolicy->maximumLength();
        $passwordValid = $passwordWithinLimit && Security::verifyPassword($password, $hash);

        $lockedUntil = $this->date($user['locked_until'] ?? null);
        if ($userId !== null && $counts['identity'] === 0 && (int) ($user['failed_login_attempts'] ?? 0) > 0) {
            $this->repository->clearFailedLogins($userId);
            $user['failed_login_attempts'] = 0;
            $lockedUntil = null;
        }
        if ($lockedUntil !== null && $lockedUntil > $now) {
            $this->repository->recordLoginAttempt($userId, $identityHash, $ipAddress, $userAgent, false, $now);

            return AuthResult::failure(
                'throttled',
                'Too many login attempts. Please try again later.',
                max(1, $lockedUntil->getTimestamp() - $now->getTimestamp()),
            );
        }

        if ($user === null || !$passwordValid) {
            $this->repository->recordLoginAttempt($userId, $identityHash, $ipAddress, $userAgent, false, $now);
            if ($userId !== null) {
                $this->repository->incrementFailedLogins($userId, $identityMaximum, $now->modify('+' . $lockMinutes . ' minutes'));
                if ((int) ($user['failed_login_attempts'] ?? 0) + 1 >= $identityMaximum) {
                    $this->repository->securityEvent(
                        $userId,
                        'auth.account_locked',
                        'medium',
                        'An account was temporarily locked after repeated login failures.',
                        $ipAddress,
                        $userAgent,
                        [],
                        $now,
                    );
                }
            }
            $this->repository->securityEvent(
                $userId,
                'auth.login_failed',
                'low',
                'A login attempt failed.',
                $ipAddress,
                $userAgent,
                ['identity_digest' => $identityHash],
                $now,
            );

            return AuthResult::failure('invalid_credentials', self::GENERIC_LOGIN_MESSAGE);
        }

        if (($user['status'] ?? '') !== 'active' || empty($user['email_verified_at'])) {
            $this->repository->recordLoginAttempt($userId, $identityHash, $ipAddress, $userAgent, false, $now);

            return ($user['status'] ?? '') === 'pending'
                ? AuthResult::failure('verification_required', 'Verify your email address before signing in.')
                : AuthResult::failure('account_unavailable', self::GENERIC_LOGIN_MESSAGE);
        }

        $this->repository->clearFailedLogins($userId);
        $this->repository->recordSuccessfulLogin($userId, $now);
        if (Security::passwordNeedsRehash($hash)) {
            $this->repository->updatePasswordHash($userId, Security::hashPassword($password), $now);
        }

        $previousSessionId = $this->session->id();
        if ($this->authenticatedUserId() !== null && $previousSessionId !== '') {
            $this->repository->revokeSession(Security::tokenDigest($previousSessionId), $now);
        }
        $this->session->regenerate();
        $this->csrf->rotate();
        $this->session->put('auth.user_id', $userId);
        $this->session->put('auth.public_id', (string) $user['public_id']);
        $this->session->put('auth.authenticated_at', $now->format(DATE_ATOM));
        $sessionHash = Security::tokenDigest($this->session->id());
        $this->repository->recordSession(
            $userId,
            $sessionHash,
            $ipAddress,
            $userAgent,
            $now->modify('+' . max(1, $this->sessionLifetimeMinutes) . ' minutes'),
            $now,
        );
        $this->repository->recordLoginAttempt($userId, $identityHash, $ipAddress, $userAgent, true, $now);
        $this->repository->audit(
            $userId,
            'auth.login_succeeded',
            'A user signed in successfully.',
            $ipAddress,
            $userAgent,
            [],
            $now,
        );

        return AuthResult::success('authenticated', 'You are signed in.');
    }

    public function logout(?string $ipAddress, string $userAgent): void
    {
        $now = $this->now();
        $userId = $this->authenticatedUserId();
        $sessionId = $this->session->id();
        try {
            if ($sessionId !== '') {
                $this->repository->revokeSession(Security::tokenDigest($sessionId), $now);
            }
            if ($userId !== null) {
                $this->repository->audit(
                    $userId,
                    'auth.logout',
                    'A user signed out.',
                    $this->validIp($ipAddress),
                    $userAgent,
                    [],
                    $now,
                );
            }
        } finally {
            $this->session->invalidate();
        }
    }

    public function requestPasswordReset(string $email, ?string $ipAddress, string $userAgent): AuthResult
    {
        $now = $this->now();
        $email = $this->normalizeEmail($email);
        $ipAddress = $this->validIp($ipAddress);
        if (!$this->requestAllowed('password_reset', $email, $ipAddress, $now)) {
            $this->repository->securityEvent(null, 'auth.password_reset_throttled', 'medium', 'A password recovery request was rejected by rate limiting.', $ipAddress, $userAgent, ['identity_digest' => hash('sha256', $email)], $now);
            return AuthResult::success('recovery_requested', self::GENERIC_RECOVERY_MESSAGE);
        }
        $user = filter_var($email, FILTER_VALIDATE_EMAIL) ? $this->repository->findByEmail($email) : null;

        if ($user !== null && is_string($user['password_hash'] ?? null)) {
            $userId = (int) $user['id'];
            $token = Security::randomToken();
            $expiresAt = $now->modify('+' . max(1, (int) ($this->config['reset_ttl_minutes'] ?? 60)) . ' minutes');
            $this->repository->revokeUnusedTokens($userId, 'password_reset', $now);
            $this->repository->storeToken($userId, 'password_reset', Security::tokenDigest($token), $expiresAt, $now);
            $this->delivery->sendPasswordReset($email, $token, $expiresAt);
            $this->repository->audit(
                $userId,
                'auth.password_reset_requested',
                'Password recovery instructions were requested.',
                $ipAddress,
                $userAgent,
                [],
                $now,
            );
        }

        return AuthResult::success('recovery_requested', self::GENERIC_RECOVERY_MESSAGE);
    }

    public function resetPassword(string $token, string $password, ?string $ipAddress, string $userAgent): AuthResult
    {
        $passwordErrors = $this->passwordPolicy->errors($password);
        if ($passwordErrors !== []) {
            return AuthResult::failure('validation_failed', implode(' ', $passwordErrors));
        }
        if (!$this->validToken($token)) {
            return AuthResult::failure('invalid_token', 'This password reset link is invalid or has expired.');
        }

        $now = $this->now();
        $userId = $this->repository->consumePasswordResetToken(
            Security::tokenDigest($token),
            Security::hashPassword($password),
            $now,
        );
        if ($userId === null) {
            return AuthResult::failure('invalid_token', 'This password reset link is invalid or has expired.');
        }

        $this->repository->audit(
            $userId,
            'auth.password_reset_completed',
            'A password was reset and existing sessions were revoked.',
            $this->validIp($ipAddress),
            $userAgent,
            [],
            $now,
        );
        $this->repository->securityEvent(
            $userId,
            'auth.password_changed',
            'medium',
            'A password was changed through the recovery flow.',
            $this->validIp($ipAddress),
            $userAgent,
            [],
            $now,
        );

        return AuthResult::success('password_reset', 'Your password has been reset. You can now sign in.');
    }

    public function verifyEmail(string $token, ?string $ipAddress, string $userAgent): AuthResult
    {
        if (!$this->validToken($token)) {
            return AuthResult::failure('invalid_token', 'This verification link is invalid or has expired.');
        }

        $now = $this->now();
        $userId = $this->repository->consumeEmailVerificationToken(Security::tokenDigest($token), $now);
        if ($userId === null) {
            return AuthResult::failure('invalid_token', 'This verification link is invalid or has expired.');
        }

        $this->repository->audit(
            $userId,
            'auth.email_verified',
            'A user verified their email address.',
            $this->validIp($ipAddress),
            $userAgent,
            [],
            $now,
        );

        return AuthResult::success('email_verified', 'Your email address has been verified. You can now sign in.');
    }

    /** @return array<string, mixed>|null */
    public function currentUser(): ?array
    {
        $userId = $this->authenticatedUserId();
        $sessionId = $this->session->id();
        if ($userId === null || $sessionId === '') {
            return null;
        }

        $now = $this->now();
        if (!$this->repository->sessionIsActive($userId, Security::tokenDigest($sessionId), $now)) {
            $this->forgetAuthentication();
            return null;
        }

        $user = $this->repository->findActiveById($userId);
        if ($user === null) {
            $this->repository->revokeSession(Security::tokenDigest($sessionId), $now);
            $this->forgetAuthentication();
        }

        return $user;
    }

    public function passwordMinimumLength(): int
    {
        return $this->passwordPolicy->minimumLength();
    }

    public function passwordMaximumLength(): int
    {
        return $this->passwordPolicy->maximumLength();
    }

    private function authenticatedUserId(): ?int
    {
        $userId = $this->session->get('auth.user_id');

        return is_int($userId) && $userId > 0 ? $userId : null;
    }

    private function forgetAuthentication(): void
    {
        $this->session->remove('auth.user_id');
        $this->session->remove('auth.public_id');
        $this->session->remove('auth.authenticated_at');
        $this->csrf->rotate();
    }

    private function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    private function validIp(?string $ipAddress): ?string
    {
        return is_string($ipAddress) && filter_var($ipAddress, FILTER_VALIDATE_IP) ? $ipAddress : null;
    }

    private function validToken(string $token): bool
    {
        return preg_match('/^[a-f0-9]{64}$/D', $token) === 1;
    }

    private function requestAllowed(string $action, string $identity, ?string $ipAddress, DateTimeImmutable $now): bool
    {
        $rateConfig = (array) ($this->config[$action] ?? []);
        $identityMaximum = max(1, (int) ($rateConfig['identity_attempts'] ?? 3));
        $ipMaximum = max($identityMaximum, (int) ($rateConfig['ip_attempts'] ?? 20));
        $decayMinutes = max(1, (int) ($rateConfig['decay_minutes'] ?? 60));

        return $this->repository->consumeRequestRateLimit(
            $action,
            hash('sha256', $identity),
            $ipAddress,
            $identityMaximum,
            $ipMaximum,
            $decayMinutes * 60,
            $now,
        );
    }

    private function now(): DateTimeImmutable
    {
        return ($this->clock)();
    }

    private function date(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function dummyHash(): string
    {
        static $hash;
        $hash ??= Security::hashPassword('not-a-real-account-password');

        return $hash;
    }
}
