<?php

declare(strict_types=1);

use App\Config\Config;
use App\Config\Environment;
use App\Controllers\Public\PublicController;
use App\Database\Connection;
use App\Exceptions\Handler;
use App\Http\Request;
use App\Http\Response;
use App\Logging\Logger;
use App\Middleware\CsrfMiddleware;
use App\Middleware\SecurityHeadersMiddleware;
use App\Middleware\SessionMiddleware;
use App\Routing\Router;
use App\Security\Csrf;
use App\Security\Security;
use App\Security\SessionManager;
use App\Services\PublicSite\PublicContent;
use App\Validation\Validator;
use App\View\View;

require dirname(__DIR__) . '/vendor/autoload.php';

$temporaryDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'aims-foundation-' . bin2hex(random_bytes(6));
$session = null;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

try {
    if (!mkdir($temporaryDirectory, 0700, true) && !is_dir($temporaryDirectory)) {
        throw new RuntimeException('Unable to create the test directory.');
    }

    $environment = Environment::load($temporaryDirectory . DIRECTORY_SEPARATOR . '.env.missing');
    $config = Config::load(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config', $environment);
    $assert($config->get('app.environment') === 'production', 'Safe production environment must be the default.');
    $assert($config->get('app.debug') === false, 'Debug mode must be disabled by default.');

    $database = new Connection($config);
    try {
        $database->get();
        throw new RuntimeException('Incomplete database configuration was accepted.');
    } catch (RuntimeException $exception) {
        $assert($exception->getMessage() === 'Database configuration is incomplete.', 'Database configuration failed unexpectedly.');
    }

    $session = new SessionManager([
        'name' => 'aims_test_' . bin2hex(random_bytes(4)),
        'lifetime' => 10,
        'save_path' => $temporaryDirectory . DIRECTORY_SEPARATOR . 'sessions',
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'http_only' => true,
        'same_site' => 'Lax',
    ]);
    $csrf = new Csrf($session);

    $router = new Router();
    $router->middleware(new SecurityHeadersMiddleware(
        ['X-Content-Type-Options' => 'nosniff'],
        ['Strict-Transport-Security' => 'max-age=31536000'],
    ));
    $router->middleware(new SessionMiddleware($session));
    $router->middleware(new CsrfMiddleware($csrf));
    $router->get('/items/{id}', static fn (Request $request): Response => Response::json(['id' => $request->route('id')]));
    $router->post('/submit', static fn (Request $request): Response => Response::json(['accepted' => true]));

    $getResponse = $router->dispatch(new Request('GET', '/items/42', headers: ['accept' => 'application/json']));
    $assert($getResponse->status() === 200, 'GET route failed.');
    $assert(($getResponse->headers()['X-Content-Type-Options'] ?? null) === 'nosniff', 'Security middleware did not run.');
    $assert(json_decode($getResponse->body(), true)['id'] === '42', 'Route parameter was not captured.');
    $assert(!isset($getResponse->headers()['Strict-Transport-Security']), 'HSTS was emitted over an insecure request.');
    $assert(session_status() !== PHP_SESSION_ACTIVE, 'Anonymous read-only requests should not eagerly start a session.');
    $secureResponse = $router->dispatch(new Request('GET', '/items/42', server: ['HTTPS' => 'on']));
    $assert(($secureResponse->headers()['Strict-Transport-Security'] ?? '') === 'max-age=31536000', 'HSTS was not emitted for HTTPS.');

    $invalidCsrf = $router->dispatch(new Request('POST', '/submit', headers: ['accept' => 'application/json']));
    $assert($invalidCsrf->status() === 419, 'Unsafe request without CSRF token was not rejected.');

    $validCsrf = $router->dispatch(new Request(
        'POST',
        '/submit',
        body: ['_csrf_token' => $csrf->token()],
        headers: ['accept' => 'application/json'],
    ));
    $assert($validCsrf->status() === 200, 'Valid CSRF token was rejected.');
    $assert(ini_get('session.use_strict_mode') === '1', 'Strict session mode was not enabled.');
    $assert(ini_get('session.use_only_cookies') === '1', 'Cookie-only sessions were not enabled.');

    $notFound = $router->dispatch(new Request('GET', '/missing'));
    $assert($notFound->status() === 404, 'Missing route did not return 404.');
    $assert(isset($notFound->headers()['X-Content-Type-Options']), 'Global middleware did not cover 404 responses.');

    $validator = new Validator();
    $assert($validator->validate(['email' => 'member@example.test'], ['email' => 'required|email']), 'Valid input failed validation.');
    $assert(!$validator->validate(['email' => 'not-an-email'], ['email' => 'required|email']), 'Invalid email passed validation.');

    $assert(Security::escape('<script>') === '&lt;script&gt;', 'Output escaping failed.');
    $assert(strlen(Security::randomToken()) === 64, 'Secure token length is incorrect.');

    $viewRoot = $temporaryDirectory . DIRECTORY_SEPARATOR . 'views';
    mkdir($viewRoot, 0700, true);
    file_put_contents($viewRoot . DIRECTORY_SEPARATOR . 'hello.php', 'Hello, <?= htmlspecialchars((string) $name, ENT_QUOTES, "UTF-8") ?>');
    $view = new View($viewRoot);
    $assert($view->render('hello', ['name' => 'AIMS']) === 'Hello, AIMS', 'View renderer failed.');

    $logPath = $temporaryDirectory . DIRECTORY_SEPARATOR . 'application.log';
    $logger = new Logger($logPath, 'debug');
    $logger->info('Redaction test.', ['password' => 'do-not-log']);
    $logContents = (string) file_get_contents($logPath);
    $assert(!str_contains($logContents, 'do-not-log'), 'Sensitive logging context was not redacted.');
    $assert(str_contains($logContents, '[REDACTED]'), 'Redaction marker is missing.');

    $handler = new Handler($logger, false);
    $errorResponse = $handler->render(
        new RuntimeException('Sensitive exception details.'),
        new Request('GET', '/failure', headers: ['accept' => 'application/json']),
    );
    $assert($errorResponse->status() === 500, 'Exception handler returned the wrong status.');
    $assert(!str_contains($errorResponse->body(), 'Sensitive exception details.'), 'Production error response exposed exception details.');

    $healthRouter = new Router();
    $registerRoutes = require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'web.php';
    $publicController = new PublicController($view, $csrf, new PublicContent($config));
    $registerRoutes($healthRouter, $publicController);
    $healthResponse = $healthRouter->dispatch(new Request('GET', '/health', headers: ['accept' => 'application/json']));
    $assert($healthResponse->status() === 200, 'Health route failed.');
    $assert(json_decode($healthResponse->body(), true)['status'] === 'ok', 'Health route payload is invalid.');

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

    echo "Foundation checks passed.\n";
} catch (Throwable $exception) {
    if ($session instanceof SessionManager && session_status() === PHP_SESSION_ACTIVE) {
        $session->invalidate();
    }

    fwrite(STDERR, 'Foundation check failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
