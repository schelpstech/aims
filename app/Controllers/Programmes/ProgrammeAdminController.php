<?php

declare(strict_types=1);

namespace App\Controllers\Programmes;

use App\Authorization\PermissionCheckerInterface;
use App\Controllers\Controller;
use App\Http\Request;
use App\Http\Response;
use App\Security\Csrf;
use App\Security\Security;
use App\Security\SessionManager;
use App\Services\ProgrammeAdmin\ProgrammeAdminResult;
use App\Services\ProgrammeAdmin\ProgrammeAdminService;
use App\Services\PublicSite\PublicContent;
use App\View\View;

final class ProgrammeAdminController extends Controller
{
    public function __construct(View $view, Csrf $csrf, private readonly ProgrammeAdminService $service, private readonly PermissionCheckerInterface $permissions, private readonly SessionManager $session, private readonly PublicContent $content) { parent::__construct($view, $csrf); }

    public function index(Request $request): Response
    {
        $actor=$this->actor($request); $result=$this->service->dashboard($actor);
        if (!$result->successful) return Response::html('<h1>403 Forbidden</h1>', 403);
        return $this->view('programme-admin.index', [
            'site'=>$this->content->site(), 'navigation'=>$this->content->navigation(),
            'page'=>['title'=>'Programme administration','meta_title'=>'Programme administration | AIMS Nigeria','description'=>'Authorized programme catalogue administration.','robots'=>'noindex, nofollow'],
            'activePage'=>'admin','requestPath'=>$request->path(),'e'=>[Security::class,'escape'],'catalogue'=>$result->data,
            'can'=>['create'=>$this->permissions->allows($actor,'programme.create'),'update'=>$this->permissions->allows($actor,'programme.update'),'delete'=>$this->permissions->allows($actor,'programme.delete'),'assign'=>$this->permissions->allows($actor,'programme.assign_coordinators')],
            'message'=>$this->session->pullFlash('programme_admin_message'),'error'=>$this->session->pullFlash('programme_admin_error'),
        ], 'layouts.public')->withHeader('Cache-Control','no-store, private')->withHeader('Referrer-Policy','no-referrer');
    }

    public function createProgramme(Request $request): Response { return $this->action($this->service->saveProgramme($this->actor($request), null, $request->all())); }
    public function updateProgramme(Request $request): Response { return $this->action($this->service->saveProgramme($this->actor($request), (string)$request->route('programme',''), $request->all())); }
    public function archiveProgramme(Request $request): Response { return $this->action($this->service->archiveProgramme($this->actor($request), (string)$request->route('programme',''))); }
    public function createArea(Request $request): Response { return $this->action($this->service->saveArea($this->actor($request), null, $request->all())); }
    public function updateArea(Request $request): Response { return $this->action($this->service->saveArea($this->actor($request), (string)$request->route('area',''), $request->all())); }
    public function archiveArea(Request $request): Response { return $this->action($this->service->archiveArea($this->actor($request), (string)$request->route('area',''))); }
    public function assignCoordinator(Request $request): Response { return $this->action($this->service->assignCoordinator($this->actor($request), $request->all())); }
    public function removeCoordinator(Request $request): Response { return $this->action($this->service->removeCoordinator($this->actor($request), (string)$request->route('assignment',''))); }

    private function action(ProgrammeAdminResult $result): Response
    {
        $this->session->flash($result->successful ? 'programme_admin_message' : 'programme_admin_error', $result->message);
        return $this->redirect('/admin/programmes')->withHeader('Cache-Control','no-store, private');
    }
    private function actor(Request $request): int { $user=$request->attribute('auth.user'); return is_array($user) ? (int)($user['id']??0) : 0; }
}
