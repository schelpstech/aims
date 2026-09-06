<?php

declare(strict_types=1);

namespace App\Controllers\Certificates;

use App\Controllers\Controller;use App\Http\Request;use App\Http\Response;use App\Security\Csrf;use App\Security\Security;use App\Security\SessionManager;use App\Services\Certificates\CertificateResult;use App\Services\Certificates\CertificateService;use App\Services\PublicSite\PublicContent;use App\View\View;
final class CertificateController extends Controller
{
 public function __construct(View$v,Csrf$c,private readonly CertificateService$service,private readonly SessionManager$session,private readonly PublicContent$content){parent::__construct($v,$c);}
 public function verify(Request$r):Response{$result=$this->service->verify($r->all(),$r->ip(),(string)$r->header('User-Agent',''));return$this->publicView($r,$result);}
 public function qr(Request$r):Response{$result=$this->service->verify(['type'=>'qr_token','value'=>(string)$r->input('token','')],$r->ip(),(string)$r->header('User-Agent',''));return$this->publicView($r,$result);}
 public function member(Request$r):Response{$result=$this->service->memberCertificates($this->actor($r));if(!$result->successful)return Response::html('<h1>403 Forbidden</h1>',403);return$this->render('certificates.member','My certificates',$r,['certificates'=>$result->data['certificates']??[]]);}
 public function admin(Request$r):Response{$result=$this->service->adminCatalogue($this->actor($r));if(!$result->successful)return Response::html('<h1>403 Forbidden</h1>',403);return$this->render('certificates.admin','Certificate administration',$r,['catalogue'=>$result->data,'message'=>$this->session->pullFlash('certificate_message'),'error'=>$this->session->pullFlash('certificate_error'),'sessionToken'=>$this->session->pullFlash('certificate_token')]);}
 public function issue(Request$r):Response{return$this->action($this->service->issue($this->actor($r),$r->all()));}public function revoke(Request$r):Response{return$this->action($this->service->revoke($this->actor($r),(string)$r->route('certificate',''),$r->all()));}public function saveType(Request$r):Response{$id=$r->route('type');return$this->action($this->service->saveType($this->actor($r),is_string($id)&&$id!==''?$id:null,$r->all()));}
 private function action(CertificateResult$result):Response{$this->session->flash($result->successful?'certificate_message':'certificate_error',$result->message);if($result->successful&&isset($result->data['verification_token']))$this->session->flash('certificate_token',(string)$result->data['verification_token']);return Response::redirect('/admin/certificates',303)->withHeader('Cache-Control','no-store');}
 private function publicView(Request$r,CertificateResult$result):Response{return$this->view('public.verify',['site'=>$this->content->site(),'navigation'=>$this->content->navigation(),'page'=>$this->content->page('verify'),'activePage'=>'verify','requestPath'=>$r->path(),'e'=>[Security::class,'escape'],'verificationResult'=>$result],'layouts.public')->withHeader('Cache-Control','no-store');}
 private function render(string$template,string$title,Request$r,array$data):Response{return$this->view($template,array_merge(['site'=>$this->content->site(),'navigation'=>$this->content->navigation(),'page'=>['title'=>$title,'meta_title'=>$title.' | AIMS Nigeria','description'=>'Secure certificate records.','robots'=>'noindex, nofollow'],'activePage'=>'portal','requestPath'=>$r->path(),'e'=>[Security::class,'escape']],$data),'layouts.public')->withHeader('Cache-Control','no-store, private');}private function actor(Request$r):int{$u=$r->attribute('auth.user');return is_array($u)?(int)($u['id']??0):0;}
}
