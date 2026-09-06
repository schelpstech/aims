<?php

declare(strict_types=1);

namespace App\Models;

final class LeadershipPosition extends Model
{
    /** @return array<string, mixed> */
    public function publicPosition(): array
    {
        return [
            'id' => (int) $this->get('id'),
            'name' => (string) $this->get('name'),
            'description' => $this->nullableString('description'),
            'display_order' => (int) $this->get('display_order', 0),
        ];
    }

    private function nullableString(string $key): ?string
    {
        $value = $this->get($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
