<?php

declare(strict_types=1);

namespace App\Models;

use JsonSerializable;

abstract class Model implements JsonSerializable
{
    /** @param array<string, mixed> $attributes */
    public function __construct(protected array $attributes = [])
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->attributes;
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}

