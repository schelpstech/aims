<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use DateTimeImmutable;

final readonly class ReportFilters
{
    private const STATUSES = ['active','lapsed','suspended','pending_activation','draft','submitted','under_review','query_raised','approved','rejected','cancelled','review','enrolled','completed','withdrawn','registered','attended','renewal_due','invoice_generated','payment_pending','paid','renewed','expired','pending','successful','failed','reversed','refunded'];

    public function __construct(public string $from,public string $to,public ?string $membershipGrade,public ?string $programme,public ?string $status,public ?string $professionalArea,public ?string $event){}

    /** @param array<string,mixed> $input */
    public static function fromInput(array $input,?DateTimeImmutable $now=null):self
    {
        $now??=new DateTimeImmutable('now');$rawTo=self::text($input['to']??null);$rawFrom=self::text($input['from']??null);$to=self::date($rawTo)??$now->format('Y-m-d');$from=self::date($rawFrom)??$now->modify('-30 days')->format('Y-m-d');
        if(($rawFrom!==null&&self::date($rawFrom)===null)||($rawTo!==null&&self::date($rawTo)===null))throw new \InvalidArgumentException('Choose valid reporting dates.');
        $start=DateTimeImmutable::createFromFormat('!Y-m-d',$from);$end=DateTimeImmutable::createFromFormat('!Y-m-d',$to);
        if(!$start||!$end||$start>$end||$end->diff($start)->days>3660)throw new \InvalidArgumentException('Choose a valid reporting range of no more than ten years.');
        $status=self::text($input['status']??null);if($status!==null&&!in_array($status,self::STATUSES,true))throw new \InvalidArgumentException('Invalid report status filter.');
        return new self($from,$to,self::uuid($input['membership_grade']??null),self::uuid($input['programme']??null),$status,self::uuid($input['professional_area']??null),self::uuid($input['event']??null));
    }
    public function start():string{return $this->from.' 00:00:00.000000';}
    public function endExclusive():string{return (new DateTimeImmutable($this->to))->modify('+1 day')->format('Y-m-d').' 00:00:00.000000';}
    /** @return array<string,string> */ public function query():array{return array_filter(['from'=>$this->from,'to'=>$this->to,'membership_grade'=>$this->membershipGrade,'programme'=>$this->programme,'status'=>$this->status,'professional_area'=>$this->professionalArea,'event'=>$this->event],static fn($v)=>$v!==null&&$v!=='');}
    private static function date(mixed $value):?string{$value=is_string($value)?trim($value):'';$date=DateTimeImmutable::createFromFormat('!Y-m-d',$value);return$date&&$date->format('Y-m-d')===$value?$value:null;}
    private static function uuid(mixed $value):?string{$value=self::text($value);if($value===null)return null;if(preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/Di',$value)!==1)throw new \InvalidArgumentException('Invalid report filter identifier.');return$value;}
    private static function text(mixed $value):?string{$value=is_string($value)?trim($value):'';return$value===''?null:$value;}
}
