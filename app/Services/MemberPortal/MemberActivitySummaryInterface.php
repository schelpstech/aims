<?php

declare(strict_types=1);

namespace App\Services\MemberPortal;

interface MemberActivitySummaryInterface
{
    /** @return array{certificates: int, programme_enrolments: int, event_registrations: int} */
    public function counts(int $memberId): array;
}
