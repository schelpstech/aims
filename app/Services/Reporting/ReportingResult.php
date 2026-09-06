<?php
declare(strict_types=1);
namespace App\Services\Reporting;
final readonly class ReportingResult{private function __construct(public bool$successful,public int$status,public string$message,public ?array$data=null){}public static function success(string$message,array$data):self{return new self(true,200,$message,$data);}public static function failure(int$status,string$message):self{return new self(false,$status,$message);}}
