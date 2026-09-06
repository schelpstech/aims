<?php

declare(strict_types=1);

namespace App\Controllers\Membership;

use App\Authorization\PermissionCheckerInterface;
use App\Controllers\Controller;
use App\Http\Request;
use App\Http\Response;
use App\Security\Csrf;
use App\Security\Security;
use App\Security\SessionManager;
use App\Services\MembershipAdmin\AdminActionResult;
use App\Services\MembershipAdmin\MembershipAdminService;
use App\Services\PublicSite\PublicContent;
use App\View\View;

final class MembershipAdminController extends Controller
{
    public function __construct(
        View $view,
        Csrf $csrf,
        private readonly MembershipAdminService $membership,
        private readonly PermissionCheckerInterface $permissions,
        private readonly SessionManager $session,
        private readonly PublicContent $content,
    ) {
        parent::__construct($view, $csrf);
    }

    public function index(Request $request): Response
    {
        $result = $this->membership->applications($this->userId($request), $request->all());
        $data = $result->data ?? ['items' => [], 'total' => 0, 'page' => 1, 'pages' => 1];

        return $this->adminView('membership-admin.index', 'Membership applications', $request, [
            'applications' => $data['items'],
            'total' => $data['total'],
            'pageNumber' => $data['page'],
            'pageCount' => $data['pages'],
            'filters' => $request->all(),
            'message' => $this->session->pullFlash('membership_admin_message'),
            'error' => $result->successful ? $this->session->pullFlash('membership_admin_error') : $result->message,
        ], $result->successful ? 200 : $result->status);
    }

    public function show(Request $request): Response
    {
        $result = $this->membership->application($this->userId($request), (string) $request->route('application', ''));
        if (!$result->successful || $result->data === null) {
            return $this->errorResponse($result);
        }
        $userId = $this->userId($request);

        return $this->adminView('membership-admin.show', 'Review membership application', $request, [
            'application' => $result->data,
            'can' => [
                'review' => $this->permissions->allows($userId, 'member.review'),
                'query' => $this->permissions->allows($userId, 'member.query'),
                'approve' => $this->permissions->allows($userId, 'member.approve'),
                'reject' => $this->permissions->allows($userId, 'member.reject'),
            ],
            'message' => $this->session->pullFlash('membership_admin_message'),
            'error' => $this->session->pullFlash('membership_admin_error'),
        ]);
    }

    public function document(Request $request): Response
    {
        $result = $this->membership->document($this->userId($request), (string) $request->route('document', ''));
        if (!$result->successful || $result->data === null) {
            return $this->errorResponse($result);
        }
        $document = $result->data;
        $contents = (string) ($document['contents'] ?? '');
        $mime = (string) ($document['mime_type'] ?? 'application/octet-stream');
        $extension = match ($mime) {
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            default => 'bin',
        };

        return (new Response($contents, 200, [
            'Content-Type' => $mime,
            'Content-Length' => (string) strlen($contents),
            'Content-Disposition' => 'attachment; filename="membership-document.' . $extension . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, private',
        ]));
    }

    public function review(Request $request): Response
    {
        return $this->actionResponse($request, $this->membership->review(
            $this->userId($request),
            (string) $request->route('application', ''),
            $this->stringInput($request, 'comment'),
        ));
    }

    public function query(Request $request): Response
    {
        return $this->actionResponse($request, $this->membership->query(
            $this->userId($request),
            (string) $request->route('application', ''),
            $this->stringInput($request, 'comment'),
        ));
    }

    public function reject(Request $request): Response
    {
        return $this->actionResponse($request, $this->membership->reject(
            $this->userId($request),
            (string) $request->route('application', ''),
            $this->stringInput($request, 'comment'),
        ));
    }

    public function approve(Request $request): Response
    {
        $comment = $this->stringInput($request, 'comment');

        return $this->actionResponse($request, $this->membership->approve(
            $this->userId($request),
            (string) $request->route('application', ''),
            $comment === '' ? null : $comment,
        ));
    }

    private function actionResponse(Request $request, AdminActionResult $result): Response
    {
        $this->session->flash(
            $result->successful ? 'membership_admin_message' : 'membership_admin_error',
            $result->message,
        );
        $publicId = rawurlencode((string) $request->route('application', ''));

        return $this->redirect('/admin/membership/applications/' . $publicId)
            ->withHeader('Cache-Control', 'no-store, private');
    }

    /** @param array<string, mixed> $data */
    private function adminView(string $template, string $title, Request $request, array $data, int $status = 200): Response
    {
        return $this->view($template, array_merge([
            'site' => $this->content->site(),
            'navigation' => $this->content->navigation(),
            'page' => [
                'title' => $title,
                'meta_title' => $title . ' | AIMS Nigeria',
                'description' => 'Authorized AIMS Nigeria membership administration.',
                'robots' => 'noindex, nofollow',
            ],
            'activePage' => 'admin',
            'requestPath' => $request->path(),
            'e' => [Security::class, 'escape'],
        ], $data), 'layouts.public', $status)
            ->withHeader('Cache-Control', 'no-store, private')
            ->withHeader('Referrer-Policy', 'no-referrer');
    }

    private function errorResponse(AdminActionResult $result): Response
    {
        return ($result->status === 403
            ? Response::html('<h1>403 Forbidden</h1>', 403)
            : Response::html('<h1>404 Not Found</h1>', 404))
            ->withHeader('Cache-Control', 'no-store, private');
    }

    private function userId(Request $request): int
    {
        $user = $request->attribute('auth.user');

        return is_array($user) ? (int) ($user['id'] ?? 0) : 0;
    }

    private function stringInput(Request $request, string $key): string
    {
        $value = $request->input($key);

        return is_string($value) ? trim($value) : '';
    }
}
