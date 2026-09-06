<?php

declare(strict_types=1);

namespace App\Models;

final class LeadershipGroup extends Model
{
    /** @return array<string, mixed> */
    public function publicGroup(): array
    {
        return [
            'id' => (int) $this->get('id'),
            'name' => (string) $this->get('name'),
            'slug' => (string) $this->get('slug'),
            'description' => $this->nullableString('description'),
            'display_order' => (int) $this->get('display_order', 0),
            'members' => [],
        ];
    }

    private function nullableString(string $key): ?string
    {
        $value = $this->get($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
