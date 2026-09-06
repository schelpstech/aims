<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

try {
    $deployment = (string) file_get_contents($root . DIRECTORY_SEPARATOR . 'DEPLOYMENT.md');
    foreach ([
        'Server requirements',
        'Installation',
        'Environment variables',
        'Database and migrations',
        'Uploads, sessions, and permissions',
        'Payment callbacks and webhooks',
        'Logging and rotation',
        'Backup and restore',
        'Post-deployment tests',
        'Rollback',
    ] as $section) {
        $assert(str_contains($deployment, '## ' . $section), "DEPLOYMENT.md is missing the {$section} section.");
    }

    $environment = (string) file_get_contents($root . DIRECTORY_SEPARATOR . '.env.production.example');
    $assert(str_contains($environment, "APP_ENV=production\n"), 'Production environment template has the wrong APP_ENV.');
    $assert(str_contains($environment, "APP_DEBUG=false\n"), 'Production environment template enables debug mode.');
    $assert(str_contains($environment, "SESSION_SECURE_COOKIE=true\n"), 'Production environment template allows insecure session cookies.');
    $assert(str_contains($environment, "PAYMENT_GATEWAY=disabled\n"), 'Production environment template enables an unavailable payment gateway.');
    foreach (['DB_DATABASE=', 'DB_USERNAME=', 'DB_PASSWORD=', 'CERTIFICATE_SIGNING_KEY='] as $emptySecret) {
        $assert(preg_match('/^' . preg_quote($emptySecret, '/') . '$/m', $environment) === 1, "{$emptySecret} must not contain a committed value.");
    }

    $robots = (string) file_get_contents($root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'robots.txt');
    foreach (['/account/', '/admin/', '/portal/', '/payments/', '/media/'] as $path) {
        $assert(str_contains($robots, 'Disallow: ' . $path), "robots.txt does not discourage indexing {$path}.");
    }

    $checker = (string) file_get_contents($root . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'production-check.php');
    $assert(str_contains($checker, 'No connection details were displayed.'), 'Preflight database failure is not explicitly non-disclosing.');
    $assert(str_contains($checker, "exit(\$failures === 0 ? 0 : 1);"), 'Preflight does not fail its process when checks fail.');

    echo "Production readiness checks passed.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'Production readiness check failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
