<?php

declare(strict_types=1);

use App\Config\Config;
use App\Config\Environment;
use App\Controllers\Membership\MembershipApplicationController;
use App\Http\Request;
use App\Security\Csrf;
use App\Security\SessionManager;
use App\Services\Membership\DocumentStorageInterface;
use App\Services\Membership\MembershipRepositoryInterface;
use App\Services\Membership\MembershipService;
use App\Services\PublicSite\PublicContent;
use App\Validation\Validator;
use App\View\View;

require dirname(__DIR__) . '/vendor/autoload.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$temporaryDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'aims-membership-test-' . bin2hex(random_bytes(6));
$session = null;

try {
    mkdir($temporaryDirectory, 0700, true);
    $repository = new class implements MembershipRepositoryInterface {
        public ?array $application = null;
        public int $memberInsertions = 0;

        public function activeGrades(): array
        {
            return [[
                'public_id' => '5c168e52-935b-4816-8e02-4faf89ebebaf',
                'name' => 'Fellow',
                'abbreviation' => null,
                'description' => null,
                'eligibility' => null,
                'benefits' => null,
                'application_fee' => null,
                'annual_fee' => null,
                'fee_currency' => null,
                'display_order' => 10,
            ]];
        }

        public function currentApplication(int $userId): ?array
        {
            return $this->application;
        }

        public function saveDraft(int $userId, ?string $applicationPublicId, ?string $gradePublicId, array $sections, DateTimeImmutable $now): array
        {
            if ($applicationPublicId !== null && $this->application !== null && $applicationPublicId !== $this->application['public_id']) {
                throw new DomainException('This application cannot be edited.');
            }
            $this->application = ($this->application ?? [
                'public_id' => 'c72c8c04-c41d-4294-a881-2b6400cb768c',
                'status' => 'draft',
                'documents' => [],
                'history' => [['from_status' => null, 'to_status' => 'draft', 'note' => 'Application draft created.', 'created_at' => $now->format('c')]],
            ]) + [];
            $this->application['grade_public_id'] = $gradePublicId;
            $this->application['grade_name'] = $gradePublicId === null ? null : 'Fellow';
            foreach ($sections as $key => $value) {
                $this->application[$key] = $value;
            }

            return $this->application;
        }

        public function applicationForUser(int $userId, string $applicationPublicId): ?array
        {
            return $this->application !== null && $this->application['public_id'] === $applicationPublicId
                ? $this->application
                : null;
        }

        public function addDocument(int $userId, string $applicationPublicId, array $document, DateTimeImmutable $now): array
        {
            if ($this->application === null || $this->application['status'] !== 'draft') {
                throw new DomainException('This application cannot accept documents.');
            }
            $stored = ['public_id' => '89835570-4af8-41a9-a013-1bc47ae15357'] + $document;
            $this->application['documents'][] = $stored;

            return $stored;
        }

        public function submit(int $userId, string $applicationPublicId, string $reference, string $declarationName, DateTimeImmutable $now): ?array
        {
            if ($this->application === null || $this->application['status'] !== 'draft' || $this->application['documents'] === []) {
                return null;
            }
            $this->application['status'] = 'submitted';
            $this->application['application_reference'] = $reference;
            $this->application['declaration_name'] = $declarationName;
            $this->application['declaration_accepted_at'] = $now->format('c');
            $this->application['history'][] = ['from_status' => 'draft', 'to_status' => 'submitted', 'note' => 'Application submitted by applicant.', 'created_at' => $now->format('c')];

            return $this->application;
        }

        public function cancel(int $userId, string $applicationPublicId, DateTimeImmutable $now): ?array
        {
            if ($this->application === null || !in_array($this->application['status'], ['draft', 'query_raised'], true)) {
                return null;
            }
            $this->application['status'] = 'cancelled';
            $this->application['history'][] = ['from_status' => 'draft', 'to_status' => 'cancelled', 'note' => 'Application cancelled by applicant.', 'created_at' => $now->format('c')];

            return $this->application;
        }
    };

    $storage = new class implements DocumentStorageInterface {
        public array $deleted = [];

        public function store(array $file): array
        {
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Select a document to upload.');
            }

            return [
                'original_name' => 'qualification.pdf',
                'storage_path' => '2026/09/' . str_repeat('a', 48) . '.pdf',
                'mime_type' => 'application/pdf',
                'size_bytes' => 1024,
                'sha256' => str_repeat('b', 64),
            ];
        }

        public function delete(string $relativePath): void
        {
            $this->deleted[] = $relativePath;
        }

        public function read(string $relativePath): string
        {
            return 'test-document';
        }
    };

    $service = new MembershipService($repository, $storage, new Validator());
    $assert($service->grades()[0]['abbreviation'] === null, 'A membership abbreviation was assumed.');

    $invalid = $service->saveDraft(9, ['contact_email' => 'not-an-email']);
    $assert(!$invalid->successful && isset($invalid->errors['contact_email']), 'Invalid draft contact data was accepted.');

    $partial = $service->saveDraft(9, ['first_name' => 'Ada']);
    $assert($partial->successful && $partial->application['status'] === 'draft', 'An incomplete draft could not be saved.');
    $applicationId = $partial->application['public_id'];

    $complete = $service->saveDraft(9, [
        'application_public_id' => $applicationId,
        'membership_grade' => '5c168e52-935b-4816-8e02-4faf89ebebaf',
        'first_name' => 'Ada',
        'last_name' => 'Okafor',
        'phone' => '+2348000000000',
        'contact_email' => 'ada@example.test',
        'address' => 'A verified contact address',
        'country' => 'Nigeria',
        'professional_area' => 'Finance',
        'current_role' => 'Analyst',
        'years_experience' => '8',
        'education_summary' => 'Verified education information',
        'employment_summary' => 'Verified employment information',
    ]);
    $assert($complete->successful, 'A valid complete draft was rejected: ' . json_encode($complete->errors));

    $withoutDocument = $service->submit(9, [
        'application_public_id' => $applicationId,
        'declaration_name' => 'Ada Okafor',
        'declaration_accepted' => '1',
    ]);
    $assert(!$withoutDocument->successful && isset($withoutDocument->errors['documents']), 'Submission did not require a supporting document.');

    $badType = $service->uploadDocument(9, $applicationId, 'executable', ['error' => UPLOAD_ERR_OK]);
    $assert(!$badType->successful, 'An unsupported document category was accepted.');
    $uploaded = $service->uploadDocument(9, $applicationId, 'qualification', ['error' => UPLOAD_ERR_OK]);
    $assert($uploaded->successful, 'A valid supporting document was not recorded.');

    $submitted = $service->submit(9, [
        'application_public_id' => $applicationId,
        'declaration_name' => 'Ada Okafor',
        'declaration_accepted' => '1',
    ]);
    $assert($submitted->successful, 'A complete application could not be submitted.');
    $assert($submitted->application['status'] === 'submitted', 'Submission did not set the correct status.');
    $assert(preg_match('/^AIMS-[0-9]{4}-[A-F0-9]{16}$/D', $submitted->application['application_reference']) === 1, 'The application reference is malformed.');
    $assert($repository->memberInsertions === 0, 'Submission created active membership.');
    $assert(!$service->cancel(9, $applicationId)->successful, 'A submitted application could be cancelled by the applicant.');

    $session = new SessionManager([
        'name' => 'aims_membership_test_' . bin2hex(random_bytes(4)),
        'lifetime' => 120,
        'save_path' => $temporaryDirectory . DIRECTORY_SEPARATOR . 'sessions',
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'http_only' => true,
        'same_site' => 'Lax',
    ]);
    $environment = Environment::load($temporaryDirectory . DIRECTORY_SEPARATOR . '.env.missing');
    $config = Config::load(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config', $environment);
    $controller = new MembershipApplicationController(
        new View(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views'),
        new Csrf($session),
        $service,
        $session,
        new PublicContent($config),
    );
    $request = new Request('GET', '/account/membership-application');
    $request->setAttribute('auth.user', ['id' => 9, 'email' => 'ada@example.test']);
    $response = $controller->show($request);
    $assert($response->status() === 200, 'The membership application page did not render.');
    $assert(str_contains($response->body(), 'AIMS-' . date('Y')), 'The submitted application reference was not rendered.');
    $assert(str_contains($response->body(), 'does not create or activate membership') || str_contains($response->body(), 'requires a separate authorized approval'), 'The approval separation warning is missing.');
    $assert(($response->headers()['Cache-Control'] ?? '') === 'no-store, private', 'The membership page may be cached.');

    $migration = (string) file_get_contents(dirname(__DIR__) . '/database/migrations/20260904_190000_create_membership_foundation_tables.php');
    foreach (['membership_grades', 'members', 'membership_applications', 'membership_application_documents', 'membership_history', 'ENGINE=InnoDB'] as $table) {
        $assert(str_contains($migration, $table), 'The membership migration is missing ' . $table . '.');
    }
    $assert(str_contains($migration, "'query_raised'"), 'The application workflow is incomplete.');
    $assert(str_contains($migration, "DEFAULT 'pending_activation'"), 'A future member record defaults directly to active.');
    $seeder = (string) file_get_contents(dirname(__DIR__) . '/database/seeders/20260904_190000_membership_grades_seeder.php');
    $assert(!str_contains($seeder, 'FIICA') && !str_contains($seeder, 'F.AIMS'), 'The grade seeder hard-codes a disputed abbreviation.');

    $session->invalidate();
    $session = null;
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temporaryDirectory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($temporaryDirectory);

    echo "Membership foundation checks passed: drafts, validation, secure document metadata, submission, history, references, and approval separation.\n";
} catch (Throwable $exception) {
    if ($session instanceof SessionManager && session_status() === PHP_SESSION_ACTIVE) {
        $session->invalidate();
    }
    fwrite(STDERR, 'Membership foundation check failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
