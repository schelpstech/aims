<?php

declare(strict_types=1);

namespace App\Config;

use RuntimeException;

final class Config
{
    /** @param array<string, array<string, mixed>> $items */
    private function __construct(private array $items)
    {
    }

    public static function load(string $directory, Environment $environment): self
    {
        if (!is_dir($directory)) {
            throw new RuntimeException('Configuration directory was not found.');
        }

        $files = glob(rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . '*.php') ?: [];
        sort($files);
        $items = [];

        foreach ($files as $file) {
            $values = (static function (string $file, Environment $environment): mixed {
                return require $file;
            })($file, $environment);

            if (!is_array($values)) {
                throw new RuntimeException(sprintf('Configuration file %s must return an array.', basename($file)));
            }

            $items[pathinfo($file, PATHINFO_FILENAME)] = $values;
        }

        return new self($items);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->items;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}

