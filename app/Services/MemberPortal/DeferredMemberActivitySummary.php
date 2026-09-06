<?php

declare(strict_types=1);

namespace App\Services\MemberPortal;

final class DeferredMemberActivitySummary implements MemberActivitySummaryInterface
{
    public function counts(int $memberId): array
    {
        return ['certificates' => 0, 'programme_enrolments' => 0, 'event_registrations' => 0];
    }
}
