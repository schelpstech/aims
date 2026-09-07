<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Authorization\PermissionCheckerInterface;
use App\Controllers\Controller;
use App\Http\Request;
use App\Http\Response;
use App\Security\Csrf;
use App\Security\Security;
use App\Services\PublicSite\PublicContent;
use App\View\View;

final class AdminDashboardController extends Controller
{
    /** @var list<array{label: string, description: string, path: string, permissions: list<string>}> */
    private const MODULES = [
        [
            'label' => 'Content management',
            'description' => 'Create and publish pages, news, announcements, resources, FAQs, media and downloads.',
            'path' => '/admin/cms',
            'permissions' => ['cms.edit'],
        ],
        [
            'label' => 'Membership applications',
            'description' => 'Review submitted applications, raise queries and complete authorized decisions.',
            'path' => '/admin/membership/applications',
            'permissions' => ['member.view'],
        ],
        [
            'label' => 'Programme catalogue',
            'description' => 'Manage professional areas, programmes and coordinator assignments.',
            'path' => '/admin/programmes',
            'permissions' => ['programme.view'],
        ],
        [
            'label' => 'Programme applications',
            'description' => 'Review programme applications and manage approved enrolments.',
            'path' => '/admin/programme-applications',
            'permissions' => ['programme.application_view'],
        ],
        [
            'label' => 'Events',
            'description' => 'Create events, manage registrations and record attendance.',
            'path' => '/admin/events',
            'permissions' => ['event.manage'],
        ],
        [
            'label' => 'Membership renewals',
            'description' => 'Review renewal operations, policies and authorized overrides.',
            'path' => '/admin/membership-renewals',
            'permissions' => ['member.renewal_override', 'member.renewal_policy'],
        ],
        [
            'label' => 'Certificates',
            'description' => 'Configure certificate types, issue certificates and record revocations.',
            'path' => '/admin/certificates',
            'permissions' => ['certificate.issue', 'certificate.revoke', 'certificate.type_manage'],
        ],
        [
            'label' => 'Reports',
            'description' => 'View bounded operational metrics and authorized CSV exports.',
            'path' => '/admin/reports',
            'permissions' => ['report.view'],
        ],
    ];

    public function __construct(
        View $view,
        Csrf $csrf,
        private readonly PermissionCheckerInterface $permissions,
        private readonly PublicContent $content,
    ) {
        parent::__construct($view, $csrf);
    }

    public function index(Request $request): Response
    {
        $user = $request->attribute('auth.user');
        $user = is_array($user) ? $user : [];
        $userId = (int) ($user['id'] ?? 0);
        $modules = [];

        foreach (self::MODULES as $module) {
            if ($this->hasAnyPermission($userId, $module['permissions'])) {
                $modules[] = $module;
            }
        }

        if ($modules === []) {
            return Response::html('<h1>403 Forbidden</h1>', 403)
                ->withHeader('Cache-Control', 'no-store, private');
        }

        return $this->view('admin.dashboard', [
            'site' => $this->content->site(),
            'navigation' => $this->content->navigation(),
            'page' => [
                'title' => 'Administration',
                'meta_title' => 'Administration | AIMS Nigeria',
                'description' => 'Permission-aware administration dashboard for AIMS Nigeria.',
                'robots' => 'noindex, nofollow',
            ],
            'activePage' => 'admin',
            'requestPath' => $request->path(),
            'e' => [Security::class, 'escape'],
            'user' => $user,
            'modules' => $modules,
        ], 'layouts.public')
            ->withHeader('Cache-Control', 'no-store, private')
            ->withHeader('Referrer-Policy', 'no-referrer');
    }

    /** @param list<string> $permissions */
    private function hasAnyPermission(int $userId, array $permissions): bool
    {
        if ($userId < 1) {
            return false;
        }

        foreach ($permissions as $permission) {
            if ($this->permissions->allows($userId, $permission)) {
                return true;
            }
        }

        return false;
    }
}
