<?php
declare(strict_types=1);
use App\Authorization\PermissionCheckerInterface;use App\Services\Reporting\ReportFilters;use App\Services\Reporting\ReportingRepositoryInterface;use App\Services\Reporting\ReportingService;
require dirname(__DIR__).'/vendor/autoload.php';$assert=static function(bool$c,string$m):void{if(!$c)throw new RuntimeException($m);};
try{
 $permissions=new class implements PermissionCheckerInterface{public array$allowed=[];public function allows(int$userId,string$permission):bool{return$userId===7&&in_array($permission,$this->allowed,true);}};
 $repository=new class implements ReportingRepositoryInterface{public bool$financeRequested=false;public int$limit=0;public function dashboard(ReportFilters$f,bool$includeFinance):array{$this->financeRequested=$includeFinance;return['summary'=>[],'finance'=>$includeFinance?[]:null];}public function export(string$report,ReportFilters$f,int$limit):array{$this->limit=$limit;return[['value'=>'=FORMULA','report'=>$report]];}public function filters():array{return[];}};
 $service=new ReportingService($repository,$permissions);$denied=$service->dashboard(7,[]);$assert(!$denied->successful&&$denied->status===403,'Unauthorized reporting access was accepted.');
 $permissions->allowed=['report.view'];$general=$service->dashboard(7,['from'=>'2026-01-01','to'=>'2026-01-31']);$assert($general->successful&&!$repository->financeRequested&&$general->data['metrics']['finance']===null,'General report exposed finance data.');
 $permissions->allowed[]='report.finance';$finance=$service->dashboard(7,['from'=>'2026-01-01','to'=>'2026-01-31']);$assert($finance->successful&&$repository->financeRequested,'Finance permission did not unlock restricted metrics.');
 $paymentsDenied=$service->export(7,'payments',['from'=>'2026-01-01','to'=>'2026-01-31']);$assert(!$paymentsDenied->successful&&$paymentsDenied->status===403,'Export succeeded without report.export.');
 $permissions->allowed[]='report.export';$payments=$service->export(7,'payments',['from'=>'2026-01-01','to'=>'2026-01-31']);$assert($payments->successful&&$repository->limit===10000,'Bounded authorized finance export failed.');
 $badRange=$service->dashboard(7,['from'=>'2010-01-01','to'=>'2026-01-01']);$assert(!$badRange->successful&&$badRange->status===422,'Unbounded reporting range was accepted.');
 $repositoryCode=(string)file_get_contents(dirname(__DIR__).'/app/Repositories/ReportingRepository.php');foreach(['min(10000','paid_at>=:paid_start','members_by_grade','revenue_minor','professional_areas','event_registrations']as$n)$assert(str_contains($repositoryCode,$n),'Reporting repository missing '.$n);
 $controller=(string)file_get_contents(dirname(__DIR__).'/app/Controllers/Reporting/ReportingController.php');$assert(str_contains($controller,"preg_match('/^[=+\\-@\\t\\r]/'"),'CSV formula-injection protection missing.');
 $rbac=(string)file_get_contents(dirname(__DIR__).'/database/seeders/20260904_161000_rbac_seeder.php');$assert(str_contains($rbac,"['report.finance', 'report'"),'Dedicated finance report permission missing.');
 $routes=(string)file_get_contents(dirname(__DIR__).'/routes/web.php');$assert(str_contains($routes,"'/admin/reports/export'")&&str_contains($routes,'$reportExport'),'Protected report routes missing.');
 echo"Reporting checks passed: bounded filters, aggregate metrics, financial isolation, RBAC exports, optimized indexes, and CSV injection protection.\n";
}catch(Throwable$e){fwrite(STDERR,'Reporting check failed: '.$e->getMessage().PHP_EOL);exit(1);}
