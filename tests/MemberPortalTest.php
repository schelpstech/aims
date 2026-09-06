<?php

declare(strict_types=1);

use App\Config\Config;
use App\Config\Environment;
use App\Controllers\MemberPortal\MemberPortalController;
use App\Http\Request;
use App\Http\Response;
use App\Middleware\MemberAccessMiddleware;
use App\Security\Csrf;
use App\Security\SessionManager;
use App\Services\MemberPortal\MemberActivitySummaryInterface;
use App\Services\MemberPortal\MemberPortalRepositoryInterface;
use App\Services\MemberPortal\MemberPortalService;
use App\Services\PublicSite\PublicContent;
use App\Validation\Validator;
use App\View\View;

require dirname(__DIR__) . '/vendor/autoload.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$temporaryDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'aims-member-portal-test-' . bin2hex(random_bytes(6));
$session = null;

try {
    mkdir($temporaryDirectory, 0700, true);
    $repository = new class implements MemberPortalRepositoryInterface {
        public array $requestedUsers = [];
        public int $profileUpdates = 0;
        public array $profiles = [];

        public function memberForUser(int $userId): ?array
        {
            $this->requestedUsers[] = $userId;
            $records = [
                21 => [
                    'member_id' => 101,
                    'public_id' => '934e23c9-f8fb-45ff-815b-bdd7790c67fa',
                    'membership_number' => 'AIMS-2026-OWNMEMBER001',
                    'status' => 'active',
                    'joined_at' => '2026-09-01',
                    'expires_at' => null,
                    'membership_grade' => 'Member',
                    'grade_abbreviation' => null,
                    'account_email' => 'own@example.test',
                    'first_name' => 'Own',
                    'last_name' => 'Member',
                    'preferred_name' => $this->profiles[21]['preferred_name'] ?? null,
                    'phone' => $this->profiles[21]['phone'] ?? null,
                ],
                22 => [
                    'member_id' => 202,
                    'public_id' => 'aa0761e0-d85d-401f-aa05-fe672a571a8c',
                    'membership_number' => 'AIMS-2026-OTHERMEMBER2',
                    'status' => 'active',
                    'joined_at' => '2025-01-10',
                    'expires_at' => '2099-12-31',
                    'membership_grade' => 'Fellow',
                    'grade_abbreviation' => null,
                    'account_email' => 'other@example.test',
                    'first_name' => 'Other',
                    'last_name' => 'Member',
                ],
            ];

            return $records[$userId] ?? null;
        }

        public function updateProfileForUser(int $userId, array $profile, DateTimeImmutable $now): ?array
        {
            if ($userId !== 21) {
                return null;
            }
            $this->profileUpdates++;
            $this->profiles[$userId] = $profile;

            return $this->memberForUser($userId) + $profile;
        }

        public function recentNotificationsForUser(int $userId, int $limit): array
        {
            return $userId === 21 ? [[
                'public_id' => '8c270d02-e630-4464-991a-1a99d062f841',
                'title' => 'Membership activated',
                'body' => 'Your membership is active.',
                'action_url' => 'https://attacker.example/steal',
                'created_at' => '2026-09-04 12:00:00',
            ]] : [];
        }
    };
    $activities = new class implements MemberActivitySummaryInterface {
        public array $memberIds = [];
        public function counts(int $memberId): array
        {
            $this->memberIds[] = $memberId;
            return ['certificates' => 2, 'programme_enrolments' => 3, 'event_registrations' => 4];
        }
    };
    $service = new MemberPortalService($repository, $activities, new Validator());

    $own = $service->dashboard(21);
    $assert($own->successful, 'An active member could not open the portal.');
    $assert($own->data['member']['name'] === 'Own Member', 'The member name was not assembled correctly.');
    $assert($own->data['member']['membership_number'] === 'AIMS-2026-OWNMEMBER001', 'The member received another member record.');
    $assert(!array_key_exists('member_id', $own->data['member']), 'The internal member identifier was exposed.');
    $assert($own->data['counts'] === ['certificates' => 2, 'programme_enrolments' => 3, 'event_registrations' => 4], 'Member activity hooks returned incorrect counts.');
    $assert($activities->memberIds === [101], 'Activity counts used another member identifier.');
    $assert($own->data['notifications'][0]['action_url'] === null, 'An external notification URL was published.');
    $assert(!$service->dashboard(23)->successful, 'A non-member received portal access.');

    $sensitive = $service->updateProfile(21, ['membership_number' => 'CHANGED']);
    $assert(!$sensitive->successful && $sensitive->status === 403, 'A sensitive membership field was accepted.');
    $assert($repository->profileUpdates === 0, 'Sensitive profile input reached persistence.');
    $updated = $service->updateProfile(21, [
        'preferred_name' => 'O. Member',
        'phone' => '+2348000000000',
        'alternate_email' => 'member@example.test',
        'professional_area' => 'Operations',
        'current_role' => 'Manager',
    ]);
    $assert($updated->successful && $repository->profileUpdates === 1, 'Permitted profile fields were not updated.');
    $assert(!array_key_exists('membership_number', $repository->profiles[21]), 'Sensitive membership data entered the profile record.');

    $memberMiddleware = new MemberAccessMiddleware($service);
    $request = new Request('GET', '/portal');
    $request->setAttribute('auth.user', ['id' => 21]);
    $allowed = $memberMiddleware->handle($request, static fn (Request $request): Response => Response::json($request->attribute('member.portal')));
    $assert($allowed->status() === 200 && str_contains($allowed->body(), 'OWNMEMBER001'), 'Member middleware did not attach the authenticated member.');
    $deniedRequest = new Request('GET', '/portal');
    $deniedRequest->setAttribute('auth.user', ['id' => 23]);
    $denied = $memberMiddleware->handle($deniedRequest, static fn (): Response => Response::html('unexpected'));
    $assert($denied->status() === 403, 'Member middleware allowed a non-member.');

    $session = new SessionManager([
        'name' => 'aims_member_portal_test_' . bin2hex(random_bytes(4)),
        'lifetime' => 120,
        'save_path' => $temporaryDirectory . DIRECTORY_SEPARATOR . 'sessions',
        'path' => '/', 'domain' => '', 'secure' => false, 'http_only' => true, 'same_site' => 'Lax',
    ]);
    $environment = Environment::load($temporaryDirectory . DIRECTORY_SEPARATOR . '.env.missing');
    $config = Config::load(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config', $environment);
    $controller = new MemberPortalController(
        new View(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views'),
        new Csrf($session),
        $service,
        $session,
        new PublicContent($config),
    );
    $dashboardRequest = new Request('GET', '/portal');
    $dashboardRequest->setAttribute('auth.user', ['id' => 21]);
    $dashboardRequest->setAttribute('member.portal', $own->data);
    $response = $controller->dashboard($dashboardRequest);
    $body = $response->body();
    $assert($response->status() === 200, 'The member dashboard did not render.');
    foreach (['Dashboard', 'My Profile', 'Membership', 'Payments', 'Programmes', 'Events', 'Certificates', 'Downloads', 'Notifications', 'Security', 'Support'] as $navigationItem) {
        $assert(str_contains($body, $navigationItem), 'Missing member navigation item ' . $navigationItem . '.');
    }
    $assert(str_contains($body, 'AIMS-2026-OWNMEMBER001') && str_contains($body, '>2<') && str_contains($body, '>3<') && str_contains($body, '>4<'), 'Dashboard membership details or counts are missing.');
    $assert(!str_contains($body, 'attacker.example'), 'The dashboard rendered an unsafe notification URL.');
    $assert(($response->headers()['Cache-Control'] ?? '') === 'no-store, private', 'The member dashboard may be cached.');

    $migration = (string) file_get_contents(dirname(__DIR__) . '/database/migrations/20260904_192000_create_member_portal_foundation_tables.php');
    $assert(str_contains($migration, 'CREATE TABLE member_profiles') && str_contains($migration, 'CREATE TABLE member_notifications'), 'The member portal migration is incomplete.');
    $repositorySource = (string) file_get_contents(dirname(__DIR__) . '/app/Repositories/MemberPortalRepository.php');
    $assert(str_contains($repositorySource, 'WHERE members.user_id = :user_id'), 'Member lookup is not constrained by authenticated user ID.');
    $routes = (string) file_get_contents(dirname(__DIR__) . '/routes/web.php');
    $assert(!str_contains($routes, '/portal/members/{member}'), 'A member-ID-based portal route was exposed.');

    $session->invalidate();
    $session = null;
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temporaryDirectory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); }
    rmdir($temporaryDirectory);

    echo "Member portal checks passed: own-record isolation, access denial, dashboard data, hooks, safe notifications, and permitted profile updates.\n";
} catch (Throwable $exception) {
    if ($session instanceof SessionManager && session_status() === PHP_SESSION_ACTIVE) { $session->invalidate(); }
    fwrite(STDERR, 'Member portal check failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
