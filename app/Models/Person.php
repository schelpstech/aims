<?php

declare(strict_types=1);

namespace App\Models;

final class Person extends Model
{
    /** @return array<string, mixed> */
    public function publicProfile(): array
    {
        return [
            'public_id' => (string) $this->get('public_id', ''),
            'name' => (string) $this->get('full_name', ''),
            'title' => $this->nullableString('title'),
            'qualifications' => $this->nullableString('qualifications'),
            'biography' => $this->nullableString('biography'),
            'photo' => $this->nullableString('photo'),
            'professional_area' => $this->nullableString('professional_area'),
            'email' => $this->nullableString('email'),
            'linkedin' => $this->nullableString('linkedin'),
            'display_order' => (int) $this->get('display_order', 0),
            'active' => (bool) $this->get('active', false),
        ];
    }

    private function nullableString(string $key): ?string
    {
        $value = $this->get($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
