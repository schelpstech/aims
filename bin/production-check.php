<?php

declare(strict_types=1);

use App\Config\Config;
use App\Config\Environment;
use App\Database\Connection;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$basePath = dirname(__DIR__);
$autoload = $basePath . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

if (!is_file($autoload)) {
    fwrite(STDERR, "[FAIL] Composer dependencies are not installed.\n");
    exit(1);
}

require $autoload;

$failures = 0;
$warnings = 0;

$report = static function (string $level, string $message) use (&$failures, &$warnings): void {
    if ($level === 'FAIL') {
        $failures++;
    } elseif ($level === 'WARN') {
        $warnings++;
    }

    fwrite($level === 'FAIL' ? STDERR : STDOUT, sprintf('[%s] %s%s', $level, $message, PHP_EOL));
};

$check = static function (bool $condition, string $pass, string $fail) use ($report): void {
    $report($condition ? 'PASS' : 'FAIL', $condition ? $pass : $fail);
};

$resolvePath = static function (string $path) use ($basePath): string {
    if (preg_match('/^(?:[A-Za-z]:[\\\\\/]|\/)/', $path) === 1) {
        return rtrim($path, '/\\');
    }

    return $basePath . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($path, '/\\'));
};

$outsidePublic = static function (string $path) use ($basePath): bool {
    $normalized = strtolower(str_replace('\\', '/', rtrim($path, '/\\')));
    $public = strtolower(str_replace('\\', '/', $basePath . DIRECTORY_SEPARATOR . 'public'));

    return $normalized !== $public && !str_starts_with($normalized, $public . '/');
};

$iniBytes = static function (string $value): int {
    $value = trim($value);
    if ($value === '') {
        return 0;
    }
    $unit = strtolower(substr($value, -1));
    $number = (int) $value;

    return match ($unit) {
        'g' => $number * 1024 * 1024 * 1024,
        'm' => $number * 1024 * 1024,
        'k' => $number * 1024,
        default => $number,
    };
};

