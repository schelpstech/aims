<?php

declare(strict_types=1);

use App\Authorization\PermissionCheckerInterface;
use App\Http\Request;
use App\Http\Response;
use App\Middleware\AuthorizeMiddleware;
use App\Services\Membership\DocumentStorageInterface;
use App\Services\MembershipAdmin\MembershipAdminRepositoryInterface;
use App\Services\MembershipAdmin\MembershipAdminService;

require dirname(__DIR__) . '/vendor/autoload.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

try {
    $applicationId = 'c72c8c04-c41d-4294-a881-2b6400cb768c';
    $documentId = '89835570-4af8-41a9-a013-1bc47ae15357';
    $repository = new class($applicationId, $documentId) implements MembershipAdminRepositoryInterface {
        public array $applicationData;
        public ?array $member = null;
        public int $memberCreations = 0;
        public int $approvalCalls = 0;

        public function __construct(private readonly string $applicationId, private readonly string $documentId)
        {
            $this->reset();
        }

        public function reset(): void
        {
            $this->applicationData = [
                'public_id' => $this->applicationId,
                'application_reference' => 'AIMS-2026-TESTREFERENCE',
                'status' => 'submitted',
                'account_email' => 'applicant@example.test',
                'grade_name' => 'Member',
                'personal_information' => ['first_name' => 'Ada', 'last_name' => 'Okafor'],
                'contact_information' => ['contact_email' => 'applicant@example.test'],
                'professional_details' => ['current_role' => 'Analyst'],
                'education' => ['summary' => 'Education'],
                'employment' => ['summary' => 'Employment'],
                'documents' => [['public_id' => $this->documentId, 'original_name' => 'record.pdf', 'document_type' => 'qualification']],
                'comments' => [],
                'history' => [['from_status' => 'draft', 'to_status' => 'submitted', 'note' => 'Submitted']],
            ];
            $this->member = null;
        }

        public function search(array $filters): array
        {
            return ['items' => [[
                'public_id' => $this->applicationId,
                'application_reference' => 'AIMS-2026-TESTREFERENCE',
                'status' => $this->applicationData['status'],
                'first_name' => 'Ada',
                'last_name' => 'Okafor',
                'email' => 'applicant@example.test',
                'grade_name' => 'Member',
                'submitted_at' => '2026-09-04 12:00:00',
            ]], 'total' => 1, 'page' => 1, 'pages' => 1];
        }

        public function application(string $applicationPublicId): ?array
        {
            return $applicationPublicId === $this->applicationId ? $this->applicationData : null;
        }

        public function document(string $documentPublicId): ?array
        {
            return $documentPublicId === $this->documentId ? [
                'public_id' => $this->documentId,
                'storage_path' => '2026/09/' . str_repeat('a', 48) . '.pdf',
                'mime_type' => 'application/pdf',
                'original_name' => 'record.pdf',
                'size_bytes' => 12,
            ] : null;
        }

        public function addReviewComment(int $actorUserId, string $applicationPublicId, string $comment, DateTimeImmutable $now): array
        {
            if ($this->applicationData['status'] === 'submitted') {
                $this->applicationData['status'] = 'under_review';
                $this->applicationData['history'][] = ['from_status' => 'submitted', 'to_status' => 'under_review', 'note' => 'Review started'];
            }
            $this->applicationData['comments'][] = ['comment_type' => 'review', 'body' => $comment, 'visible_to_applicant' => 0];

            return $this->applicationData;
        }

        public function raiseQuery(int $actorUserId, string $applicationPublicId, string $query, DateTimeImmutable $now): array
        {
            $from = $this->applicationData['status'];
            $this->applicationData['status'] = 'query_raised';
            $this->applicationData['history'][] = ['from_status' => $from, 'to_status' => 'query_raised', 'note' => 'Query raised'];
            $this->applicationData['comments'][] = ['comment_type' => 'query', 'body' => $query, 'visible_to_applicant' => 1];

            return $this->applicationData;
        }

        public function reject(int $actorUserId, string $applicationPublicId, string $reason, DateTimeImmutable $now): array
        {
            $from = $this->applicationData['status'];
            $this->applicationData['status'] = 'rejected';
            $this->applicationData['history'][] = ['from_status' => $from, 'to_status' => 'rejected', 'note' => 'Rejected'];
            $this->applicationData['comments'][] = ['comment_type' => 'rejection', 'body' => $reason, 'visible_to_applicant' => 1];

            return $this->applicationData;
        }

        public function approve(int $actorUserId, string $applicationPublicId, ?string $comment, string $numberPrefix, DateTimeImmutable $now): array
        {
            $this->approvalCalls++;
            if ($this->member !== null) {
                return ['idempotent' => true, 'member' => $this->member, 'application' => $this->applicationData];
            }
            $this->memberCreations++;
            $this->member = ['public_id' => 'ce4fcf56-2519-4c58-87f0-f063e33f5b72', 'membership_number' => $numberPrefix . '-2026-ABC123456789', 'status' => 'active'];
            $this->applicationData['status'] = 'approved';
            $this->applicationData['membership_number'] = $this->member['membership_number'];
            $this->applicationData['history'][] = ['from_status' => 'submitted', 'to_status' => 'approved', 'note' => 'Approved'];

            return ['idempotent' => false, 'member' => $this->member, 'application' => $this->applicationData];
        }
    };

    $permissions = new class implements PermissionCheckerInterface {
        public array $grants = [
            10 => ['member.view', 'member.review', 'member.query', 'member.approve', 'member.reject'],
            11 => ['member.view'],
        ];

        public function allows(int $userId, string $permission): bool
        {
            return in_array($permission, $this->grants[$userId] ?? [], true);
        }
    };
    $storage = new class implements DocumentStorageInterface {
        public function store(array $file): array { throw new RuntimeException('Not used.'); }
        public function delete(string $relativePath): void {}
        public function read(string $relativePath): string { return '%PDF-test'; }
    };
    $service = new MembershipAdminService($repository, $permissions, $storage, 'AIMS');

    $unauthorized = $service->approve(11, $applicationId, null);
    $assert(!$unauthorized->successful && $unauthorized->status === 403, 'Approval service accepted a user without member.approve.');
    $assert($repository->approvalCalls === 0, 'Unauthorized approval reached the repository.');

    $deniedRequest = new Request('POST', '/admin/membership/applications/' . $applicationId . '/approve');
    $deniedRequest->setAttribute('auth.user', ['id' => 11]);
    $middleware = new AuthorizeMiddleware($permissions, 'member.approve');
    $deniedResponse = $middleware->handle($deniedRequest, static fn (): Response => Response::html('approved'));
    $assert($deniedResponse->status() === 403, 'Authorization middleware did not deny an unauthorized user.');
    $allowedRequest = new Request('POST', '/admin/membership/applications/' . $applicationId . '/approve');
    $allowedRequest->setAttribute('auth.user', ['id' => 10]);
    $allowedResponse = $middleware->handle($allowedRequest, static fn (): Response => Response::html('approved'));
    $assert($allowedResponse->status() === 200, 'Authorization middleware denied an authorized user.');

    $firstApproval = $service->approve(10, $applicationId, 'Reviewed and verified.');
    $secondApproval = $service->approve(10, $applicationId, 'Repeated request.');
    $assert($firstApproval->successful && $secondApproval->successful, 'Authorized approval failed.');
    $assert($repository->memberCreations === 1, 'Duplicate approval created more than one member.');
    $assert($secondApproval->data['idempotent'] === true, 'Duplicate approval was not reported idempotently.');
    $assert($firstApproval->data['member']['membership_number'] === $secondApproval->data['member']['membership_number'], 'Duplicate approval changed the membership number.');

    $repository->reset();
    $review = $service->review(10, $applicationId, 'Credentials reviewed.');
    $assert($review->successful && $repository->applicationData['status'] === 'under_review', 'Review did not record progress.');
    $query = $service->query(10, $applicationId, 'Please clarify the employment date.');
    $assert($query->successful && $repository->applicationData['status'] === 'query_raised', 'Query was not raised.');
    $historyBeforeReject = count($repository->applicationData['history']);
    $rejection = $service->reject(10, $applicationId, 'The supplied evidence does not meet the verified criteria.');
    $assert($rejection->successful && $repository->applicationData['status'] === 'rejected', 'Rejection failed.');
    $assert(count($repository->applicationData['history']) === $historyBeforeReject + 1, 'Rejection did not retain and append history.');
    $assert($repository->application($applicationId) !== null, 'Rejection deleted the submitted application.');

    $list = $service->applications(10, ['search' => 'Ada', 'status' => 'rejected']);
    $assert($list->successful && $list->data['total'] === 1, 'Authorized filtered application listing failed.');
    $document = $service->document(10, $documentId);
    $assert($document->successful && $document->data['contents'] === '%PDF-test', 'Authorized secure document retrieval failed.');

    $seeder = (string) file_get_contents(dirname(__DIR__) . '/database/seeders/20260904_161000_rbac_seeder.php');
    foreach (['member.view', 'member.review', 'member.approve', 'member.reject', 'member.query'] as $permission) {
        $assert(str_contains($seeder, "'{$permission}'"), 'Missing permission ' . $permission . '.');
    }
    $adminRepository = (string) file_get_contents(dirname(__DIR__) . '/app/Repositories/MembershipAdminRepository.php');
    $assert(str_contains($adminRepository, 'FOR UPDATE'), 'Approval and review actions do not lock application state.');
    $assert(str_contains($adminRepository, "'idempotent' => true"), 'Duplicate approval handling is missing.');
    $assert(str_contains($adminRepository, "'active'"), 'Approval does not activate a member record.');
    $routes = (string) file_get_contents(dirname(__DIR__) . '/routes/web.php');
    $assert(str_contains($routes, '/admin/membership/applications'), 'Membership administration routes are missing.');

    echo "Membership administration checks passed: RBAC denial, filters, review, query, rejection retention, secure documents, approval, and idempotency.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'Membership administration check failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
