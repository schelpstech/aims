<?php
declare(strict_types=1);
namespace App\Services\Reporting;
interface ReportingRepositoryInterface{public function dashboard(ReportFilters $filters,bool $includeFinance):array;public function export(string $report,ReportFilters $filters,int $limit):array;public function filters():array;}
