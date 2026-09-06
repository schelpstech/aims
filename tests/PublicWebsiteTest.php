<?php

declare(strict_types=1);

use App\Config\Config;
use App\Config\Environment;
use App\Controllers\Public\PublicController;
use App\Http\Request;
use App\Middleware\CsrfMiddleware;
use App\Middleware\SecurityHeadersMiddleware;
use App\Middleware\SessionMiddleware;
use App\Routing\Router;
use App\Security\Csrf;
use App\Security\SessionManager;
use App\Services\PublicSite\PublicContent;
use App\View\View;

require dirname(__DIR__) . '/vendor/autoload.php';

$temporaryDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'aims-public-test-' . bin2hex(random_bytes(6));
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
        'name' => 'aims_public_test_' . bin2hex(random_bytes(4)),
        'lifetime' => 10,
        'save_path' => $temporaryDirectory . DIRECTORY_SEPARATOR . 'sessions',
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'http_only' => true,
        'same_site' => 'Lax',
    ]);
    $csrf = new Csrf($session);
    $view = new View(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views');
    $controller = new PublicController($view, $csrf, new PublicContent($config));
    $router = new Router();
    $router->middleware(new SecurityHeadersMiddleware((array) $config->get('security.headers', [])));
    $router->middleware(new SessionMiddleware($session, ['/health']));
    $router->middleware(new CsrfMiddleware($csrf));
    $registerRoutes = require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'web.php';
    $registerRoutes($router, $controller);

    $paths = [
        '/',
        '/about',
        '/vision-mission',
        '/leadership',
        '/membership',
        '/programmes',
        '/professional-areas',
        '/events',
        '/resources',
        '/news',
        '/contact',
        '/verify',
    ];

    foreach ($paths as $path) {
        $response = $router->dispatch(new Request('GET', $path));
        $body = $response->body();
        $assert($response->status() === 200, sprintf('%s did not return 200.', $path));
        $assert(str_contains($body, '<title>'), sprintf('%s has no document title.', $path));
        $assert(str_contains($body, '<meta name="description"'), sprintf('%s has no meta description.', $path));
        $assert(str_contains($body, '<main id="main-content">'), sprintf('%s has no semantic main landmark.', $path));
        $assert(str_contains($body, '<nav class="navbar'), sprintf('%s has no primary navigation.', $path));
        $assert(str_contains($body, '<footer class="site-footer">'), sprintf('%s has no footer.', $path));
        $assert(substr_count($body, '<h1') === 1, sprintf('%s must contain exactly one H1.', $path));
        $assert(($response->headers()['Content-Security-Policy'] ?? '') !== '', sprintf('%s has no CSP.', $path));
    }

    $contact = $router->dispatch(new Request('GET', '/contact'))->body();
    $assert(str_contains($contact, '<fieldset disabled>'), 'Inactive contact form must not collect data.');
    $assert(str_contains($contact, '<label for="contact-email">'), 'Contact form email label is missing.');

    $verification = $router->dispatch(new Request('GET', '/verify'))->body();
    $assert(str_contains($verification, 'Only a one-way digest'), 'Verification privacy disclosure is missing.');
    $assert(str_contains($verification, 'method="post" action="/verify"'), 'Active verification form is missing.');
    $assert(str_contains($verification, 'secure public verification service'), 'Verification page does not describe the active service.');
    $assert(!str_contains($verification, 'will be enabled'), 'Verification page still contains pre-launch placeholder copy.');

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

    echo "Public website checks passed for 12 routes.\n";
} catch (Throwable $exception) {
    if ($session instanceof SessionManager && session_status() === PHP_SESSION_ACTIVE) {
        $session->invalidate();
    }
    fwrite(STDERR, 'Public website check failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
