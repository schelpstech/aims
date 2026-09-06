<?php

declare(strict_types=1);

namespace App\Controllers\Reporting;

use App\Controllers\Controller;
use App\Http\Request;
use App\Http\Response;
use App\Security\Csrf;
use App\Security\Security;
use App\Services\PublicSite\PublicContent;
use App\Services\Reporting\ReportingService;
use App\View\View;

final class ReportingController extends Controller
{
    public function __construct(View$view,Csrf$csrf,private readonly ReportingService$service,private readonly PublicContent$content){parent::__construct($view,$csrf);}
    public function index(Request$request):Response
    {
        $result=$this->service->dashboard($this->actor($request),$request->all());if(!$result->successful)return Response::html('<h1>'.($result->status===403?'403 Forbidden':'Report unavailable').'</h1><p>'.Security::escape($result->message).'</p>',$result->status);
        return$this->view('reporting.index',['site'=>$this->content->site(),'navigation'=>$this->content->navigation(),'page'=>['title'=>'Administrative reporting','meta_title'=>'Administrative reporting | AIMS Nigeria','description'=>'Authorized operational reporting dashboard.','robots'=>'noindex, nofollow'],'activePage'=>'admin','requestPath'=>$request->path(),'e'=>[Security::class,'escape'],'reporting'=>$result->data],'layouts.public')->withHeader('Cache-Control','no-store, private')->withHeader('Referrer-Policy','no-referrer');
    }
    public function export(Request$request):Response
    {
        $report=is_string($request->input('report'))?trim($request->input('report')):'';$result=$this->service->export($this->actor($request),$report,$request->all());if(!$result->successful)return Response::html('<h1>'.($result->status===403?'403 Forbidden':'Report unavailable').'</h1><p>'.Security::escape($result->message).'</p>',$result->status);
        $rows=$result->data['rows']??[];$csv=$this->csv(is_array($rows)?$rows:[]);$filename='aims-'.str_replace('_','-',$report).'-'.date('Ymd').'.csv';
        return new Response($csv,200,['Content-Type'=>'text/csv; charset=UTF-8','Content-Disposition'=>'attachment; filename="'.$filename.'"','Content-Length'=>(string)strlen($csv),'Cache-Control'=>'no-store, private','X-Content-Type-Options'=>'nosniff']);
    }
    /** @param list<array<string,mixed>>$rows */ private function csv(array$rows):string{if($rows===[])return"\xEF\xBB\xBFNo records\r\n";$headers=array_keys($rows[0]);$lines=[$this->line($headers)];foreach($rows as$row){$values=[];foreach($headers as$header)$values[]=$this->safeCell($row[$header]??'');$lines[]=$this->line($values);}return"\xEF\xBB\xBF".implode("\r\n",$lines)."\r\n";}
    /** @param list<mixed>$values */ private function line(array$values):string{return implode(',',array_map(static fn($value):string=>'"'.str_replace('"','""',(string)$value).'"',$values));}
    private function safeCell(mixed$value):string{$value=is_scalar($value)?(string)$value:'';return preg_match('/^[=+\-@\t\r]/',$value)===1?"'".$value:$value;}
    private function actor(Request$request):int{$user=$request->attribute('auth.user');return is_array($user)?(int)($user['id']??0):0;}
}
