<?php
declare(strict_types=1);
namespace App\Services\Reporting;
use App\Authorization\PermissionCheckerInterface;use InvalidArgumentException;use Throwable;
final class ReportingService
{
 public const EXPORTS=['members','membership_applications','programme_applications','programme_enrolments','event_registrations','payments'];
 public function __construct(private readonly ReportingRepositoryInterface$repository,private readonly PermissionCheckerInterface$permissions){}
 /** @param array<string,mixed>$input */ public function dashboard(int$actor,array$input):ReportingResult{if(!$this->allowed($actor,'report.view'))return ReportingResult::failure(403,'You are not authorized to view reports.');try{$filters=ReportFilters::fromInput($input);$finance=$this->allowed($actor,'report.finance');return ReportingResult::success('Reporting dashboard loaded.',['filters'=>$filters,'catalogue'=>$this->repository->filters(),'metrics'=>$this->repository->dashboard($filters,$finance),'can_finance'=>$finance,'can_export'=>$this->allowed($actor,'report.export')]);}catch(InvalidArgumentException$e){return ReportingResult::failure(422,$e->getMessage());}catch(Throwable){return ReportingResult::failure(503,'Reporting data is temporarily unavailable.');}}
 /** @param array<string,mixed>$input */ public function export(int$actor,string$report,array$input):ReportingResult{if(!$this->allowed($actor,'report.view')||!$this->allowed($actor,'report.export'))return ReportingResult::failure(403,'You are not authorized to export reports.');if(!in_array($report,self::EXPORTS,true))return ReportingResult::failure(404,'Report not found.');if($report==='payments'&&!$this->allowed($actor,'report.finance'))return ReportingResult::failure(403,'You are not authorized to export financial reports.');try{$filters=ReportFilters::fromInput($input);return ReportingResult::success('Report generated.',['report'=>$report,'filters'=>$filters,'rows'=>$this->repository->export($report,$filters,10000)]);}catch(InvalidArgumentException$e){return ReportingResult::failure(422,$e->getMessage());}catch(Throwable){return ReportingResult::failure(503,'The report could not be generated.');}}
 private function allowed(int$actor,string$permission):bool{return$actor>0&&$this->permissions->allows($actor,$permission);}
}
