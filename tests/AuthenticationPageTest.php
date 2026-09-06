<?php

declare(strict_types=1);

use App\Auth\AuthService;
use App\Auth\PasswordPolicy;
use App\Config\Config;
use App\Config\Environment;
use App\Controllers\Auth\AuthController;
use App\Controllers\Public\PublicController;
use App\Http\Request;
use App\Middleware\AuthenticateMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\SecurityHeadersMiddleware;
use App\Middleware\SessionMiddleware;
use App\Routing\Router;
use App\Security\Csrf;
use App\Security\SessionManager;
use App\Services\PublicSite\PublicContent;
use App\View\View;
use Tests\Support\FakeTokenDelivery;
use Tests\Support\InMemoryAuthRepository;

require dirname(__DIR__) . '/vendor/autoload.php';
require __DIR__ . '/Support/InMemoryAuthRepository.php';
require __DIR__ . '/Support/FakeTokenDelivery.php';

$temporaryDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'aims-auth-page-test-' . bin2hex(random_bytes(6));
$session = null;
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

try {
    mkdir($temporaryDirectory, 0700, true);
    $environment = Environment::load($temporaryDirectory . DIRECTORY_SEPARATOR . '.env.missing');
    $config = Config::load(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config', $environment);
    $session = new SessionManager([
        'name' => 'aims_auth_page_test_' . bin2hex(random_bytes(4)),
        'lifetime' => 120,
        'save_path' => $temporaryDirectory . DIRECTORY_SEPARATOR . 'sessions',
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'http_only' => true,
        'same_site' => 'Lax',
    ]);
    $csrf = new Csrf($session);
    $repository = new InMemoryAuthRepository();
    $auth = new AuthService(
        $repository,
        $session,
        $csrf,
        new PasswordPolicy(12, 4096),
        new FakeTokenDelivery(),
        (array) $config->get('auth', []),
        120,
    );
    $view = new View(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views');
    $content = new PublicContent($config);
    $publicController = new PublicController($view, $csrf, $content);
    $authController = new AuthController($view, $csrf, $auth, $session, $content);
    $router = new Router();
    $router->middleware(new SecurityHeadersMiddleware((array) $config->get('security.headers', [])));
    $router->middleware(new SessionMiddleware($session, ['/health']));
    $router->middleware(new CsrfMiddleware($csrf));
    $registerRoutes = require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'web.php';
    $registerRoutes($router, $publicController, $authController, new AuthenticateMiddleware($auth));

    $token = str_repeat('a', 64);
    $pages = [
        ['/login', []],
        ['/register', []],
        ['/forgot-password', []],
        ['/reset-password?token=' . $token, ['token' => $token]],
        ['/verify-email?token=' . $token, ['token' => $token]],
    ];

    foreach ($pages as [$uri, $query]) {
        $response = $router->dispatch(new Request('GET', $uri, query: $query));
        $body = $response->body();
        $assert($response->status() === 200, sprintf('%s did not render.', $uri));
        $assert(substr_count($body, '<h1') === 1, sprintf('%s does not have exactly one H1.', $uri));
        $assert(str_contains($body, '<form'), sprintf('%s does not contain its form.', $uri));
        $assert(str_contains($body, 'name="_csrf_token"'), sprintf('%s is missing its CSRF field.', $uri));
        $assert(str_contains($body, '<meta name="robots" content="noindex, nofollow">'), sprintf('%s is indexable.', $uri));
        $assert(($response->headers()['Cache-Control'] ?? '') === 'no-store, private', sprintf('%s may be cached.', $uri));
        $assert(($response->headers()['Referrer-Policy'] ?? '') === 'no-referrer', sprintf('%s may leak token-bearing referrers.', $uri));
    }

    $registrationPage = $router->dispatch(new Request('GET', '/register'));
    $assert(
        str_contains($registrationPage->body(), 'action="/account/create"'),
        'The registration form is not using the hosting-compatible submission endpoint.',
    );

    $guestAccount = $router->dispatch(new Request('GET', '/account'));
    $assert($guestAccount->status() === 302, 'The account page did not reject a guest.');
    $assert(($guestAccount->headers()['Location'] ?? '') === '/login', 'The account page guest redirect is incorrect.');

    $csrfFailure = $router->dispatch(new Request('POST', '/login'));
    $assert($csrfFailure->status() === 419, 'The login endpoint accepted a POST without CSRF.');
    $assert(($csrfFailure->headers()['Cache-Control'] ?? '') === 'no-store', 'The CSRF rejection may be cached.');

    $registrationCsrfFailure = $router->dispatch(new Request('POST', '/account/create'));
    $assert($registrationCsrfFailure->status() === 419, 'The alternate registration endpoint accepted a POST without CSRF.');

    $session->invalidate();
    $session = null;

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($temporaryDirectory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($temporaryDirectory);

    echo "Authentication page checks passed for login, registration, recovery, verification, CSRF, and guest protection.\n";
} catch (Throwable $exception) {
    if ($session instanceof SessionManager && session_status() === PHP_SESSION_ACTIVE) {
        $session->invalidate();
    }
    fwrite(STDERR, 'Authentication page check failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
