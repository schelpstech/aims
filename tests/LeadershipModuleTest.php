<?php

declare(strict_types=1);

use App\Config\Config;
use App\Config\Environment;
use App\Controllers\Public\PublicController;
use App\Http\Request;
use App\Logging\Logger;
use App\Security\Csrf;
use App\Security\SessionManager;
use App\Services\Leadership\LeadershipRepositoryInterface;
use App\Services\Leadership\LeadershipService;
use App\Services\PublicSite\PublicContent;
use App\View\View;

require dirname(__DIR__) . '/vendor/autoload.php';

$temporaryDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'aims-leadership-test-' . bin2hex(random_bytes(6));
$session = null;
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

try {
    mkdir($temporaryDirectory, 0700, true);
    $logger = new Logger($temporaryDirectory . DIRECTORY_SEPARATOR . 'leadership.log', 'debug');
    $repository = new class implements LeadershipRepositoryInterface {
        public function publicDirectoryRows(): array
        {
            return [
                [
                    'group_id' => 2,
                    'group_name' => 'Second Test Group',
                    'group_slug' => 'second-test-group',
                    'group_description' => null,
                    'group_display_order' => 20,
                    'position_id' => null,
                    'position_name' => null,
                    'assignment_id' => null,
                    'person_public_id' => null,
                ],
                [
                    'group_id' => 1,
                    'group_name' => 'First Test Group',
                    'group_slug' => 'first-test-group',
                    'group_description' => 'A confirmed test group.',
                    'group_display_order' => 10,
                    'position_id' => 1,
                    'position_name' => 'Test Position',
                    'position_description' => null,
                    'position_display_order' => 10,
                    'assignment_id' => 1,
                    'assignment_display_order' => 20,
                    'person_public_id' => 'a031ce10-6eb6-43b9-a5b4-26c04e2e255a',
                    'person_name' => 'Later Test Leader',
                    'person_title' => null,
                    'person_qualifications' => null,
                    'person_biography' => null,
                    'person_photo_path' => 'javascript:alert(1)',
                    'person_professional_area' => null,
                    'person_email' => 'not-an-email',
                    'person_linkedin_url' => 'https://example.test/not-linkedin',
                    'person_display_order' => 20,
                ],
                [
                    'group_id' => 1,
                    'group_name' => 'First Test Group',
                    'group_slug' => 'first-test-group',
                    'group_description' => 'A confirmed test group.',
                    'group_display_order' => 10,
                    'position_id' => 1,
                    'position_name' => 'Test Position',
                    'position_description' => null,
                    'position_display_order' => 10,
                    'assignment_id' => 2,
                    'assignment_display_order' => 10,
                    'person_public_id' => 'd3b959b5-f19b-425f-b739-11a72be52c07',
                    'person_name' => 'Early <Test> Leader',
                    'person_title' => 'Dr',
                    'person_qualifications' => null,
                    'person_biography' => 'A confirmed biography with <markup>.',
                    'person_photo_path' => '/assets/uploads/leadership/early-test.webp',
                    'person_professional_area' => 'Testing',
                    'person_email' => 'leader@example.test',
                    'person_linkedin_url' => 'https://www.linkedin.com/in/test-leader',
                    'person_display_order' => 10,
                ],
            ];
        }
    };

    $service = new LeadershipService(
        $repository,
        $logger,
        ['Advisory Board', 'Governing Board', 'Management Team', 'Programme Coordinators'],
    );
    $directory = $service->directory();
    $assert($directory['available'] === true, 'The available data source was marked unavailable.');
    $assert(count($directory['groups']) === 2, 'Leadership groups were not assembled from repository rows.');
    $assert($directory['groups'][0]['name'] === 'First Test Group', 'Leadership group ordering is incorrect.');
    $assert($directory['groups'][0]['members'][0]['name'] === 'Early <Test> Leader', 'Assignment display ordering is incorrect.');
    $assert($directory['groups'][0]['members'][0]['photo'] === '/assets/uploads/leadership/early-test.webp', 'A safe leadership photo path was rejected.');
    $assert($directory['groups'][0]['members'][1]['photo'] === null, 'An unsafe leadership photo path was published.');
    $assert($directory['groups'][0]['members'][1]['email'] === null, 'An invalid leadership email was published.');
    $assert($directory['groups'][0]['members'][1]['linkedin'] === null, 'A non-LinkedIn URL was published as LinkedIn.');

    $environment = Environment::load($temporaryDirectory . DIRECTORY_SEPARATOR . '.env.missing');
    $config = Config::load(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config', $environment);
    $session = new SessionManager([
        'name' => 'aims_leadership_test_' . bin2hex(random_bytes(4)),
        'lifetime' => 10,
        'save_path' => $temporaryDirectory . DIRECTORY_SEPARATOR . 'sessions',
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'http_only' => true,
        'same_site' => 'Lax',
    ]);
    $csrf = new Csrf($session);
    $view = new View(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views');
    $controller = new PublicController($view, $csrf, new PublicContent($config), $service);
    $response = $controller->leadership(new Request('GET', '/leadership'));
    $body = $response->body();
    $assert($response->status() === 200, 'The dynamic leadership page did not render.');
    $assert(str_contains($body, 'First Test Group'), 'The public page did not use repository leadership groups.');
    $assert(str_contains($body, 'Early &lt;Test&gt; Leader'), 'The public page did not escape a leadership name.');
    $assert(str_contains($body, 'A confirmed biography with &lt;markup&gt;.'), 'The public page did not escape a biography.');
    $assert(!str_contains($body, 'javascript:alert'), 'The public page rendered an unsafe image source.');
    $assert(substr_count($body, '<h1') === 1, 'The leadership page heading hierarchy has multiple H1 elements.');

    $throwingRepository = new class implements LeadershipRepositoryInterface {
        public function publicDirectoryRows(): array
        {
            throw new RuntimeException('Simulated unavailable database.');
        }
    };
    $fallback = (new LeadershipService(
        $throwingRepository,
        $logger,
        ['Advisory Board', 'Governing Board', 'Management Team', 'Programme Coordinators'],
    ))->directory();
    $assert($fallback['available'] === false, 'A failed leadership data source was not reported as unavailable.');
    $assert(count($fallback['groups']) === 4, 'The verified group-only fallback is incomplete.');

    $migration = (string) file_get_contents(
        dirname(__DIR__) . '/database/migrations/20260904_180000_create_organisational_leadership_tables.php',
    );
    foreach (['CREATE TABLE people', 'CREATE TABLE leadership_groups', 'CREATE TABLE leadership_positions', 'CREATE TABLE leadership_assignments', 'ENGINE=InnoDB'] as $requirement) {
        $assert(str_contains($migration, $requirement), sprintf('Leadership migration is missing %s.', $requirement));
    }

    $seeder = (string) file_get_contents(
        dirname(__DIR__) . '/database/seeders/20260904_181000_confirmed_leadership_seeder.php',
    );
    $assert(substr_count($seeder, "'full_name' =>") === 28, 'The confirmed leadership seeder does not contain 28 unique people.');
    foreach (['Segun Folorunso', 'T.A Okeowo', 'Amusa Nojimu Adetunji', 'Oduntan Oluwatoyin'] as $confirmedName) {
        $assert(str_contains($seeder, $confirmedName), sprintf('Confirmed leader %s is missing.', $confirmedName));
    }
    foreach (['qualifications', 'biography', 'photo_path', 'professional_area', 'linkedin_url'] as $unconfirmedField) {
        $assert(!str_contains($seeder, "'{$unconfirmedField}' =>"), sprintf('The seeder invents %s data.', $unconfirmedField));
    }

    $template = (string) file_get_contents(dirname(__DIR__) . '/resources/views/public/leadership.php');
    $assert(!str_contains($template, 'Segun Folorunso'), 'A leadership name was hard-coded in the public template.');

    $session->invalidate();
    $session = null;
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($temporaryDirectory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($temporaryDirectory);

    echo "Leadership module checks passed: dynamic data, ordering, escaping, safe links/assets, fallback, schema, and confirmed seed data.\n";
} catch (Throwable $exception) {
    if ($session instanceof SessionManager && session_status() === PHP_SESSION_ACTIVE) {
        $session->invalidate();
    }
    fwrite(STDERR, 'Leadership module check failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
