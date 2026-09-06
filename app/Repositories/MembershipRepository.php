<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;
use App\Security\Security;
use App\Services\Membership\MembershipRepositoryInterface;
use App\Services\Notifications\NotificationOutbox;
use DateTimeImmutable;
use DomainException;
use JsonException;
use PDO;

final class MembershipRepository extends Repository implements MembershipRepositoryInterface
{
    public function __construct(Connection $database)
    {
        parent::__construct($database);
    }

    public function activeGrades(): array
    {
        $statement = $this->connection()->query(<<<'SQL'
            SELECT public_id, name, abbreviation, description, eligibility, benefits,
                   application_fee, annual_fee, fee_currency, display_order
            FROM membership_grades
            WHERE active = 1
            ORDER BY display_order, name
            SQL);

        return $statement->fetchAll();
    }

    public function currentApplication(int $userId): ?array
    {
        $statement = $this->connection()->prepare(<<<'SQL'
            SELECT applications.*, grades.public_id AS grade_public_id, grades.name AS grade_name,
                   grades.abbreviation AS grade_abbreviation
            FROM membership_applications AS applications
            LEFT JOIN membership_grades AS grades ON grades.id = applications.membership_grade_id
            WHERE applications.user_id = :user_id
            ORDER BY FIELD(applications.status, 'draft', 'query_raised', 'submitted', 'under_review', 'approved', 'rejected', 'cancelled'),
                     applications.updated_at DESC
            LIMIT 1
            SQL);
        $statement->execute(['user_id' => $userId]);
        $application = $statement->fetch();

        return is_array($application) ? $this->hydrate($application) : null;
    }

