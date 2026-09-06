<?php

declare(strict_types=1);

namespace App\Models;

final class MembershipApplication extends Model
{
    public const STATUSES = [
        'draft',
        'submitted',
        'under_review',
        'query_raised',
        'approved',
        'rejected',
        'cancelled',
    ];

    public function isApplicantEditable(): bool
    {
        return in_array((string) $this->get('status'), ['draft', 'query_raised'], true);
    }

    /** @return array<string, mixed> */
    public function details(): array
    {
        return $this->toArray() + [
            'documents' => [],
            'history' => [],
        ];
    }
}
