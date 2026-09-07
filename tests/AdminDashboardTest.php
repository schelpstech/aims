<?php

declare(strict_types=1);

use App\Authorization\PermissionCheckerInterface;
use App\Config\Config;
use App\Config\Environment;
use App\Controllers\Admin\AdminDashboardController;
use App\Http\Request;
use App\Security\Csrf;
use App\Security\SessionManager;
use App\Services\PublicSite\PublicContent;
use App\View\View;

require dirname(__DIR__) . '/vendor/autoload.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$temporaryDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'aims-admin-dashboard-test-' . bin2hex(random_bytes(6));
$session = null;

try {
    mkdir($temporaryDirectory, 0700, true);
    $environment = Environment::load($temporaryDirectory . DIRECTORY_SEPARATOR . '.env.missing');
    $config = Config::load(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config', $environment);
    $session = new SessionManager([
        'name' => 'aims_admin_dashboard_test_' . bin2hex(random_bytes(4)),
        'lifetime' => 120,
        'save_path' => $temporaryDirectory . DIRECTORY_SEPARATOR . 'sessions',
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'http_only' => true,
        'same_site' => 'Lax',
    ]);
    $view = new View(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views');
    $content = new PublicContent($config);
    $allowedPermissions = ['cms.edit', 'report.view'];
    $checker = new class($allowedPermissions) implements PermissionCheckerInterface {
        /** @param list<string> $allowed */
        public function __construct(private readonly array $allowed)
        {
        }

        public function allows(int $userId, string $permission): bool
        {
            return $userId === 7 && in_array($permission, $this->allowed, true);
        }
    };

    $controller = new AdminDashboardController($view, new Csrf($session), $checker, $content);
    $request = new Request('GET', '/admin');
    $request->setAttribute('auth.user', ['id' => 7, 'email' => 'admin@example.test']);
    $response = $controller->index($request);
    $body = $response->body();

    $assert($response->status() === 200, 'An authorized administrator could not open the dashboard.');
    $assert(str_contains($body, '/admin/cms'), 'The dashboard omitted an authorized CMS module.');
    $assert(str_contains($body, '/admin/reports'), 'The dashboard omitted an authorized reporting module.');
    $assert(!str_contains($body, '/admin/events'), 'The dashboard exposed an unauthorized event module.');
    $assert(str_contains($body, '/account/security'), 'The dashboard omitted account security.');
    $assert(str_contains($body, 'noindex, nofollow'), 'The restricted dashboard is indexable.');
    $assert(($response->headers()['Cache-Control'] ?? '') === 'no-store, private', 'The restricted dashboard may be cached.');

    $layout = (string) file_get_contents(dirname(__DIR__) . '/resources/views/layouts/public.php');
    $assert(str_contains($layout, "str_starts_with(\$requestPath, '/admin/')"), 'Administrative child pages do not receive shared dashboard navigation.');
    $assert(str_contains($layout, 'Back to admin dashboard'), 'The shared admin dashboard return control is missing.');

    $deniedChecker = new class implements PermissionCheckerInterface {
        public function allows(int $userId, string $permission): bool
        {
            return false;
        }
    };
    $deniedController = new AdminDashboardController($view, new Csrf($session), $deniedChecker, $content);
    $deniedRequest = new Request('GET', '/admin');
    $deniedRequest->setAttribute('auth.user', ['id' => 8, 'email' => 'member@example.test']);
    $assert($deniedController->index($deniedRequest)->status() === 403, 'A user without administrative permissions opened the dashboard.');

    if (session_status() === PHP_SESSION_ACTIVE) {
        $session->invalidate();
    }
    $session = null;

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($temporaryDirectory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($temporaryDirectory);

    echo "Admin dashboard checks passed: permission-aware modules, shared return navigation, account security, cache protection, and access denial.\n";
} catch (Throwable $exception) {
    if ($session instanceof SessionManager && session_status() === PHP_SESSION_ACTIVE) {
        $session->invalidate();
    }
    fwrite(STDERR, 'Admin dashboard check failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
