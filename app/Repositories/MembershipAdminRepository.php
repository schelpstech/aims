<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;
use App\Security\Security;
use App\Services\MembershipAdmin\MembershipAdminRepositoryInterface;
use App\Services\Notifications\NotificationOutbox;
use DateTimeImmutable;
use DomainException;
use JsonException;
use PDO;
use PDOException;

final class MembershipAdminRepository extends Repository implements MembershipAdminRepositoryInterface
{
    public function __construct(Connection $database)
    {
        parent::__construct($database);
    }

    public function search(array $filters): array
    {
        $where = ["applications.status <> 'draft'"];
        $parameters = [];
        if (is_string($filters['status'] ?? null) && $filters['status'] !== '') {
            $where[] = 'applications.status = :status';
            $parameters['status'] = $filters['status'];
        }
        if (is_string($filters['grade'] ?? null) && $filters['grade'] !== '') {
            $where[] = 'grades.public_id = :grade';
            $parameters['grade'] = $filters['grade'];
        }
        if (is_string($filters['search'] ?? null) && $filters['search'] !== '') {
            $where[] = <<<'SQL'
                (applications.application_reference LIKE :search
                 OR users.email LIKE :search
                 OR JSON_UNQUOTE(JSON_EXTRACT(applications.personal_information, '$.first_name')) LIKE :search
                 OR JSON_UNQUOTE(JSON_EXTRACT(applications.personal_information, '$.last_name')) LIKE :search)
                SQL;
            $parameters['search'] = '%' . $filters['search'] . '%';
        }

        $from = <<<'SQL'
            FROM membership_applications AS applications
            INNER JOIN users ON users.id = applications.user_id
            LEFT JOIN membership_grades AS grades ON grades.id = applications.membership_grade_id
            SQL;
        $predicate = ' WHERE ' . implode(' AND ', $where);
        $count = $this->connection()->prepare('SELECT COUNT(*) ' . $from . $predicate);
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = 25;
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);

        $statement = $this->connection()->prepare(<<<SQL
            SELECT applications.public_id, applications.application_reference, applications.status,
                   applications.submitted_at, applications.updated_at, users.email,
                   grades.name AS grade_name, grades.abbreviation AS grade_abbreviation,
                   JSON_UNQUOTE(JSON_EXTRACT(applications.personal_information, '$.first_name')) AS first_name,
                   JSON_UNQUOTE(JSON_EXTRACT(applications.personal_information, '$.last_name')) AS last_name
            {$from}{$predicate}
            ORDER BY COALESCE(applications.submitted_at, applications.updated_at) DESC, applications.id DESC
            LIMIT :limit OFFSET :offset
            SQL);
        foreach ($parameters as $key => $value) {
            $statement->bindValue(':' . $key, $value, PDO::PARAM_STR);
        }
        $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $statement->execute();

