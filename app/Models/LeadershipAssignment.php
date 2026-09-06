<?php

declare(strict_types=1);

namespace App\Models;

final class LeadershipAssignment extends Model
{
    /** @return array<string, mixed> */
    public function publicAssignment(): array
    {
        return [
            'id' => (int) $this->get('id'),
            'display_order' => (int) $this->get('display_order', 0),
        ];
    }
}
