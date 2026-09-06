<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;
use App\Services\MemberPortal\MemberPortalRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MemberPortalRepository extends Repository implements MemberPortalRepositoryInterface
{
    public function __construct(Connection $database)
    {
        parent::__construct($database);
    }

    public function memberForUser(int $userId): ?array
    {
        $statement = $this->connection()->prepare(<<<'SQL'
            SELECT members.id AS member_id, members.public_id, members.membership_number,
                   members.status, members.joined_at, members.expires_at,
                   grades.name AS membership_grade, grades.abbreviation AS grade_abbreviation,
                   users.email AS account_email,
                   JSON_UNQUOTE(JSON_EXTRACT(applications.personal_information, '$.first_name')) AS first_name,
                   JSON_UNQUOTE(JSON_EXTRACT(applications.personal_information, '$.last_name')) AS last_name,
                   profiles.preferred_name, profiles.phone, profiles.alternate_email,
                   profiles.address, profiles.city, profiles.state_region, profiles.country,
                   profiles.professional_area, profiles.current_role, profiles.biography
            FROM members
            INNER JOIN users ON users.id = members.user_id
            INNER JOIN membership_grades AS grades ON grades.id = members.membership_grade_id
            INNER JOIN membership_applications AS applications ON applications.id = members.approved_application_id
            LEFT JOIN member_profiles AS profiles ON profiles.member_id = members.id
            WHERE members.user_id = :user_id
            LIMIT 1
            SQL);
        $statement->execute(['user_id' => $userId]);
        $member = $statement->fetch();

        return is_array($member) ? $member : null;
    }

    public function updateProfileForUser(int $userId, array $profile, DateTimeImmutable $now): ?array
    {
        return $this->database->transaction(function (PDO $database) use ($userId, $profile, $now): ?array {
            $member = $database->prepare(<<<'SQL'
                SELECT id FROM members
                WHERE user_id = :user_id AND status = 'active'
                LIMIT 1 FOR UPDATE
                SQL);
            $member->execute(['user_id' => $userId]);
            $memberId = $member->fetchColumn();
            if ($memberId === false) {
                return null;
            }

            $statement = $database->prepare(<<<'SQL'
                INSERT INTO member_profiles (
                    member_id, preferred_name, phone, alternate_email, address, city,
                    state_region, country, professional_area, current_role, biography,
                    updated_by_user_id, created_at, updated_at
                ) VALUES (
                    :member_id, :preferred_name, :phone, :alternate_email, :address, :city,
                    :state_region, :country, :professional_area, :current_role, :biography,
                    :updated_by, :created_at, :updated_at
                )
                ON DUPLICATE KEY UPDATE
                    preferred_name = VALUES(preferred_name),
                    phone = VALUES(phone),
                    alternate_email = VALUES(alternate_email),
                    address = VALUES(address),
                    city = VALUES(city),
                    state_region = VALUES(state_region),
                    country = VALUES(country),
                    professional_area = VALUES(professional_area),
                    current_role = VALUES(current_role),
                    biography = VALUES(biography),
                    updated_by_user_id = VALUES(updated_by_user_id),
                    updated_at = VALUES(updated_at)
                SQL);
            $timestamp = $now->format('Y-m-d H:i:s.u');
            $statement->execute([
                'member_id' => (int) $memberId,
                'preferred_name' => $profile['preferred_name'],
                'phone' => $profile['phone'],
                'alternate_email' => $profile['alternate_email'],
                'address' => $profile['address'],
                'city' => $profile['city'],
                'state_region' => $profile['state_region'],
                'country' => $profile['country'],
                'professional_area' => $profile['professional_area'],
                'current_role' => $profile['current_role'],
                'biography' => $profile['biography'],
                'updated_by' => $userId,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            $audit = $database->prepare(<<<'SQL'
                INSERT INTO audit_logs (
                    actor_user_id, action, auditable_type, auditable_id, description, new_values, request_id, created_at
                ) VALUES (
                    :actor, 'member.profile_updated', 'member', :member_id,
                    'A member updated permitted profile fields.', :new_values, :request_id, :created_at
                )
                SQL);
            $audit->execute([
                'actor' => $userId,
                'member_id' => (string) $memberId,
                'new_values' => json_encode(['updated_fields' => array_keys($profile)], JSON_THROW_ON_ERROR),
                'request_id' => bin2hex(random_bytes(16)),
                'created_at' => $timestamp,
            ]);

            return $this->memberForUserUsing($database, $userId);
        });
    }

    public function recentNotificationsForUser(int $userId, int $limit): array
    {
        $statement = $this->connection()->prepare(<<<'SQL'
            SELECT public_id, category, title, body, action_url, read_at, created_at
            FROM member_notifications
            WHERE user_id = :user_id
            ORDER BY created_at DESC, id DESC
            LIMIT :limit
            SQL);
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->bindValue(':limit', max(1, min(20, $limit)), PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    /** @return array<string, mixed>|null */
    private function memberForUserUsing(PDO $database, int $userId): ?array
    {
        $statement = $database->prepare(<<<'SQL'
            SELECT members.id AS member_id, members.public_id, members.membership_number,
                   members.status, members.joined_at, members.expires_at,
                   grades.name AS membership_grade, grades.abbreviation AS grade_abbreviation,
                   users.email AS account_email,
                   JSON_UNQUOTE(JSON_EXTRACT(applications.personal_information, '$.first_name')) AS first_name,
                   JSON_UNQUOTE(JSON_EXTRACT(applications.personal_information, '$.last_name')) AS last_name,
                   profiles.preferred_name, profiles.phone, profiles.alternate_email,
                   profiles.address, profiles.city, profiles.state_region, profiles.country,
                   profiles.professional_area, profiles.current_role, profiles.biography
            FROM members
            INNER JOIN users ON users.id = members.user_id
            INNER JOIN membership_grades AS grades ON grades.id = members.membership_grade_id
            INNER JOIN membership_applications AS applications ON applications.id = members.approved_application_id
            LEFT JOIN member_profiles AS profiles ON profiles.member_id = members.id
            WHERE members.user_id = :user_id LIMIT 1
            SQL);
        $statement->execute(['user_id' => $userId]);
        $member = $statement->fetch();

        return is_array($member) ? $member : null;
    }
}
