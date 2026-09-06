<?php

declare(strict_types=1);

use App\Authorization\PermissionCheckerInterface;
use App\Http\Request;
use App\Http\Response;
use App\Middleware\AuthorizeMiddleware;

require dirname(__DIR__) . '/vendor/autoload.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

try {
    $routeSource = (string) file_get_contents(dirname(__DIR__) . '/routes/web.php');
    $bootstrapSource = (string) file_get_contents(dirname(__DIR__) . '/bootstrap/app.php');

    foreach (preg_split('/\R/', $routeSource) ?: [] as $line) {
        if (!str_contains($line, "'/admin/")) {
            continue;
        }

        $assert(str_contains($line, '$authenticate'), 'An administrative route is missing authentication middleware: ' . trim($line));
    }

    $assert(str_contains($bootstrapSource, "new CsrfMiddleware(\$csrf, ['/payments/webhook'])"), 'The global CSRF middleware or its narrow webhook exception changed unexpectedly.');
    $assert(!str_contains($bootstrapSource, "'/admin/"), 'An administrative CSRF bypass was introduced.');

    $called = false;
    $deniedChecker = new class implements PermissionCheckerInterface {
        public function allows(int $userId, string $permission): bool
        {
            return false;
        }
    };
    $request = new Request('POST', '/admin/programme-applications/example/approve', headers: ['accept' => 'application/json']);
    $request->setAttribute('auth.user', ['id' => 41]);
    $response = (new AuthorizeMiddleware($deniedChecker, 'programme.application_approve'))->handle(
        $request,
        static function (Request $request) use (&$called): Response {
            $called = true;
            return Response::json(['ok' => true]);
        },
    );
    $assert($response->status() === 403 && !$called, 'Direct endpoint access was not stopped before the protected handler.');
    $assert(($response->headers()['Cache-Control'] ?? '') === 'no-store, private', 'Authorization denial may be cached.');

    $programmeService = (string) file_get_contents(dirname(__DIR__) . '/app/Services/ProgrammeApplications/ProgrammeApplicationService.php');
    foreach (['programme.application_view', 'programme.application_review', 'programme.application_approve', 'programme.application_reject', 'programme.enrol'] as $permission) {
        $assert(str_contains($programmeService, $permission), "Programme application service is missing {$permission} enforcement.");
    }
    $assert(!str_contains($programmeService, 'programme.manage_applications'), 'The broad legacy programme application authority remains active in the service.');

    $eventRepository = (string) file_get_contents(dirname(__DIR__) . '/app/Repositories/EventRepository.php');
    $assert(substr_count($eventRepository, 'events.public_id=:event') >= 2, 'Nested event attendance mutations are not bound to the parent event.');
    $assert(str_contains($eventRepository, '\'event_public_id\'=>$eventPublicId'), 'Nested event audit records do not retain parent context.');

    $ownershipSources = [
        'MembershipRepository.php' => 'applications.user_id = :user_id',
        'MemberPortalRepository.php' => 'members.user_id = :user_id',
        'ProgrammeApplicationRepository.php' => 'user_id=:user',
        'PaymentRepository.php' => 'WHERE user_id=:user',
        'CertificateRepository.php' => 'members.user_id=:user',
        'MembershipRenewalRepository.php' => 'members.user_id=:user',
    ];
    foreach ($ownershipSources as $file => $needle) {
        $source = (string) file_get_contents(dirname(__DIR__) . '/app/Repositories/' . $file);
        $assert(str_contains($source, $needle), "{$file} is missing its current-user ownership predicate.");
    }

    $seedSource = (string) file_get_contents(dirname(__DIR__) . '/database/seeders/20260904_161000_rbac_seeder.php');
    $permissionChecker = (string) file_get_contents(dirname(__DIR__) . '/app/Authorization/PdoPermissionChecker.php');
    $assert(str_contains($seedSource, "roles.slug = 'super-administrator'") && str_contains($seedSource, 'CROSS JOIN permissions'), 'Super Administrator permissions are not explicitly materialized.');
    $assert(str_contains($seedSource, "'programme-reviewer' => ['programme.view', 'programme.application_view', 'programme.application_review']"), 'The Programme Reviewer role is not constrained to view and review authority.');
    $assert(!str_contains($permissionChecker, 'super-administrator'), 'A hidden Super Administrator bypass exists outside normal permission assignments.');

    foreach (['MembershipAdminRepository.php', 'ProgrammeApplicationRepository.php', 'EventRepository.php', 'MembershipRenewalRepository.php', 'CertificateRepository.php', 'CmsRepository.php', 'PaymentRepository.php'] as $file) {
        $source = (string) file_get_contents(dirname(__DIR__) . '/app/Repositories/' . $file);
        $assert(str_contains($source, 'audit'), "{$file} is missing an audit hook for privileged state changes.");
    }

    echo "RBAC security audit checks passed: authentication, permission middleware, CSRF coverage, direct-access denial, granular programme authority, nested-ID binding, object ownership, explicit Super Administrator grants, and audit hooks.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'RBAC security audit check failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
