<?php

declare(strict_types=1);

namespace App\Config;

use RuntimeException;

final class Environment
{
    /** @param array<string, string> $values */
    private function __construct(private array $values)
    {
    }

    public static function load(string $path): self
    {
        $values = [];

        if (!is_file($path)) {
            return new self($values);
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            throw new RuntimeException('Unable to read the environment file.');
        }

        foreach ($lines as $lineNumber => $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (str_starts_with($line, 'export ')) {
                $line = trim(substr($line, 7));
            }

            $separator = strpos($line, '=');
            if ($separator === false) {
                throw new RuntimeException(sprintf('Invalid environment entry on line %d.', $lineNumber + 1));
            }

            $key = trim(substr($line, 0, $separator));
            if (preg_match('/^[A-Z][A-Z0-9_]*$/', $key) !== 1) {
                throw new RuntimeException(sprintf('Invalid environment key on line %d.', $lineNumber + 1));
            }

            $value = trim(substr($line, $separator + 1));
            if (strlen($value) >= 2) {
                $quote = $value[0];
                if (($quote === '"' || $quote === "'") && str_ends_with($value, $quote)) {
                    $value = substr($value, 1, -1);
                    if ($quote === '"') {
                        $value = stripcslashes($value);
                    }
                }
            }

            $values[$key] = $value;
        }

        return new self($values);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $serverValue = $_SERVER[$key] ?? $_ENV[$key] ?? getenv($key);
        if ($serverValue !== false && $serverValue !== null) {
            return $serverValue;
        }

        return $this->values[$key] ?? $default;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->get($key);
        if ($value === null || $value === '') {
            return $default;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        if ($parsed === null) {
            throw new RuntimeException(sprintf('Environment value %s must be boolean.', $key));
        }

        return $parsed;
    }

    public function int(string $key, int $default): int
    {
        $value = $this->get($key);
        if ($value === null || $value === '') {
            return $default;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_INT);
        if ($parsed === false) {
            throw new RuntimeException(sprintf('Environment value %s must be an integer.', $key));
        }

        return $parsed;
    }
}

