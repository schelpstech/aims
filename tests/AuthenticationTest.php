<?php

declare(strict_types=1);

use App\Auth\AuthService;
use App\Auth\PasswordPolicy;
use App\Http\Request;
use App\Http\Response;
use App\Middleware\AuthenticateMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\SessionMiddleware;
use App\Routing\Router;
use App\Security\Csrf;
use App\Security\Security;
use App\Security\SessionManager;
use Tests\Support\FakeTokenDelivery;
use Tests\Support\InMemoryAuthRepository;

require dirname(__DIR__) . '/vendor/autoload.php';
require __DIR__ . '/Support/InMemoryAuthRepository.php';
require __DIR__ . '/Support/FakeTokenDelivery.php';

$temporaryDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'aims-auth-test-' . bin2hex(random_bytes(6));
$session = null;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

try {
    if (!mkdir($temporaryDirectory, 0700, true) && !is_dir($temporaryDirectory)) {
        throw new RuntimeException('Unable to create the authentication test directory.');
    }

    $now = new DateTimeImmutable('2026-09-04 12:00:00');
    $repository = new InMemoryAuthRepository([
        [
            'id' => 1,
            'public_id' => 'e85c6502-468f-4d6e-9e8b-9e49225efbbb',
            'email' => 'member@example.test',
            'password_hash' => Security::hashPassword('Correct Horse Battery 123!'),
            'status' => 'active',
            'email_verified_at' => '2026-09-01 10:00:00.000000',
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'deleted_at' => null,
        ],
        [
            'id' => 2,
            'public_id' => '253e067f-ac20-4246-bcef-5461da024eb4',
            'email' => 'lockable@example.test',
            'password_hash' => Security::hashPassword('Lockable account password 81!'),
            'status' => 'active',
            'email_verified_at' => '2026-09-01 10:00:00.000000',
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'deleted_at' => null,
        ],
    ]);
    $delivery = new FakeTokenDelivery();
    $session = new SessionManager([
        'name' => 'aims_auth_test_' . bin2hex(random_bytes(4)),
        'lifetime' => 120,
        'save_path' => $temporaryDirectory . DIRECTORY_SEPARATOR . 'sessions',
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'http_only' => true,
        'same_site' => 'Lax',
    ]);
    $csrf = new Csrf($session);
    $auth = new AuthService(
        repository: $repository,
        session: $session,
        csrf: $csrf,
        passwordPolicy: new PasswordPolicy(12, 4096),
        delivery: $delivery,
        config: [
            'verification_ttl_minutes' => 1440,
            'reset_ttl_minutes' => 60,
            'login' => [
                'identity_attempts' => 3,
                'ip_attempts' => 100,
                'decay_minutes' => 15,
                'lock_minutes' => 15,
            ],
            'registration' => ['identity_attempts' => 2, 'ip_attempts' => 100, 'decay_minutes' => 60],
            'password_reset' => ['identity_attempts' => 2, 'ip_attempts' => 100, 'decay_minutes' => 60],
            'password_change' => ['identity_attempts' => 5, 'ip_attempts' => 20, 'decay_minutes' => 15],
        ],
        sessionLifetimeMinutes: 120,
        clock: static function () use (&$now): DateTimeImmutable {
            return $now;
        },
    );

    $session->start();
    $cookieParameters = session_get_cookie_params();
    $assert($cookieParameters['httponly'] === true, 'The session cookie is not HttpOnly.');
    $assert(($cookieParameters['samesite'] ?? '') === 'Lax', 'The session cookie SameSite policy was not applied.');
    $oldSessionId = $session->id();
    $oldCsrfToken = $csrf->token();
    $login = $auth->login('Member@Example.Test', 'Correct Horse Battery 123!', '127.0.0.1', 'AIMS Test');
    $assert($login->successful, 'Valid credentials were rejected.');
    $assert($login->code === 'authenticated', 'Valid login returned the wrong result code.');
    $assert($session->id() !== $oldSessionId, 'The session identifier was not regenerated after login.');
    $assert($csrf->token() !== $oldCsrfToken, 'The CSRF token was not rotated after login.');
    $assert($session->get('auth.user_id') === 1, 'Authenticated user state was not stored server-side.');
    $assert(count($repository->sessions) === 1, 'The authenticated session was not registered.');
    $assert($auth->currentUser()['email'] === 'member@example.test', 'The registered session was not accepted by authentication.');

    $activeSessionHash = Security::tokenDigest($session->id());
    $auth->logout('127.0.0.1', 'AIMS Test');
    $assert($repository->sessions[$activeSessionHash]['revoked_at'] instanceof DateTimeImmutable, 'Logout did not revoke the server-side session.');
    $assert(session_status() !== PHP_SESSION_ACTIVE, 'Logout did not destroy the PHP session.');

    $unknownFailure = $auth->login('unknown@example.test', 'Definitely wrong 123!', '127.0.0.2', 'AIMS Test');
    $knownFailure = $auth->login('member@example.test', 'Definitely wrong 123!', '127.0.0.3', 'AIMS Test');
    $assert(!$unknownFailure->successful && !$knownFailure->successful, 'Invalid credentials were accepted.');
    $assert($unknownFailure->message === $knownFailure->message, 'Login failure messages disclose account existence.');
    $assert($unknownFailure->code === 'invalid_credentials', 'Invalid login returned the wrong result code.');

    for ($attempt = 0; $attempt < 3; $attempt++) {
        $auth->login('limited@example.test', 'Wrong password 123!', '127.0.0.4', 'AIMS Test');
    }
    $limited = $auth->login('limited@example.test', 'Wrong password 123!', '127.0.0.4', 'AIMS Test');
    $assert($limited->code === 'throttled' && $limited->retryAfter === 900, 'Login rate limiting did not activate.');

    for ($attempt = 0; $attempt < 3; $attempt++) {
        $auth->login('lockable@example.test', 'Wrong password 123!', '127.0.0.8', 'AIMS Test');
    }
    $assert(is_string($repository->users[2]['locked_until']), 'Repeated failures did not lock the account.');
    $lockedLogin = $auth->login('lockable@example.test', 'Lockable account password 81!', '127.0.0.9', 'AIMS Test');
    $assert($lockedLogin->code === 'throttled', 'A temporarily locked account accepted a valid password.');
    $assert(
        count(array_filter($repository->securityEvents, static fn (array $event): bool => $event['eventType'] === 'auth.account_locked')) === 1,
        'The account lock security event was not recorded.',
    );

    $registrationPassword = 'A long registration passphrase 42!';
    $registration = $auth->register('new.member@example.test', $registrationPassword, '127.0.0.5', 'AIMS Test');
    $assert($registration->successful, 'Registration foundation rejected valid input.');
    $registered = $repository->findByEmail('new.member@example.test');
    $assert(is_array($registered), 'Registration did not create a pending user.');
    $assert($registered['password_hash'] !== $registrationPassword, 'Registration stored a plaintext password.');
    $assert(password_verify($registrationPassword, $registered['password_hash']), 'Registration password hash cannot be verified.');
    $verificationToken = $delivery->verifications[0]['token'];
    $verificationDigest = Security::tokenDigest($verificationToken);
    $assert(isset($repository->tokens['email_verification'][$verificationDigest]), 'The verification token digest was not stored.');
    $assert(!isset($repository->tokens['email_verification'][$verificationToken]), 'The plaintext verification token was stored.');
    $verified = $auth->verifyEmail($verificationToken, '127.0.0.5', 'AIMS Test');
    $assert($verified->successful, 'A valid email verification token was rejected.');
    $assert(!$auth->verifyEmail($verificationToken, '127.0.0.5', 'AIMS Test')->successful, 'A used verification token was accepted.');
    $auth->register('new.member@example.test', $registrationPassword, '127.0.0.5', 'AIMS Test');
    $auth->register('new.member@example.test', $registrationPassword, '127.0.0.5', 'AIMS Test');
    $assert(
        count(array_filter($repository->securityEvents, static fn (array $event): bool => $event['eventType'] === 'auth.registration_throttled')) === 1,
        'Registration request rate limiting did not activate.',
    );

    $knownRecovery = $auth->requestPasswordReset('member@example.test', '127.0.0.6', 'AIMS Test');
    $unknownRecovery = $auth->requestPasswordReset('missing@example.test', '127.0.0.6', 'AIMS Test');
    $assert($knownRecovery->message === $unknownRecovery->message, 'Password recovery discloses account existence.');
    $expiredToken = $delivery->resets[array_key_last($delivery->resets)]['token'];
    $expiredDigest = Security::tokenDigest($expiredToken);
    $assert(isset($repository->tokens['password_reset'][$expiredDigest]), 'The password reset token digest was not stored.');
    $assert(!isset($repository->tokens['password_reset'][$expiredToken]), 'The plaintext password reset token was stored.');
    $now = $now->modify('+61 minutes');
    $expiredReset = $auth->resetPassword($expiredToken, 'A replacement passphrase 99!', '127.0.0.6', 'AIMS Test');
    $assert($expiredReset->code === 'invalid_token', 'An expired password reset token was accepted.');

    $auth->requestPasswordReset('member@example.test', '127.0.0.6', 'AIMS Test');
    $usableToken = $delivery->resets[array_key_last($delivery->resets)]['token'];
    $newPassword = 'Another replacement passphrase 27!';
    $reset = $auth->resetPassword($usableToken, $newPassword, '127.0.0.6', 'AIMS Test');
    $assert($reset->successful, 'A valid password reset token was rejected.');
    $usedReset = $auth->resetPassword($usableToken, 'Yet another passphrase 38!', '127.0.0.6', 'AIMS Test');
    $assert($usedReset->code === 'invalid_token', 'A used password reset token was accepted.');
    $assert(password_verify($newPassword, $repository->users[1]['password_hash']), 'The reset password was not securely persisted.');
    $resetDeliveries = count($delivery->resets);
    $auth->requestPasswordReset('member@example.test', '127.0.0.6', 'AIMS Test');
    $auth->requestPasswordReset('member@example.test', '127.0.0.6', 'AIMS Test');
    $assert(count($delivery->resets) === $resetDeliveries + 1, 'A throttled password reset generated an email.');
    $assert(
        count(array_filter($repository->securityEvents, static fn (array $event): bool => $event['eventType'] === 'auth.password_reset_throttled')) === 1,
        'Password recovery request rate limiting did not activate.',
    );

    $router = new Router();
    $router->middleware(new SessionMiddleware($session));
    $router->middleware(new CsrfMiddleware($csrf));
    $router->post('/csrf-check', static fn (Request $request): Response => Response::json(['accepted' => true]));
    $csrfRejected = $router->dispatch(new Request('POST', '/csrf-check', headers: ['accept' => 'application/json']));
    $assert($csrfRejected->status() === 419, 'A POST request without CSRF protection was accepted.');
    $csrfAccepted = $router->dispatch(new Request(
        'POST',
        '/csrf-check',
        body: ['_csrf_token' => $csrf->token()],
        headers: ['accept' => 'application/json'],
    ));
    $assert($csrfAccepted->status() === 200, 'A valid CSRF token was rejected.');

    $protected = new Router();
    $protected->middleware(new SessionMiddleware($session));
    $protected->get('/account', static fn (Request $request): Response => Response::json([
        'email' => $request->attribute('auth.user')['email'],
    ]), [new AuthenticateMiddleware($auth)]);
    $unauthorized = $protected->dispatch(new Request('GET', '/account'));
    $assert($unauthorized->status() === 302 && $unauthorized->headers()['Location'] === '/login', 'Authentication middleware did not reject a guest.');

    $secondLogin = $auth->login('member@example.test', $newPassword, '127.0.0.7', 'AIMS Test');
    $assert($secondLogin->successful, 'The reset password could not authenticate.');
    $authorized = $protected->dispatch(new Request('GET', '/account', headers: ['accept' => 'application/json']));
    $assert($authorized->status() === 200, 'Authentication middleware rejected an active server-side session.');
    $assert(json_decode($authorized->body(), true)['email'] === 'member@example.test', 'Authentication middleware did not attach the server-derived user.');

    $passwordBeforeRejectedChange = $repository->users[1]['password_hash'];
    $rejectedChange = $auth->changePassword('Incorrect current password', 'A secure changed passphrase 64!', '127.0.0.7', 'AIMS Test');
    $assert(!$rejectedChange->successful && $rejectedChange->code === 'invalid_current_password', 'An invalid current password was accepted.');
    $assert($repository->users[1]['password_hash'] === $passwordBeforeRejectedChange, 'A rejected password change modified the password hash.');

    $sessionBeforePasswordChange = $session->id();
    $csrfBeforePasswordChange = $csrf->token();
    $changedPassword = 'A secure changed passphrase 64!';
    $passwordChange = $auth->changePassword($newPassword, $changedPassword, '127.0.0.7', 'AIMS Test');
    $assert($passwordChange->successful && $passwordChange->code === 'password_changed', 'A valid authenticated password change failed.');
    $assert(password_verify($changedPassword, $repository->users[1]['password_hash']), 'The authenticated password change did not persist a secure hash.');
    $assert($session->id() !== $sessionBeforePasswordChange, 'The session identifier was not regenerated after changing the password.');
    $assert($csrf->token() !== $csrfBeforePasswordChange, 'The CSRF token was not rotated after changing the password.');
    $assert($auth->currentUser() !== null, 'The current session was not re-established after changing the password.');
    $assert(
        count(array_filter($repository->sessions, static fn (array $record): bool => $record['user_id'] === 1 && $record['revoked_at'] === null)) === 1,
        'Changing the password did not revoke other sessions while retaining one current session.',
    );
    $assert(
        count(array_filter($repository->audits, static fn (array $audit): bool => $audit['action'] === 'auth.password_changed')) === 1,
        'The authenticated password change was not audited.',
    );

    $auth->logout('127.0.0.7', 'AIMS Test');
    $assert(!$auth->login('member@example.test', $newPassword, '127.0.0.7', 'AIMS Test')->successful, 'The previous password remained usable after a password change.');
    $assert($auth->login('member@example.test', $changedPassword, '127.0.0.7', 'AIMS Test')->successful, 'The changed password could not authenticate.');
    $auth->logout('127.0.0.7', 'AIMS Test');
    $session = null;

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($temporaryDirectory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($temporaryDirectory);

    echo "Authentication checks passed: valid login, invalid login, throttling, logout, session regeneration, token expiry/reuse, CSRF, and middleware.\n";
} catch (Throwable $exception) {
    if ($session instanceof SessionManager && session_status() === PHP_SESSION_ACTIVE) {
        $session->invalidate();
    }

    fwrite(STDERR, 'Authentication check failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
