<?php

declare(strict_types=1);

use App\Config\Config;
use App\Config\Environment;
use App\Database\Connection;
use App\Logging\Logger;
use App\Services\Notifications\NativeEmailChannel;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\ScheduledNotificationProducer;
use App\Services\Notifications\UnavailableSmsChannel;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$basePath = dirname(__DIR__);
$autoload = $basePath . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Application dependencies are not installed.\n");
    exit(1);
}

require $autoload;

$environment = Environment::load($basePath . DIRECTORY_SEPARATOR . '.env');
$config = Config::load($basePath . DIRECTORY_SEPARATOR . 'config', $environment);
$logPath = (string) $config->get('logging.path', 'storage/logs/application.log');
if (preg_match('/^(?:[A-Za-z]:[\\\\\/]|\/)/', $logPath) !== 1) {
    $logPath = $basePath . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $logPath);
}
$logger = new Logger($logPath, (string) $config->get('logging.level', 'info'));

try {
    $database = (new Connection($config))->get();
    $queued = (new ScheduledNotificationProducer($database))->queue(new DateTimeImmutable('now'));
    $result = (new NotificationDispatcher(
        $database,
        new NativeEmailChannel((array) $config->get('mail', [])),
        new UnavailableSmsChannel(),
        $logger,
        (int) $config->get('notifications.max_attempts', 5),
    ))->dispatch(max(1, (int) ($argv[1] ?? 50)));

    echo json_encode(['scheduled_queued' => $queued] + $result, JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $exception) {
    $logger->error('Notification worker failed.', ['exception' => $exception]);
    fwrite(STDERR, "Notification worker failed.\n");
    exit(1);
}
