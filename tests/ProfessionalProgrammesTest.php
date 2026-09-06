<?php

declare(strict_types=1);

use App\Authorization\PermissionCheckerInterface;
use App\Config\Config;
use App\Config\Environment;
use App\Controllers\Public\PublicController;
use App\Http\Request;
use App\Logging\Logger;
use App\Security\Csrf;
use App\Security\SessionManager;
use App\Services\ProgrammeAdmin\ProgrammeAdminRepositoryInterface;
use App\Services\ProgrammeAdmin\ProgrammeAdminService;
use App\Services\Programmes\ProgrammeDirectoryService;
use App\Services\Programmes\ProgrammeRepositoryInterface;
use App\Services\PublicSite\PublicContent;
use App\View\View;

require dirname(__DIR__) . '/vendor/autoload.php';

$assert = static function (bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); };
$temporaryDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'aims-programme-test-' . bin2hex(random_bytes(6));
$session = null;

try {
    mkdir($temporaryDirectory, 0700, true);
    $logger = new Logger($temporaryDirectory . DIRECTORY_SEPARATOR . 'test.log', 'debug');
    $publicRepository = new class implements ProgrammeRepositoryInterface {
        public function programmeTypes(): array { return [['public_id'=>'10000000-0000-4000-8000-000000000001','name'=>'Test Type','slug'=>'test-type','description'=>null,'display_order'=>10]]; }
        public function programmeRows(?string $slug = null): array {
            if ($slug !== null && $slug !== 'safe-programme') return [];
            return [[
                'public_id'=>'20000000-0000-4000-8000-000000000001','code'=>'TST-1','name'=>'Safe <Programme>','slug'=>'safe-programme','description'=>'Verified <copy>','duration'=>'6 months','entry_requirements'=>null,'delivery_mode'=>'Hybrid','application_fee'=>null,'tuition_fee'=>'1200.00','fee_currency'=>'NGN','display_order'=>10,'type_name'=>'Test Type','type_slug'=>'test-type',
                'area_public_id'=>'30000000-0000-4000-8000-000000000001','area_name'=>'Test Area','area_slug'=>'test-area','area_is_primary'=>1,
                'coordinator_public_id'=>'40000000-0000-4000-8000-000000000001','coordinator_name'=>'Coordinator <Name>','coordinator_title'=>'Dr','coordinator_role'=>'Lead',
            ]];
        }
        public function areaRows(?string $slug = null): array {
            if ($slug !== null && $slug !== 'test-area') return [];
            return [['public_id'=>'30000000-0000-4000-8000-000000000001','code'=>'TA','name'=>'Test Area','slug'=>'test-area','description'=>'Area copy','display_order'=>10,'programme_public_id'=>'20000000-0000-4000-8000-000000000001','programme_code'=>'TST-1','programme_name'=>'Safe <Programme>','programme_slug'=>'safe-programme','programme_type'=>'Test Type','coordinator_public_id'=>null,'coordinator_name'=>null,'coordinator_title'=>null,'coordinator_role'=>null]];
        }
    };
    $directory = new ProgrammeDirectoryService($publicRepository, $logger);
    $catalogue = $directory->catalogue();
    $assert($catalogue['available'] && count($catalogue['programmes']) === 1, 'Published programme rows were not assembled.');
    $assert(count($catalogue['programmes'][0]['areas']) === 1 && count($catalogue['programmes'][0]['coordinators']) === 1, 'Programme relationships were not assembled.');

    $environment=Environment::load($temporaryDirectory.DIRECTORY_SEPARATOR.'.env.missing'); $config=Config::load(dirname(__DIR__).DIRECTORY_SEPARATOR.'config',$environment);
    $session=new SessionManager(['name'=>'aims_programme_test_'.bin2hex(random_bytes(4)),'lifetime'=>10,'save_path'=>$temporaryDirectory.DIRECTORY_SEPARATOR.'sessions','path'=>'/','domain'=>'','secure'=>false,'http_only'=>true,'same_site'=>'Lax']);
    $controller=new PublicController(new View(dirname(__DIR__).DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'views'),new Csrf($session),new PublicContent($config),null,$directory);
    $body=$controller->programmes(new Request('GET','/programmes'))->body();
    $assert(str_contains($body,'Safe &lt;Programme&gt;') && !str_contains($body,'Safe <Programme>'),'Programme output is not escaped.');
    $detail=$controller->programme(new Request('GET','/programmes/safe-programme',routeParameters:['programme'=>'safe-programme']));
    $assert($detail->status()===200 && str_contains($detail->body(),'NGN 1,200.00'),'Programme detail did not render verified data.');
    $missing=$controller->programme(new Request('GET','/programmes/missing',routeParameters:['programme'=>'missing']));
    $assert($missing->status()===404,'Unknown programme did not return 404.');

    $throwing = new class implements ProgrammeRepositoryInterface { public function programmeTypes(): array { throw new RuntimeException('offline'); } public function programmeRows(?string $slug=null): array { throw new RuntimeException('offline'); } public function areaRows(?string $slug=null): array { throw new RuntimeException('offline'); } };
    $assert(!(new ProgrammeDirectoryService($throwing,$logger))->catalogue()['available'],'Unavailable catalogue was not reported safely.');

    $adminRepository = new class implements ProgrammeAdminRepositoryInterface {
        public int $writes=0; public function dashboard(): array { return ['types'=>[],'areas'=>[],'programmes'=>[],'coordinators'=>[],'assignments'=>[]]; }
        public function saveProgramme(int $actor,?string $publicId,array $data): string { $this->writes++; return $publicId??'50000000-0000-4000-8000-000000000001'; }
        public function archiveProgramme(int $actor,string $publicId): void { $this->writes++; }
        public function saveArea(int $actor,?string $publicId,array $data): string { $this->writes++; return $publicId??'60000000-0000-4000-8000-000000000001'; }
        public function archiveArea(int $actor,string $publicId): void { $this->writes++; }
        public function assignCoordinator(int $actor,array $data): void { $this->writes++; }
        public function removeCoordinator(int $actor,string $publicId): void { $this->writes++; }
    };
    $denied = new class implements PermissionCheckerInterface { public function allows(int $userId,string $permission): bool { return false; } };
    $admin = new ProgrammeAdminService($adminRepository,$denied);
    $result=$admin->saveProgramme(9,null,[]); $assert($result->status===403 && $adminRepository->writes===0,'Service-level authorization did not prevent a write.');
    $allowed = new class implements PermissionCheckerInterface { public function allows(int $userId,string $permission): bool { return $userId===9; } };
    $admin = new ProgrammeAdminService($adminRepository,$allowed);
    $invalid=$admin->saveProgramme(9,null,['name'=>'Programme','code'=>'bad code','programme_type'=>'invalid','status'=>'published']);
    $assert($invalid->status===422 && $adminRepository->writes===0,'Invalid programme input reached persistence.');
    $valid=$admin->saveProgramme(9,null,['name'=>'Test Programme','code'=>'TST-2','programme_type'=>'10000000-0000-4000-8000-000000000001','status'=>'draft','application_fee'=>'0','fee_currency'=>'NGN','areas'=>[]]);
    $assert($valid->successful && $adminRepository->writes===1,'Authorized valid programme was not persisted.');
    $badAssignment=$admin->assignCoordinator(9,['person'=>'invalid','programme'=>'','area'=>'']);
    $assert($badAssignment->status===422 && $adminRepository->writes===1,'Invalid coordinator target reached persistence.');

    $migration=(string)file_get_contents(dirname(__DIR__).'/database/migrations/20260904_200000_create_professional_programme_tables.php');
    foreach (['CREATE TABLE professional_areas','CREATE TABLE programme_types','CREATE TABLE programmes','CREATE TABLE programme_areas','CREATE TABLE coordinator_assignments','ENGINE=InnoDB','FOREIGN KEY'] as $needle) $assert(str_contains($migration,$needle),"Migration missing {$needle}.");
    $seeder=(string)file_get_contents(dirname(__DIR__).'/database/seeders/20260904_200000_professional_programme_catalogue_seeder.php');
    foreach (['General Management','Marketing','Human Resources','Finance','Operations','ICT','Diploma','Higher Diploma','Graduate Certificate','Post Graduate Diploma'] as $verified) $assert(str_contains($seeder,$verified),"Verified seed missing {$verified}.");
    $assert(!str_contains($seeder,'INSERT INTO programmes'),'Programme records were invented by the catalogue seeder.');
    $routes=(string)file_get_contents(dirname(__DIR__).'/routes/web.php');
    foreach (['assign_coordinators','/programmes/{programme}','/professional-areas/{area}'] as $needle) $assert(str_contains($routes,$needle),"Stage 9 route or permission missing {$needle}.");
    $bootstrap=(string)file_get_contents(dirname(__DIR__).'/bootstrap/app.php');
    $assert(str_contains($bootstrap,"'programme.' . \$permissionAction"),'Programme authorization middleware is not registered.');
    foreach (['resources/views/public/programmes.php','resources/views/public/professional-areas.php'] as $template) { $text=(string)file_get_contents(dirname(__DIR__).'/'.$template); $assert(!str_contains($text,'General Management')&&!str_contains($text,'Diploma'),'Verified catalogue data was hard-coded into a public template.'); }

    $session->invalidate(); $session=null;
    $files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temporaryDirectory,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST); foreach($files as $file) $file->isDir()?rmdir($file->getPathname()):unlink($file->getPathname()); rmdir($temporaryDirectory);
    echo "Professional programme checks passed: dynamic public data, escaping, safe fallback, RBAC service boundary, validation, schema, and verified seeds.\n";
} catch (Throwable $exception) {
    if ($session instanceof SessionManager && session_status()===PHP_SESSION_ACTIVE) $session->invalidate();
    fwrite(STDERR,'Professional programme check failed: '.$exception->getMessage().PHP_EOL); exit(1);
}
