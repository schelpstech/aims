<?php

declare(strict_types=1);

namespace App\Controllers\Programmes;

use App\Controllers\Controller;
use App\Http\Request;
use App\Http\Response;
use App\Security\Csrf;
use App\Security\Security;
use App\Security\SessionManager;
use App\Services\ProgrammeApplications\ProgrammeApplicationResult;
use App\Services\ProgrammeApplications\ProgrammeApplicationService;
use App\Services\PublicSite\PublicContent;
use App\View\View;

final class ProgrammeApplicationAdminController extends Controller
{
    public function __construct(View $view,Csrf $csrf,private readonly ProgrammeApplicationService $service,private readonly SessionManager $session,private readonly PublicContent $content){parent::__construct($view,$csrf);}
    public function index(Request $request):Response
    {
        $result=$this->service->administrativeApplications($this->actor($request),$request->all());if(!$result->successful)return $this->error($result);
        return $this->adminView('programme-application-admin.index','Programme applications',$request,['applications'=>$result->data['items']??[],'filters'=>$request->all(),'message'=>$this->session->pullFlash('programme_application_admin_message'),'error'=>$this->session->pullFlash('programme_application_admin_error')]);
    }
    public function show(Request $request):Response{$result=$this->service->administrativeApplication($this->actor($request),(string)$request->route('application',''));if(!$result->successful)return $this->error($result);return $this->adminView('programme-application-admin.show','Review programme application',$request,['application'=>$result->data,'message'=>$this->session->pullFlash('programme_application_admin_message'),'error'=>$this->session->pullFlash('programme_application_admin_error')]);}
    public function review(Request $r):Response{return $this->action($r,$this->service->review($this->actor($r),$this->id($r),$this->note($r)));}
    public function approve(Request $r):Response{return $this->action($r,$this->service->approve($this->actor($r),$this->id($r),$this->note($r)));}
    public function reject(Request $r):Response{return $this->action($r,$this->service->reject($this->actor($r),$this->id($r),$this->note($r)));}
    public function enrol(Request $r):Response{return $this->action($r,$this->service->enrol($this->actor($r),$this->id($r)));}
    public function complete(Request $r):Response{return $this->action($r,$this->service->complete($this->actor($r),$this->id($r)));}
    public function withdraw(Request $r):Response{return $this->action($r,$this->service->withdrawEnrolment($this->actor($r),$this->id($r),$this->note($r)));}
    private function action(Request $request,ProgrammeApplicationResult $result):Response{$this->session->flash($result->successful?'programme_application_admin_message':'programme_application_admin_error',$result->message);return $this->redirect('/admin/programme-applications/'.rawurlencode($this->id($request)))->withHeader('Cache-Control','no-store, private');}
    /** @param array<string,mixed> $data */ private function adminView(string $template,string $title,Request $request,array $data):Response{return $this->view($template,array_merge(['site'=>$this->content->site(),'navigation'=>$this->content->navigation(),'page'=>['title'=>$title,'meta_title'=>$title.' | AIMS Nigeria','description'=>'Authorized professional programme application administration.','robots'=>'noindex, nofollow'],'activePage'=>'admin','requestPath'=>$request->path(),'e'=>[Security::class,'escape']],$data),'layouts.public')->withHeader('Cache-Control','no-store, private')->withHeader('Referrer-Policy','no-referrer');}
    private function error(ProgrammeApplicationResult $result):Response{return Response::html($result->status===403?'<h1>403 Forbidden</h1>':'<h1>404 Not Found</h1>',$result->status===403?403:404)->withHeader('Cache-Control','no-store, private');}
    private function actor(Request $r):int{$user=$r->attribute('auth.user');return is_array($user)?(int)($user['id']??0):0;} private function id(Request $r):string{return (string)$r->route('application','');} private function note(Request $r):?string{$value=$r->input('note');return is_string($value)?trim($value):null;}
}
