<?php

declare(strict_types=1);

namespace App\Controllers\Membership;

use App\Controllers\Controller;
use App\Http\Request;
use App\Http\Response;
use App\Security\Csrf;
use App\Security\Security;
use App\Security\SessionManager;
use App\Services\PublicSite\PublicContent;
use App\Services\Renewals\MembershipRenewalService;
use App\Services\Renewals\RenewalResult;
use App\View\View;

final class MembershipRenewalController extends Controller
{
    public function __construct(View$v,Csrf$c,private readonly MembershipRenewalService$service,private readonly SessionManager$session,private readonly PublicContent$content){parent::__construct($v,$c);}
    public function index(Request$r):Response{$result=$this->service->dashboard($this->actor($r));if(!$result->successful)return Response::html('<h1>403 Forbidden</h1>',403);return$this->render('renewals.index','Membership renewal',$r,['renewalDashboard'=>$result->data,'message'=>$this->session->pullFlash('renewal_message'),'error'=>$this->session->pullFlash('renewal_error')]);}
    public function generate(Request$r):Response{$result=$this->service->generate($this->actor($r));$this->flash($result);return Response::redirect('/account/membership-renewal',303)->withHeader('Cache-Control','no-store');}
    public function admin(Request$r):Response{$result=$this->service->adminIndex($this->actor($r));if(!$result->successful)return Response::html('<h1>403 Forbidden</h1>',403);return$this->render('renewals.admin','Membership renewal administration',$r,['renewals'=>$result->data['renewals']??[],'configuration'=>$result->data['configuration']??[],'canOverride'=>$result->data['can_override']??false,'canConfigure'=>$result->data['can_configure']??false,'message'=>$this->session->pullFlash('renewal_admin_message'),'error'=>$this->session->pullFlash('renewal_admin_error')]);}
    public function savePolicy(Request$r):Response{$id=$r->route('policy');$result=$this->service->savePolicy($this->actor($r),is_string($id)&&$id!==''?$id:null,$r->all());$this->session->flash($result->successful?'renewal_admin_message':'renewal_admin_error',$result->message);return Response::redirect('/admin/membership-renewals',303)->withHeader('Cache-Control','no-store');}
    public function override(Request$r):Response{$result=$this->service->override($this->actor($r),(string)$r->route('renewal',''),$r->all());$this->session->flash($result->successful?'renewal_admin_message':'renewal_admin_error',$result->message);return Response::redirect('/admin/membership-renewals',303)->withHeader('Cache-Control','no-store');}
    private function flash(RenewalResult$r):void{$this->session->flash($r->successful?'renewal_message':'renewal_error',$r->message);}private function actor(Request$r):int{$u=$r->attribute('auth.user');return is_array($u)?(int)($u['id']??0):0;}private function render(string$template,string$title,Request$r,array$data):Response{return$this->view($template,array_merge(['site'=>$this->content->site(),'navigation'=>$this->content->navigation(),'page'=>['title'=>$title,'meta_title'=>$title.' | AIMS Nigeria','description'=>'Secure membership renewal workflow.','robots'=>'noindex, nofollow'],'activePage'=>'portal','requestPath'=>$r->path(),'e'=>[Security::class,'escape']],$data),'layouts.public')->withHeader('Cache-Control','no-store, private');}
}