    public function saveDraft(
        int $userId,
        ?string $applicationPublicId,
        ?string $gradePublicId,
        array $sections,
        DateTimeImmutable $now,
    ): array {
        return $this->database->transaction(function (PDO $database) use (
            $userId,
            $applicationPublicId,
            $gradePublicId,
            $sections,
            $now,
        ): array {
            $this->lockUser($database, $userId);
            $gradeId = $this->gradeId($database, $gradePublicId);
            $application = $this->lockEditableApplication($database, $userId, $applicationPublicId);
            $timestamp = $this->date($now);

            if ($applicationPublicId !== null && $applicationPublicId !== '' && $application === null) {
                throw new DomainException('This application cannot be edited.');
            }

            if ($application === null) {
                $publicId = Security::uuidV4();
                $statement = $database->prepare(<<<'SQL'
                    INSERT INTO membership_applications (
                        public_id, user_id, membership_grade_id, status,
                        personal_information, contact_information, professional_details,
                        education, employment, created_at, updated_at
                    ) VALUES (
                        :public_id, :user_id, :grade_id, 'draft',
                        :personal, :contact, :professional, :education, :employment, :created_at, :updated_at
                    )
                    SQL);
                $statement->execute($this->sectionParameters($sections) + [
                    'public_id' => $publicId,
                    'user_id' => $userId,
                    'grade_id' => $gradeId,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);
                $applicationId = (int) $database->lastInsertId();
                $this->history($database, $applicationId, null, 'draft', 'Application draft created.', $userId, $now);
                $this->audit($database, $userId, 'membership.application_draft_created', $publicId, null, $now);
            } else {
                $applicationId = (int) $application['id'];
                $publicId = (string) $application['public_id'];
                $statement = $database->prepare(<<<'SQL'
                    UPDATE membership_applications
                    SET membership_grade_id = :grade_id,
                        personal_information = :personal,
                        contact_information = :contact,
                        professional_details = :professional,
                        education = :education,
                        employment = :employment,
                        updated_at = :updated_at
                    WHERE id = :id AND user_id = :user_id AND status IN ('draft', 'query_raised')
                    SQL);
                $statement->execute($this->sectionParameters($sections) + [
                    'grade_id' => $gradeId,
                    'updated_at' => $timestamp,
                    'id' => $applicationId,
                    'user_id' => $userId,
                ]);
                $this->audit($database, $userId, 'membership.application_draft_saved', $publicId, null, $now);
            }

            return $this->findApplication($database, $userId, $publicId) ?? throw new DomainException('The application could not be loaded.');
        });
    }

    public function applicationForUser(int $userId, string $applicationPublicId): ?array
    {
        return $this->findApplication($this->connection(), $userId, $applicationPublicId);
    }

    public function addDocument(int $userId, string $applicationPublicId, array $document, DateTimeImmutable $now): array
    {
        return $this->database->transaction(function (PDO $database) use ($userId, $applicationPublicId, $document, $now): array {
            $application = $this->lockEditableApplication($database, $userId, $applicationPublicId);
            if ($application === null) {
                throw new DomainException('This application cannot accept documents.');
            }

            $publicId = Security::uuidV4();
            $statement = $database->prepare(<<<'SQL'
                INSERT INTO membership_application_documents (
                    public_id, membership_application_id, uploaded_by_user_id, document_type,
                    original_name, storage_path, mime_type, size_bytes, sha256, created_at
                ) VALUES (
                    :public_id, :application_id, :user_id, :document_type,
                    :original_name, :storage_path, :mime_type, :size_bytes, :sha256, :created_at
                )
                SQL);
            $statement->execute([
                'public_id' => $publicId,
                'application_id' => (int) $application['id'],
                'user_id' => $userId,
                'document_type' => $document['document_type'],
                'original_name' => $document['original_name'],
                'storage_path' => $document['storage_path'],
                'mime_type' => $document['mime_type'],
                'size_bytes' => $document['size_bytes'],
                'sha256' => $document['sha256'],
                'created_at' => $this->date($now),
            ]);
            $this->audit($database, $userId, 'membership.document_uploaded', $applicationPublicId, [
                'document_public_id' => $publicId,
                'document_type' => $document['document_type'],
            ], $now);

            return ['public_id' => $publicId] + $document;
        });
    }

    public function submit(
        int $userId,
        string $applicationPublicId,
        string $reference,
        string $declarationName,
        DateTimeImmutable $now,
    ): ?array {
        return $this->database->transaction(function (PDO $database) use (
            $userId,
            $applicationPublicId,
            $reference,
            $declarationName,
            $now,
        ): ?array {
            $application = $this->lockEditableApplication($database, $userId, $applicationPublicId);
            if ($application === null) {
                return null;
            }
            $this->assertSubmittable($application);
            $documentCount = $database->prepare(<<<'SQL'
                SELECT COUNT(*) FROM membership_application_documents
                WHERE membership_application_id = :application_id AND deleted_at IS NULL
                SQL);
            $documentCount->execute(['application_id' => (int) $application['id']]);
            if ((int) $documentCount->fetchColumn() < 1) {
                throw new DomainException('Upload at least one supporting document before submission.');
            }

            $fromStatus = (string) $application['status'];
            $reference = is_string($application['application_reference'] ?? null)
                && $application['application_reference'] !== ''
                ? $application['application_reference']
                : $reference;
            $statement = $database->prepare(<<<'SQL'
                UPDATE membership_applications
                SET application_reference = :reference,
                    status = 'submitted',
                    declaration_name = :declaration_name,
                    declaration_accepted_at = :accepted_at,
                    submitted_at = :submitted_at,
                    updated_at = :updated_at
                WHERE id = :id AND user_id = :user_id AND status IN ('draft', 'query_raised')
                SQL);
            $statement->execute([
                'reference' => $reference,
                'declaration_name' => $declarationName,
                'accepted_at' => $this->date($now),
                'submitted_at' => $this->date($now),
                'updated_at' => $this->date($now),
                'id' => (int) $application['id'],
                'user_id' => $userId,
            ]);
            if ($statement->rowCount() !== 1) {
                return null;
            }

            $this->history($database, (int) $application['id'], $fromStatus, 'submitted', 'Application submitted by applicant.', $userId, $now);
            $this->audit($database, $userId, 'membership.application_submitted', $applicationPublicId, [
                'application_reference' => $reference,
            ], $now);
            NotificationOutbox::enqueue($database, 'membership.submitted', $applicationPublicId, $userId, null, 'membership', 'Membership application submitted', 'Your membership application has been received and is awaiting review.', '/membership/apply', ['in_app', 'email'], $now);

            return $this->findApplication($database, $userId, $applicationPublicId);
        });
    }

    public function cancel(int $userId, string $applicationPublicId, DateTimeImmutable $now): ?array
    {
        return $this->database->transaction(function (PDO $database) use ($userId, $applicationPublicId, $now): ?array {
            $application = $this->lockEditableApplication($database, $userId, $applicationPublicId);
            if ($application === null) {
                return null;
            }
            $statement = $database->prepare(<<<'SQL'
                UPDATE membership_applications
                SET status = 'cancelled', cancelled_at = :cancelled_at, updated_at = :updated_at
                WHERE id = :id AND user_id = :user_id AND status IN ('draft', 'query_raised')
                SQL);
            $statement->execute([
                'cancelled_at' => $this->date($now),
                'updated_at' => $this->date($now),
                'id' => (int) $application['id'],
                'user_id' => $userId,
            ]);
            if ($statement->rowCount() !== 1) {
                return null;
            }
            $this->history($database, (int) $application['id'], (string) $application['status'], 'cancelled', 'Application cancelled by applicant.', $userId, $now);
            $this->audit($database, $userId, 'membership.application_cancelled', $applicationPublicId, null, $now);

            return $this->findApplication($database, $userId, $applicationPublicId);
        });
    }

    private function lockUser(PDO $database, int $userId): void
    {
        $statement = $database->prepare('SELECT id FROM users WHERE id = :id AND deleted_at IS NULL LIMIT 1 FOR UPDATE');
        $statement->execute(['id' => $userId]);
        if ($statement->fetchColumn() === false) {
            throw new DomainException('The applicant account is unavailable.');
        }
    }

    /** @return array<string, mixed>|null */
    private function lockEditableApplication(PDO $database, int $userId, ?string $publicId): ?array
    {
        if ($publicId !== null && $publicId !== '') {
            $statement = $database->prepare(<<<'SQL'
                SELECT * FROM membership_applications
                WHERE public_id = :public_id AND user_id = :user_id AND status IN ('draft', 'query_raised')
                LIMIT 1 FOR UPDATE
                SQL);
            $statement->execute(['public_id' => $publicId, 'user_id' => $userId]);
        } else {
            $statement = $database->prepare(<<<'SQL'
                SELECT * FROM membership_applications
                WHERE user_id = :user_id AND status IN ('draft', 'query_raised')
                ORDER BY updated_at DESC LIMIT 1 FOR UPDATE
                SQL);
            $statement->execute(['user_id' => $userId]);
        }
        $application = $statement->fetch();

        return is_array($application) ? $application : null;
    }

    private function gradeId(PDO $database, ?string $publicId): ?int
    {
        if ($publicId === null || $publicId === '') {
            return null;
        }
        $statement = $database->prepare('SELECT id FROM membership_grades WHERE public_id = :public_id AND active = 1 LIMIT 1');
        $statement->execute(['public_id' => $publicId]);
        $id = $statement->fetchColumn();
        if ($id === false) {
            throw new DomainException('Select an available membership grade.');
        }

        return (int) $id;
    }

    /** @param array<string, mixed> $application */
    private function assertSubmittable(array $application): void
    {
        if (empty($application['membership_grade_id'])) {
            throw new DomainException('Select a membership grade before submission.');
        }
        $required = [
            [$this->decode($application['personal_information'] ?? null), ['first_name', 'last_name']],
            [$this->decode($application['contact_information'] ?? null), ['phone', 'contact_email', 'address', 'country']],
            [$this->decode($application['professional_details'] ?? null), ['professional_area', 'current_role']],
            [$this->decode($application['education'] ?? null), ['summary']],
            [$this->decode($application['employment'] ?? null), ['summary']],
        ];
        foreach ($required as [$section, $fields]) {
            foreach ($fields as $field) {
                if (!is_string($section[$field] ?? null) || trim($section[$field]) === '') {
                    throw new DomainException('Complete every required section before submission.');
                }
            }
        }
        $email = $this->decode($application['contact_information'] ?? null)['contact_email'] ?? null;
        if (!is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new DomainException('Enter a valid contact email address before submission.');
        }
    }

    /** @return array<string, mixed>|null */
    private function findApplication(PDO $database, int $userId, string $publicId): ?array
    {
        $statement = $database->prepare(<<<'SQL'
            SELECT applications.*, grades.public_id AS grade_public_id, grades.name AS grade_name,
                   grades.abbreviation AS grade_abbreviation
            FROM membership_applications AS applications
            LEFT JOIN membership_grades AS grades ON grades.id = applications.membership_grade_id
            WHERE applications.public_id = :public_id AND applications.user_id = :user_id
            LIMIT 1
            SQL);
        $statement->execute(['public_id' => $publicId, 'user_id' => $userId]);
        $application = $statement->fetch();

        return is_array($application) ? $this->hydrate($application, $database) : null;
    }

    /** @param array<string, mixed> $application
     *  @return array<string, mixed>
     */
    private function hydrate(array $application, ?PDO $database = null): array
    {
        $database ??= $this->connection();
        foreach (['personal_information', 'contact_information', 'professional_details', 'education', 'employment'] as $field) {
            $application[$field] = $this->decode($application[$field] ?? null);
        }

        $documents = $database->prepare(<<<'SQL'
            SELECT public_id, document_type, original_name, mime_type, size_bytes, created_at
            FROM membership_application_documents
            WHERE membership_application_id = :application_id AND deleted_at IS NULL
            ORDER BY created_at, id
            SQL);
        $documents->execute(['application_id' => (int) $application['id']]);
        $application['documents'] = $documents->fetchAll();

        $history = $database->prepare(<<<'SQL'
            SELECT from_status, to_status, note, created_at
            FROM membership_history
            WHERE membership_application_id = :application_id
            ORDER BY created_at, id
            SQL);
        $history->execute(['application_id' => (int) $application['id']]);
        $application['history'] = $history->fetchAll();

        $messages = $database->prepare(<<<'SQL'
            SELECT comment_type, body, created_at
            FROM membership_review_comments
            WHERE membership_application_id = :application_id AND visible_to_applicant = 1
            ORDER BY created_at, id
            SQL);
        $messages->execute(['application_id' => (int) $application['id']]);
        $application['applicant_messages'] = $messages->fetchAll();
        unset($application['id'], $application['user_id'], $application['membership_grade_id']);

        return $application;
    }

    /** @param array<string, mixed> $sections
     *  @return array<string, string|null>
     */
    private function sectionParameters(array $sections): array
    {
        return [
            'personal' => $this->json((array) ($sections['personal_information'] ?? [])),
            'contact' => $this->json((array) ($sections['contact_information'] ?? [])),
            'professional' => $this->json((array) ($sections['professional_details'] ?? [])),
            'education' => $this->json((array) ($sections['education'] ?? [])),
            'employment' => $this->json((array) ($sections['employment'] ?? [])),
        ];
    }

    private function history(PDO $database, int $applicationId, ?string $from, string $to, string $note, int $actor, DateTimeImmutable $now): void
    {
        $statement = $database->prepare(<<<'SQL'
            INSERT INTO membership_history (membership_application_id, from_status, to_status, note, changed_by_user_id, created_at)
            VALUES (:application_id, :from_status, :to_status, :note, :actor, :created_at)
            SQL);
        $statement->execute([
            'application_id' => $applicationId,
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note,
            'actor' => $actor,
            'created_at' => $this->date($now),
        ]);
    }

    /** @param array<string, mixed>|null $values */
    private function audit(PDO $database, int $userId, string $action, string $applicationPublicId, ?array $values, DateTimeImmutable $now): void
    {
        $statement = $database->prepare(<<<'SQL'
            INSERT INTO audit_logs (
                actor_user_id, action, auditable_type, auditable_id, description, new_values, request_id, created_at
            ) VALUES (
                :actor, :action, 'membership_application', :auditable_id,
                'A membership application workflow action was completed.', :new_values, :request_id, :created_at
            )
            SQL);
        $statement->execute([
            'actor' => $userId,
            'action' => $action,
            'auditable_id' => $applicationPublicId,
            'new_values' => $values === null ? null : $this->json($values),
            'request_id' => bin2hex(random_bytes(16)),
            'created_at' => $this->date($now),
        ]);
    }

    /** @param array<string, mixed> $value
     *  @throws JsonException
     */
    private function json(array $value): ?string
    {
        return $value === [] ? null : json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    /** @return array<string, mixed> */
    private function decode(mixed $value): array
    {
        if (!is_string($value) || $value === '') {
            return [];
        }
        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function date(DateTimeImmutable $date): string
    {
        return $date->format('Y-m-d H:i:s.u');
    }
}
