<?php

declare(strict_types=1);

namespace App\Controllers\Membership;

use App\Controllers\Controller;
use App\Http\Request;
use App\Http\Response;
use App\Security\Csrf;
use App\Security\Security;
use App\Security\SessionManager;
use App\Services\Membership\MembershipResult;
use App\Services\Membership\MembershipService;
use App\Services\PublicSite\PublicContent;
use App\View\View;

final class MembershipApplicationController extends Controller
{
    public function __construct(
        View $view,
        Csrf $csrf,
        private readonly MembershipService $membership,
        private readonly SessionManager $session,
        private readonly PublicContent $content,
    ) {
        parent::__construct($view, $csrf);
    }

    public function show(Request $request): Response
    {
        $userId = $this->userId($request);

        return $this->render($request, [
            'application' => $this->membership->currentApplication($userId),
            'grades' => $this->membership->grades(),
            'message' => $this->session->pullFlash('membership_message'),
            'error' => $this->session->pullFlash('membership_error'),
            'errors' => [],
            'old' => [],
        ]);
    }

    public function saveDraft(Request $request): Response
    {
        $result = $this->membership->saveDraft($this->userId($request), $request->all());
        if ($result->successful) {
            $this->session->flash('membership_message', $result->message);

            return $this->redirect('/account/membership-application')->withHeader('Cache-Control', 'no-store');
        }

        return $this->renderResult($request, $result, $request->all());
    }

    public function uploadDocument(Request $request): Response
    {
        $files = $request->files();
        $applicationPublicId = is_string($request->input('application_public_id'))
            ? trim($request->input('application_public_id'))
            : '';
        $documentType = is_string($request->input('document_type')) ? trim($request->input('document_type')) : '';
        $file = is_array($files['document'] ?? null) ? $files['document'] : [];
        $result = $this->membership->uploadDocument(
            $this->userId($request),
            $applicationPublicId,
            $documentType,
            $file,
        );

        $this->session->flash($result->successful ? 'membership_message' : 'membership_error', $result->message);

        return $this->redirect('/account/membership-application')->withHeader('Cache-Control', 'no-store');
    }

    public function submit(Request $request): Response
    {
        $result = $this->membership->submit($this->userId($request), $request->all());
        if ($result->successful) {
            $this->session->flash('membership_message', $result->message);

            return $this->redirect('/account/membership-application')->withHeader('Cache-Control', 'no-store');
        }

        return $this->renderResult($request, $result, $request->all());
    }

    public function cancel(Request $request): Response
    {
        $applicationPublicId = is_string($request->input('application_public_id'))
            ? trim($request->input('application_public_id'))
            : '';
        $result = $this->membership->cancel($this->userId($request), $applicationPublicId);
        $this->session->flash($result->successful ? 'membership_message' : 'membership_error', $result->message);

        return $this->redirect('/account/membership-application')->withHeader('Cache-Control', 'no-store');
    }

    /** @param array<string, mixed> $old */
    private function renderResult(Request $request, MembershipResult $result, array $old): Response
    {
        return $this->render($request, [
            'application' => $this->membership->currentApplication($this->userId($request)),
            'grades' => $this->membership->grades(),
            'message' => null,
            'error' => $result->message,
            'errors' => $result->errors,
            'old' => $old,
        ], 422);
    }

    /** @param array<string, mixed> $data */
    private function render(Request $request, array $data, int $status = 200): Response
    {
        return $this->view('membership.application', array_merge([
            'site' => $this->content->site(),
            'navigation' => $this->content->navigation(),
            'page' => [
                'title' => 'Membership application',
                'meta_title' => 'Membership Application | AIMS Nigeria',
                'description' => 'Complete and securely submit an AIMS Nigeria membership application.',
                'robots' => 'noindex, nofollow',
            ],
            'activePage' => 'auth',
            'requestPath' => $request->path(),
            'e' => [Security::class, 'escape'],
        ], $data), 'layouts.public', $status)
            ->withHeader('Cache-Control', 'no-store, private')
            ->withHeader('Referrer-Policy', 'no-referrer');
    }

    private function userId(Request $request): int
    {
        $user = $request->attribute('auth.user');

        return is_array($user) ? (int) ($user['id'] ?? 0) : 0;
    }
}
