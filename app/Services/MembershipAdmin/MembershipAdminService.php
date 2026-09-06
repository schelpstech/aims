<?php

declare(strict_types=1);

namespace App\Services\MembershipAdmin;

use App\Authorization\PermissionCheckerInterface;
use App\Services\Membership\DocumentStorageInterface;
use DateTimeImmutable;
use DomainException;
use Throwable;

final class MembershipAdminService
{
    private const STATUSES = ['submitted', 'under_review', 'query_raised', 'approved', 'rejected', 'cancelled'];

    public function __construct(
        private readonly MembershipAdminRepositoryInterface $repository,
        private readonly PermissionCheckerInterface $permissions,
        private readonly DocumentStorageInterface $documents,
        private readonly string $membershipNumberPrefix = 'AIMS',
    ) {
    }

    /** @param array<string, mixed> $filters */
    public function applications(int $actorUserId, array $filters): AdminActionResult
    {
        if (!$this->permissions->allows($actorUserId, 'member.view')) {
            return AdminActionResult::failure(403, 'You are not authorized to view membership applications.');
        }
        $status = is_string($filters['status'] ?? null) ? trim($filters['status']) : '';
        $grade = is_string($filters['grade'] ?? null) ? trim($filters['grade']) : '';
        $search = is_string($filters['search'] ?? null) ? trim($filters['search']) : '';
        if ($status !== '' && !in_array($status, self::STATUSES, true)) {
            return AdminActionResult::failure(422, 'The selected application status is invalid.');
        }
        if ($grade !== '' && !$this->validUuid($grade)) {
            return AdminActionResult::failure(422, 'The selected membership grade is invalid.');
        }
        if (mb_strlen($search) > 100) {
            return AdminActionResult::failure(422, 'Search text must not exceed 100 characters.');
        }

        return AdminActionResult::success('Applications loaded.', $this->repository->search([
            'status' => $status,
            'grade' => $grade,
            'search' => $search,
            'page' => max(1, (int) ($filters['page'] ?? 1)),
        ]));
    }

    public function application(int $actorUserId, string $publicId): AdminActionResult
    {
        if (!$this->permissions->allows($actorUserId, 'member.view')) {
            return AdminActionResult::failure(403, 'You are not authorized to view membership applications.');
        }
        if (!$this->validUuid($publicId)) {
            return AdminActionResult::failure(404, 'Application not found.');
        }
        $application = $this->repository->application($publicId);

        return $application === null
            ? AdminActionResult::failure(404, 'Application not found.')
            : AdminActionResult::success('Application loaded.', $application);
    }

    public function document(int $actorUserId, string $publicId): AdminActionResult
    {
        if (!$this->permissions->allows($actorUserId, 'member.view')) {
            return AdminActionResult::failure(403, 'You are not authorized to view supporting documents.');
        }
        if (!$this->validUuid($publicId)) {
            return AdminActionResult::failure(404, 'Document not found.');
        }
        $document = $this->repository->document($publicId);
        if ($document === null) {
            return AdminActionResult::failure(404, 'Document not found.');
        }
        try {
            $document['contents'] = $this->documents->read((string) $document['storage_path']);
            unset($document['storage_path']);

            return AdminActionResult::success('Document loaded.', $document);
        } catch (Throwable) {
            return AdminActionResult::failure(404, 'Document not found.');
        }
    }

    public function review(int $actorUserId, string $publicId, string $comment): AdminActionResult
    {
        return $this->commentAction($actorUserId, 'member.review', $publicId, $comment, 'review');
    }

    public function query(int $actorUserId, string $publicId, string $query): AdminActionResult
    {
        return $this->commentAction($actorUserId, 'member.query', $publicId, $query, 'query');
    }

    public function reject(int $actorUserId, string $publicId, string $reason): AdminActionResult
    {
        return $this->commentAction($actorUserId, 'member.reject', $publicId, $reason, 'reject');
    }

    public function approve(int $actorUserId, string $publicId, ?string $comment): AdminActionResult
    {
        if (!$this->permissions->allows($actorUserId, 'member.approve')) {
            return AdminActionResult::failure(403, 'You are not authorized to approve membership applications.');
        }
        if (!$this->validUuid($publicId)) {
            return AdminActionResult::failure(404, 'Application not found.');
        }
        $comment = is_string($comment) ? trim($comment) : null;
        if ($comment === '') {
            $comment = null;
        }
        if ($comment !== null && mb_strlen($comment) > 5000) {
            return AdminActionResult::failure(422, 'Approval comments must not exceed 5000 characters.');
        }
        $prefix = strtoupper(trim($this->membershipNumberPrefix));
        if (preg_match('/^[A-Z0-9-]{2,20}$/D', $prefix) !== 1) {
            return AdminActionResult::failure(500, 'Membership number configuration is invalid.');
        }

        try {
            $result = $this->repository->approve($actorUserId, $publicId, $comment, $prefix, new DateTimeImmutable('now'));
            $message = !empty($result['idempotent'])
                ? 'This application was already approved; the existing membership was returned.'
                : 'The application was approved and membership activated.';

            return AdminActionResult::success($message, $result);
        } catch (DomainException $exception) {
            return AdminActionResult::failure(409, $exception->getMessage());
        }
    }

    private function commentAction(int $actor, string $permission, string $publicId, string $comment, string $action): AdminActionResult
    {
        if (!$this->permissions->allows($actor, $permission)) {
            return AdminActionResult::failure(403, 'You are not authorized to perform this membership action.');
        }
        $comment = trim($comment);
        if (!$this->validUuid($publicId)) {
            return AdminActionResult::failure(404, 'Application not found.');
        }
        if (mb_strlen($comment) < 3 || mb_strlen($comment) > 5000) {
            return AdminActionResult::failure(422, 'A comment between 3 and 5000 characters is required.');
        }

        try {
            $data = match ($action) {
                'review' => $this->repository->addReviewComment($actor, $publicId, $comment, new DateTimeImmutable('now')),
                'query' => $this->repository->raiseQuery($actor, $publicId, $comment, new DateTimeImmutable('now')),
                'reject' => $this->repository->reject($actor, $publicId, $comment, new DateTimeImmutable('now')),
            };

            return AdminActionResult::success(match ($action) {
                'review' => 'The review comment was recorded.',
                'query' => 'The query was recorded for the applicant.',
                'reject' => 'The application was rejected and retained with its history.',
            }, $data);
        } catch (DomainException $exception) {
            return AdminActionResult::failure(409, $exception->getMessage());
        }
    }

    private function validUuid(string $value): bool
    {
        return preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/Di', $value) === 1;
    }
}
