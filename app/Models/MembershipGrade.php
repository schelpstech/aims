<?php

declare(strict_types=1);

namespace App\Models;

final class MembershipGrade extends Model
{
    /** @return array<string, mixed> */
    public function publicDetails(): array
    {
        return [
            'public_id' => (string) $this->get('public_id', ''),
            'name' => (string) $this->get('name', ''),
            'abbreviation' => $this->nullableString('abbreviation'),
            'description' => $this->nullableString('description'),
            'eligibility' => $this->nullableString('eligibility'),
            'benefits' => $this->nullableString('benefits'),
            'application_fee' => $this->get('application_fee'),
            'annual_fee' => $this->get('annual_fee'),
            'fee_currency' => $this->nullableString('fee_currency'),
            'display_order' => (int) $this->get('display_order', 0),
        ];
    }

    private function nullableString(string $key): ?string
    {
        $value = $this->get($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