try {
    $environment = Environment::load($basePath . DIRECTORY_SEPARATOR . '.env');
    $config = Config::load($basePath . DIRECTORY_SEPARATOR . 'config', $environment);

    $check(PHP_VERSION_ID >= 80200, 'PHP version satisfies 8.2 or newer.', 'PHP 8.2 or newer is required.');
    foreach (['fileinfo', 'dom', 'json', 'mbstring', 'pdo', 'pdo_mysql'] as $extension) {
        $check(extension_loaded($extension), "PHP extension {$extension} is loaded.", "Required PHP extension {$extension} is not loaded.");
    }

    $check(is_file($basePath . DIRECTORY_SEPARATOR . '.env'), 'A deployment environment file exists.', 'Create a private .env file from .env.production.example.');
    $check($config->get('app.environment') === 'production', 'Application environment is production.', 'APP_ENV must be production.');
    $check($config->get('app.debug') === false, 'Debug responses are disabled.', 'APP_DEBUG must be false.');
    $check(strtolower((string) $config->get('logging.level', 'info')) !== 'debug', 'Production logging is not set to debug.', 'LOG_LEVEL must not be debug in production.');

    $applicationUrl = rtrim((string) $config->get('app.url', ''), '/');
    $check(filter_var($applicationUrl, FILTER_VALIDATE_URL) !== false && str_starts_with(strtolower($applicationUrl), 'https://'), 'APP_URL is an absolute HTTPS URL.', 'APP_URL must be the canonical absolute HTTPS production URL.');

    $session = (array) $config->get('session', []);
    $check(($session['secure'] ?? false) === true, 'Session cookies require HTTPS.', 'SESSION_SECURE_COOKIE must be true.');
    $check(($session['http_only'] ?? false) === true, 'Session cookies are HttpOnly.', 'Session cookies must be HttpOnly.');
    $check(in_array($session['same_site'] ?? null, ['Lax', 'Strict', 'None'], true), 'Session SameSite policy is valid.', 'SESSION_SAME_SITE must be Lax, Strict, or None.');

    $paths = [
        'log' => $resolvePath((string) $config->get('logging.path', 'storage/logs/application.log')),
        'session' => $resolvePath((string) ($session['save_path'] ?? 'storage/sessions')),
        'membership upload' => $resolvePath((string) $config->get('membership.documents.path', 'storage/private/membership-documents')),
        'CMS upload' => $resolvePath((string) $config->get('cms.media_path', 'storage/private/cms-media')),
    ];
    foreach ($paths as $label => $path) {
        $targetDirectory = $label === 'log' ? dirname($path) : $path;
        $check($outsidePublic($targetDirectory), ucfirst($label) . ' storage is outside public/.', ucfirst($label) . ' storage must be outside public/.');
        $check(is_dir($targetDirectory) && is_writable($targetDirectory), ucfirst($label) . ' directory exists and is writable.', ucfirst($label) . ' directory must exist and be writable by the PHP worker.');
    }

    $membershipLimit = (int) $config->get('membership.documents.max_bytes', 5 * 1024 * 1024);
    $cmsLimit = (int) $config->get('cms.max_upload_bytes', 10 * 1024 * 1024);
    $largestUpload = max($membershipLimit, $cmsLimit);
    $check(filter_var(ini_get('file_uploads'), FILTER_VALIDATE_BOOL), 'PHP file uploads are enabled.', 'PHP file_uploads must be enabled.');
    $check($iniBytes((string) ini_get('upload_max_filesize')) >= $largestUpload, 'upload_max_filesize covers the configured application limit.', 'PHP upload_max_filesize is lower than an application upload limit.');
    $check($iniBytes((string) ini_get('post_max_size')) >= $largestUpload, 'post_max_size covers the configured application limit.', 'PHP post_max_size is lower than an application upload limit.');

    $databaseConfig = (array) $config->get('database.connections.mysql', []);
    $databaseConfigured = true;
    foreach (['host', 'database', 'username', 'password'] as $key) {
        if (trim((string) ($databaseConfig[$key] ?? '')) === '') {
            $databaseConfigured = false;
        }
    }
    $check($databaseConfigured, 'Database settings are present.', 'Database settings are incomplete. No values were displayed.');

    if ($databaseConfigured) {
        try {
            $database = (new Connection($config))->get();
            $engine = strtolower((string) $database->query('SELECT @@default_storage_engine')->fetchColumn());
            $check($engine === 'innodb', 'Database default storage engine is InnoDB.', 'Database default storage engine must be InnoDB.');
            $migrationTable = $database->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'migrations'")->fetchColumn();
            if ((int) $migrationTable !== 1) {
                $report('FAIL', 'The migrations table does not exist; migrations have not been applied.');
            } else {
                $applied = $database->query('SELECT migration FROM migrations')->fetchAll(PDO::FETCH_COLUMN);
                $available = array_map(
                    static fn (string $file): string => pathinfo($file, PATHINFO_FILENAME),
                    glob($basePath . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR . '*.php') ?: [],
                );
                $pending = array_diff($available, array_map('strval', $applied));
                $check($pending === [], 'All available database migrations are applied.', sprintf('%d database migration(s) are pending.', count($pending)));
            }
        } catch (Throwable) {
            $report('FAIL', 'Database connectivity or readiness check failed. No connection details were displayed.');
        }
    }

    $mail = (array) $config->get('mail', []);
    if (($mail['enabled'] ?? false) !== true) {
        $report('WARN', 'Email delivery is disabled; verification and password-reset email will not be delivered.');
    } else {
        $check(filter_var($mail['from_address'] ?? '', FILTER_VALIDATE_EMAIL) !== false, 'Mail sender address is valid.', 'MAIL_FROM_ADDRESS must be a valid address.');
        $report('WARN', 'Email uses PHP mail(); verify the host MTA and external delivery before launch.');
    }

    $gateway = strtolower((string) $config->get('payments.gateway', 'disabled'));
    if ($gateway === 'disabled') {
        $report('WARN', 'Online payments are disabled. This is the safe setting until an approved gateway adapter is installed.');
    } else {
        $report('FAIL', 'PAYMENT_GATEWAY is enabled, but this release wires only the unavailable gateway adapter.');
    }

    $signingKey = (string) $config->get('certificates.signing_key', '');
    $verificationUrl = (string) $config->get('certificates.verification_url', '');
    if (strlen($signingKey) < 32 || !str_starts_with(strtolower($verificationUrl), 'https://')) {
        $report('WARN', 'Certificate issuance remains unavailable until a strong signing key and HTTPS verification URL are configured.');
    } else {
        $report('PASS', 'Certificate signing and verification settings are present.');
    }

    $check(is_file($basePath . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'robots.txt'), 'robots.txt is present.', 'public/robots.txt is missing.');
    if (!is_file($basePath . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'sitemap.xml')) {
        $report('WARN', 'sitemap.xml is intentionally pending until the canonical production domain is verified.');
    } else {
        $report('PASS', 'sitemap.xml is present.');
    }
} catch (Throwable) {
    $report('FAIL', 'Production checks could not load the application configuration. No sensitive details were displayed.');
}

fwrite(STDOUT, sprintf('Production readiness result: %d failure(s), %d warning(s).%s', $failures, $warnings, PHP_EOL));
exit($failures === 0 ? 0 : 1);
