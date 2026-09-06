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

final class ProgrammeApplicationController extends Controller
{
    public function __construct(View $view,Csrf $csrf,private readonly ProgrammeApplicationService $service,private readonly SessionManager $session,private readonly PublicContent $content){parent::__construct($view,$csrf);}
    public function index(Request $request):Response
    {
        return $this->view('programme-applications.index',['site'=>$this->content->site(),'navigation'=>$this->content->navigation(),'page'=>['title'=>'Programme applications','meta_title'=>'Programme Applications | AIMS Nigeria','description'=>'Apply for a published AIMS Nigeria professional programme.','robots'=>'noindex, nofollow'],'activePage'=>'auth','requestPath'=>$request->path(),'e'=>[Security::class,'escape'],'dashboard'=>$this->service->applicantDashboard($this->actor($request)),'message'=>$this->session->pullFlash('programme_application_message'),'error'=>$this->session->pullFlash('programme_application_error')],'layouts.public')->withHeader('Cache-Control','no-store, private')->withHeader('Referrer-Policy','no-referrer');
    }
    public function saveDraft(Request $request):Response{return $this->action($this->service->saveDraft($this->actor($request),$request->all()));}
    public function submit(Request $request):Response{return $this->action($this->service->submit($this->actor($request),$request->all()));}
    public function withdraw(Request $request):Response{return $this->action($this->service->withdraw($this->actor($request),(string)$request->route('application','')));}
    private function action(ProgrammeApplicationResult $result):Response{$this->session->flash($result->successful?'programme_application_message':'programme_application_error',$result->message);return $this->redirect('/account/programme-applications')->withHeader('Cache-Control','no-store, private');}
    private function actor(Request $request):int{$user=$request->attribute('auth.user');return is_array($user)?(int)($user['id']??0):0;}
}
