<?php

declare(strict_types=1);

namespace App\Logging;

use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use Stringable;
use Throwable;

final class Logger
{
    private const LEVELS = [
        'debug' => 100,
        'info' => 200,
        'notice' => 250,
        'warning' => 300,
        'error' => 400,
        'critical' => 500,
        'alert' => 550,
        'emergency' => 600,
    ];

    public function __construct(
        private readonly string $path,
        private readonly string $minimumLevel = 'info',
    ) {
    }

    /** @param array<string, mixed> $context */
    public function log(string $level, string|Stringable $message, array $context = []): void
    {
        $level = strtolower($level);
        if (!isset(self::LEVELS[$level])) {
            $level = 'error';
        }

        $minimum = self::LEVELS[strtolower($this->minimumLevel)] ?? self::LEVELS['info'];
        if (self::LEVELS[$level] < $minimum) {
            return;
        }

        $directory = dirname($this->path);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            error_log('AIMS logger could not create its log directory.');
            return;
        }

        try {
            $record = json_encode([
                'timestamp' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DATE_ATOM),
                'level' => $level,
                'message' => (string) $message,
                'context' => $this->sanitize($context),
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException) {
            error_log('AIMS logger could not encode a log record.');
            return;
        }

        if (file_put_contents($this->path, $record . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
            error_log('AIMS logger could not write a log record.');
        }
    }

    /** @param array<string, mixed> $context */
    public function error(string|Stringable $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function info(string|Stringable $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    private function sanitize(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && preg_match('/password|passwd|secret|token|authorization|cookie|api[_-]?key/i', $key) === 1) {
            return '[REDACTED]';
        }

        if ($value instanceof Throwable) {
            return [
                'class' => $value::class,
                'message' => $value->getMessage(),
                'file' => $value->getFile(),
                'line' => $value->getLine(),
            ];
        }

        if (is_array($value)) {
            $sanitized = [];
            foreach ($value as $itemKey => $item) {
                $sanitized[$itemKey] = $this->sanitize($item, (string) $itemKey);
            }

            return $sanitized;
        }

        if (is_scalar($value) || $value === null) {
            return $value;
        }

        return sprintf('[%s]', get_debug_type($value));
    }
}