        return ['items' => $statement->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    public function application(string $applicationPublicId): ?array
    {
        return $this->findApplication($this->connection(), $applicationPublicId);
    }

    public function document(string $documentPublicId): ?array
    {
        $statement = $this->connection()->prepare(<<<'SQL'
            SELECT documents.public_id, documents.original_name, documents.storage_path,
                   documents.mime_type, documents.size_bytes, documents.sha256,
                   applications.public_id AS application_public_id
            FROM membership_application_documents AS documents
            INNER JOIN membership_applications AS applications ON applications.id = documents.membership_application_id
            WHERE documents.public_id = :public_id
              AND documents.deleted_at IS NULL
              AND applications.status <> 'draft'
            LIMIT 1
            SQL);
        $statement->execute(['public_id' => $documentPublicId]);
        $document = $statement->fetch();

        return is_array($document) ? $document : null;
    }

    public function addReviewComment(int $actorUserId, string $applicationPublicId, string $comment, DateTimeImmutable $now): array
    {
        return $this->database->transaction(function (PDO $database) use ($actorUserId, $applicationPublicId, $comment, $now): array {
            $application = $this->lockReviewable($database, $applicationPublicId, ['submitted', 'under_review', 'query_raised', 'approved', 'rejected']);
            if (($application['status'] ?? null) === 'submitted') {
                $this->changeStatus($database, (int) $application['id'], 'submitted', 'under_review', $actorUserId, 'Administrative review started.', $now);
            }
            $this->comment($database, (int) $application['id'], $actorUserId, 'review', $comment, false, $now);
            $this->audit($database, $actorUserId, 'membership.review_comment_recorded', $applicationPublicId, null, $now);

            return $this->findApplication($database, $applicationPublicId) ?? throw new DomainException('Application unavailable.');
        });
    }

    public function raiseQuery(int $actorUserId, string $applicationPublicId, string $query, DateTimeImmutable $now): array
    {
        return $this->database->transaction(function (PDO $database) use ($actorUserId, $applicationPublicId, $query, $now): array {
            $application = $this->lockReviewable($database, $applicationPublicId, ['submitted', 'under_review']);
            $this->changeStatus($database, (int) $application['id'], (string) $application['status'], 'query_raised', $actorUserId, 'A query was raised for the applicant.', $now);
            $this->comment($database, (int) $application['id'], $actorUserId, 'query', $query, true, $now);
            $this->audit($database, $actorUserId, 'membership.application_query_raised', $applicationPublicId, ['query_recorded' => true], $now);
            NotificationOutbox::enqueue($database, 'membership.query', $applicationPublicId, (int) $application['user_id'], null, 'membership', 'Membership application query', 'A query has been raised on your membership application. Sign in to review and respond.', '/membership/apply', ['in_app', 'email'], $now);

            return $this->findApplication($database, $applicationPublicId) ?? throw new DomainException('Application unavailable.');
        });
    }

    public function reject(int $actorUserId, string $applicationPublicId, string $reason, DateTimeImmutable $now): array
    {
        return $this->database->transaction(function (PDO $database) use ($actorUserId, $applicationPublicId, $reason, $now): array {
            $application = $this->lockReviewable($database, $applicationPublicId, ['submitted', 'under_review', 'query_raised']);
            $this->changeStatus($database, (int) $application['id'], (string) $application['status'], 'rejected', $actorUserId, 'Application rejected after review.', $now);
            $this->comment($database, (int) $application['id'], $actorUserId, 'rejection', $reason, true, $now);
            $this->audit($database, $actorUserId, 'membership.application_rejected', $applicationPublicId, ['reason_recorded' => true], $now);
            NotificationOutbox::enqueue($database, 'membership.rejected', $applicationPublicId, (int) $application['user_id'], null, 'membership', 'Membership application decision', 'Your membership application was not approved. Sign in to review the decision.', '/membership/apply', ['in_app', 'email'], $now);

            return $this->findApplication($database, $applicationPublicId) ?? throw new DomainException('Application unavailable.');
        });
    }

    public function approve(
        int $actorUserId,
        string $applicationPublicId,
        ?string $comment,
        string $numberPrefix,
        DateTimeImmutable $now,
    ): array {
        return $this->database->transaction(function (PDO $database) use (
            $actorUserId,
            $applicationPublicId,
            $comment,
            $numberPrefix,
            $now,
        ): array {
            $statement = $database->prepare('SELECT * FROM membership_applications WHERE public_id = :public_id LIMIT 1 FOR UPDATE');
            $statement->execute(['public_id' => $applicationPublicId]);
            $application = $statement->fetch();
            if (!is_array($application)) {
                throw new DomainException('Application unavailable.');
            }

            $existing = $database->prepare(<<<'SQL'
                SELECT public_id, membership_number, status
                FROM members WHERE approved_application_id = :application_id LIMIT 1 FOR UPDATE
                SQL);
            $existing->execute(['application_id' => (int) $application['id']]);
            $member = $existing->fetch();
            if (($application['status'] ?? null) === 'approved' && is_array($member)) {
                return ['idempotent' => true, 'member' => $member, 'application' => $this->findApplication($database, $applicationPublicId)];
            }
            if (!in_array($application['status'] ?? null, ['submitted', 'under_review'], true)) {
                throw new DomainException('Only a submitted application under review can be approved.');
            }
            if (empty($application['membership_grade_id'])) {
                throw new DomainException('The application has no membership grade.');
            }

            $userMember = $database->prepare('SELECT approved_application_id FROM members WHERE user_id = :user_id LIMIT 1 FOR UPDATE');
            $userMember->execute(['user_id' => (int) $application['user_id']]);
            $existingApplicationId = $userMember->fetchColumn();
            if ($existingApplicationId !== false && (int) $existingApplicationId !== (int) $application['id']) {
                throw new DomainException('This applicant already has a member record.');
            }

            $memberPublicId = Security::uuidV4();
            $membershipNumber = '';
            for ($attempt = 0; $attempt < 5; $attempt++) {
                $membershipNumber = $numberPrefix . '-' . $now->format('Y') . '-' . strtoupper(bin2hex(random_bytes(6)));
                try {
                    $insert = $database->prepare(<<<'SQL'
                        INSERT INTO members (
                            public_id, user_id, membership_grade_id, approved_application_id,
                            membership_number, status, joined_at, created_at, updated_at
                        ) VALUES (
                            :public_id, :user_id, :grade_id, :application_id,
                            :membership_number, 'active', :joined_at, :created_at, :updated_at
                        )
                        SQL);
                    $insert->execute([
                        'public_id' => $memberPublicId,
                        'user_id' => (int) $application['user_id'],
                        'grade_id' => (int) $application['membership_grade_id'],
                        'application_id' => (int) $application['id'],
                        'membership_number' => $membershipNumber,
                        'joined_at' => $now->format('Y-m-d'),
                        'created_at' => $this->date($now),
                        'updated_at' => $this->date($now),
                    ]);
                    break;
                } catch (PDOException $exception) {
                    if ($exception->getCode() !== '23000' || $attempt === 4) {
                        throw $exception;
                    }
                    $membershipNumber = '';
                }
            }
            if ($membershipNumber === '') {
                throw new DomainException('A unique membership number could not be generated.');
            }

            $update = $database->prepare(<<<'SQL'
                UPDATE membership_applications SET status = 'approved', updated_at = :updated_at
                WHERE id = :id AND status IN ('submitted', 'under_review')
                SQL);
            $update->execute(['updated_at' => $this->date($now), 'id' => (int) $application['id']]);
            if ($update->rowCount() !== 1) {
                throw new DomainException('The application approval state changed.');
            }
            if ($comment !== null) {
                $this->comment($database, (int) $application['id'], $actorUserId, 'approval', $comment, false, $now);
            }
            $this->changeStatusHistory($database, (int) $application['id'], (string) $application['status'], 'approved', $actorUserId, 'Application approved and membership activated.', $now);
            NotificationOutbox::enqueue($database, 'membership.approved', $applicationPublicId, (int) $application['user_id'], null, 'membership', 'Membership activated', 'Your membership application has been approved and your membership is active.', '/portal/membership', ['in_app', 'email'], $now);
            $this->audit($database, $actorUserId, 'membership.application_approved', $applicationPublicId, [
                'member_public_id' => $memberPublicId,
                'membership_number' => $membershipNumber,
            ], $now);

            return [
                'idempotent' => false,
                'member' => ['public_id' => $memberPublicId, 'membership_number' => $membershipNumber, 'status' => 'active'],
                'application' => $this->findApplication($database, $applicationPublicId),
            ];
        });
    }

    /** @param list<string> $statuses
     *  @return array<string, mixed>
     */
    private function lockReviewable(PDO $database, string $publicId, array $statuses): array
    {
        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
        $statement = $database->prepare("SELECT * FROM membership_applications WHERE public_id = ? AND status IN ({$placeholders}) LIMIT 1 FOR UPDATE");
        $statement->execute([$publicId, ...$statuses]);
        $application = $statement->fetch();
        if (!is_array($application)) {
            throw new DomainException('The application is unavailable for this action.');
        }

        return $application;
    }

    private function changeStatus(PDO $database, int $applicationId, string $from, string $to, int $actor, string $note, DateTimeImmutable $now): void
    {
        $statement = $database->prepare('UPDATE membership_applications SET status = :status, updated_at = :updated_at WHERE id = :id AND status = :from_status');
        $statement->execute(['status' => $to, 'updated_at' => $this->date($now), 'id' => $applicationId, 'from_status' => $from]);
        if ($statement->rowCount() !== 1) {
            throw new DomainException('The application status changed before this action completed.');
        }
        $this->changeStatusHistory($database, $applicationId, $from, $to, $actor, $note, $now);
    }

    private function changeStatusHistory(PDO $database, int $applicationId, string $from, string $to, int $actor, string $note, DateTimeImmutable $now): void
    {
        $statement = $database->prepare(<<<'SQL'
            INSERT INTO membership_history (membership_application_id, from_status, to_status, note, changed_by_user_id, created_at)
            VALUES (:application_id, :from_status, :to_status, :note, :actor, :created_at)
            SQL);
        $statement->execute(['application_id' => $applicationId, 'from_status' => $from, 'to_status' => $to, 'note' => $note, 'actor' => $actor, 'created_at' => $this->date($now)]);
    }

    private function comment(PDO $database, int $applicationId, int $actor, string $type, string $body, bool $visible, DateTimeImmutable $now): void
    {
        $statement = $database->prepare(<<<'SQL'
            INSERT INTO membership_review_comments (
                public_id, membership_application_id, author_user_id, comment_type, body, visible_to_applicant, created_at
            ) VALUES (:public_id, :application_id, :actor, :type, :body, :visible, :created_at)
            SQL);
        $statement->execute([
            'public_id' => Security::uuidV4(),
            'application_id' => $applicationId,
            'actor' => $actor,
            'type' => $type,
            'body' => $body,
            'visible' => $visible ? 1 : 0,
            'created_at' => $this->date($now),
        ]);
    }

    /** @param array<string, mixed>|null $values */
    private function audit(PDO $database, int $actor, string $action, string $publicId, ?array $values, DateTimeImmutable $now): void
    {
        $statement = $database->prepare(<<<'SQL'
            INSERT INTO audit_logs (
                actor_user_id, action, auditable_type, auditable_id, description, new_values, request_id, created_at
            ) VALUES (
                :actor, :action, 'membership_application', :auditable_id,
                'An administrative membership action was completed.', :new_values, :request_id, :created_at
            )
            SQL);
        $statement->execute([
            'actor' => $actor,
            'action' => $action,
            'auditable_id' => $publicId,
            'new_values' => $values === null ? null : $this->json($values),
            'request_id' => bin2hex(random_bytes(16)),
            'created_at' => $this->date($now),
        ]);
    }

    /** @return array<string, mixed>|null */
    private function findApplication(PDO $database, string $publicId): ?array
    {
        $statement = $database->prepare(<<<'SQL'
            SELECT applications.*, users.email AS account_email,
                   grades.public_id AS grade_public_id, grades.name AS grade_name,
                   grades.abbreviation AS grade_abbreviation,
                   members.public_id AS member_public_id, members.membership_number, members.status AS member_status
            FROM membership_applications AS applications
            INNER JOIN users ON users.id = applications.user_id
            LEFT JOIN membership_grades AS grades ON grades.id = applications.membership_grade_id
            LEFT JOIN members ON members.approved_application_id = applications.id
            WHERE applications.public_id = :public_id AND applications.status <> 'draft'
            LIMIT 1
            SQL);
        $statement->execute(['public_id' => $publicId]);
        $application = $statement->fetch();
        if (!is_array($application)) {
            return null;
        }
        foreach (['personal_information', 'contact_information', 'professional_details', 'education', 'employment'] as $field) {
            $application[$field] = $this->decode($application[$field] ?? null);
        }

        $documents = $database->prepare(<<<'SQL'
            SELECT public_id, document_type, original_name, mime_type, size_bytes, sha256, created_at
            FROM membership_application_documents
            WHERE membership_application_id = :application_id AND deleted_at IS NULL ORDER BY created_at, id
            SQL);
        $documents->execute(['application_id' => (int) $application['id']]);
        $application['documents'] = $documents->fetchAll();
        $comments = $database->prepare(<<<'SQL'
            SELECT comments.public_id, comments.comment_type, comments.body, comments.visible_to_applicant,
                   comments.created_at, users.email AS author_email
            FROM membership_review_comments AS comments
            INNER JOIN users ON users.id = comments.author_user_id
            WHERE comments.membership_application_id = :application_id ORDER BY comments.created_at, comments.id
            SQL);
        $comments->execute(['application_id' => (int) $application['id']]);
        $application['comments'] = $comments->fetchAll();
        $history = $database->prepare(<<<'SQL'
            SELECT from_status, to_status, note, created_at
            FROM membership_history WHERE membership_application_id = :application_id ORDER BY created_at, id
            SQL);
        $history->execute(['application_id' => (int) $application['id']]);
        $application['history'] = $history->fetchAll();
        unset($application['id'], $application['user_id'], $application['membership_grade_id']);

        return $application;
    }

    /** @return array<string, mixed> */
    private function decode(mixed $value): array
    {
        $decoded = is_string($value) ? json_decode($value, true) : null;

        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<string, mixed> $value
     *  @throws JsonException
     */
    private function json(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    private function date(DateTimeImmutable $date): string
    {
        return $date->format('Y-m-d H:i:s.u');
    }
}
