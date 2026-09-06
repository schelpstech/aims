<?php

declare(strict_types=1);

namespace App\Services\MemberPortal;

use App\Database\Connection;

final class PdoMemberActivitySummary implements MemberActivitySummaryInterface
{
    public function __construct(private readonly Connection $database) {}
    public function counts(int $memberId): array
    {
        $statement = $this->database->get()->prepare(<<<'SQL'
            SELECT
                (SELECT COUNT(*) FROM certificates
                 WHERE certificates.member_id = members.id
                   AND certificates.status IN ('issued', 'expired')) AS certificates,
                (SELECT COUNT(*) FROM programme_enrolments
                 WHERE programme_enrolments.user_id = members.user_id) AS programme_enrolments,
                (SELECT COUNT(*) FROM event_registrations
                 WHERE event_registrations.user_id = members.user_id
                   AND event_registrations.status IN ('registered', 'attended')) AS event_registrations
            FROM members
            WHERE members.id = :member_id
            LIMIT 1
            SQL);
        $statement->execute(['member_id' => $memberId]);
        $counts = $statement->fetch();

        return [
            'certificates' => (int) ($counts['certificates'] ?? 0),
            'programme_enrolments' => (int) ($counts['programme_enrolments'] ?? 0),
            'event_registrations' => (int) ($counts['event_registrations'] ?? 0),
        ];
    }
}
