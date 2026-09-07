<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Auth\AuthResult;
use App\Auth\AuthService;
use App\Authorization\PermissionCheckerInterface;
use App\Controllers\Controller;
use App\Http\Request;
use App\Http\Response;
use App\Security\Csrf;
use App\Security\Security;
use App\Security\SessionManager;
use App\Services\PublicSite\PublicContent;
use App\Services\MemberPortal\MemberPortalService;
use App\View\View;

final class AuthController extends Controller
{
    private const ADMIN_PERMISSIONS = [
        'member.view',
        'programme.view',
        'programme.application_view',
        'event.manage',
        'certificate.issue',
        'certificate.revoke',
        'certificate.type_manage',
        'cms.edit',
        'report.view',
        'member.renewal_override',
        'member.renewal_policy',
    ];

    public function __construct(
        View $view,
        Csrf $csrf,
        private readonly AuthService $auth,
        private readonly SessionManager $session,
        private readonly PublicContent $content,
        private readonly ?PermissionCheckerInterface $permissions = null,
        private readonly ?MemberPortalService $memberPortal = null,
    ) {
        parent::__construct($view, $csrf);
    }

    public function showLogin(Request $request): Response
    {
        return $this->authView('auth.login', 'Sign in', 'Securely access your AIMS Nigeria account.', $request, [
            'message' => $this->session->pullFlash('auth_message'),
            'email' => '',
            'error' => null,
        ]);
    }

    public function login(Request $request): Response
    {
        $email = is_string($request->input('email')) ? trim($request->input('email')) : '';
        $password = is_string($request->input('password')) ? $request->input('password') : '';
        $result = $this->auth->login($email, $password, $request->ip(), $this->userAgent($request));

        if ($result->successful) {
            $this->session->flash('auth_message', $result->message);
            return $this->redirect('/account')->withHeader('Cache-Control', 'no-store');
        }

        $status = $result->code === 'throttled' ? 429 : 422;
        $response = $this->authView('auth.login', 'Sign in', 'Securely access your AIMS Nigeria account.', $request, [
            'message' => null,
            'email' => $email,
            'error' => $result->message,
        ], $status);

        return $result->retryAfter === null
            ? $response
            : $response->withHeader('Retry-After', (string) $result->retryAfter);
    }

    public function showRegister(Request $request): Response
    {
        return $this->authView('auth.register', 'Create an account', 'Start a secure AIMS Nigeria account registration.', $request, [
            'message' => null,
            'email' => '',
            'error' => null,
        ]);
    }

    public function register(Request $request): Response
    {
        $email = is_string($request->input('email')) ? trim($request->input('email')) : '';
        $password = is_string($request->input('password')) ? $request->input('password') : '';
        $confirmation = is_string($request->input('password_confirmation')) ? $request->input('password_confirmation') : '';

        if (!hash_equals($password, $confirmation)) {
            return $this->authView('auth.register', 'Create an account', 'Start a secure AIMS Nigeria account registration.', $request, [
                'message' => null,
                'email' => $email,
                'error' => 'The password confirmation does not match.',
            ], 422);
        }

        $result = $this->auth->register($email, $password, $request->ip(), $this->userAgent($request));

        return $this->resultView('auth.register', 'Create an account', 'Start a secure AIMS Nigeria account registration.', $request, $result, $email, 202);
    }

    public function showForgotPassword(Request $request): Response
    {
        return $this->authView('auth.forgot-password', 'Forgot password', 'Request secure password recovery instructions.', $request, [
            'message' => null,
            'email' => '',
            'error' => null,
        ]);
    }

    public function forgotPassword(Request $request): Response
    {
        $email = is_string($request->input('email')) ? trim($request->input('email')) : '';
        $result = $this->auth->requestPasswordReset($email, $request->ip(), $this->userAgent($request));

        return $this->resultView('auth.forgot-password', 'Forgot password', 'Request secure password recovery instructions.', $request, $result, '', 202);
    }

    public function showResetPassword(Request $request): Response
    {
        $token = is_string($request->input('token')) ? $request->input('token') : '';

        return $this->authView('auth.reset-password', 'Reset password', 'Choose a new password using a one-time recovery link.', $request, [
            'token' => $token,
            'message' => null,
            'error' => preg_match('/^[a-f0-9]{64}$/D', $token) === 1 ? null : 'This password reset link is invalid.',
        ]);
    }

    public function resetPassword(Request $request): Response
    {
        $token = is_string($request->input('token')) ? $request->input('token') : '';
        $password = is_string($request->input('password')) ? $request->input('password') : '';
        $confirmation = is_string($request->input('password_confirmation')) ? $request->input('password_confirmation') : '';

        if (!hash_equals($password, $confirmation)) {
            return $this->authView('auth.reset-password', 'Reset password', 'Choose a new password using a one-time recovery link.', $request, [
                'token' => $token,
                'message' => null,
                'error' => 'The password confirmation does not match.',
            ], 422);
        }

        $result = $this->auth->resetPassword($token, $password, $request->ip(), $this->userAgent($request));
        if ($result->successful) {
            $this->session->flash('auth_message', $result->message);
            return $this->redirect('/login')->withHeader('Cache-Control', 'no-store');
        }

        return $this->authView('auth.reset-password', 'Reset password', 'Choose a new password using a one-time recovery link.', $request, [
            'token' => $token,
            'message' => null,
            'error' => $result->message,
        ], 422);
    }

    public function showVerifyEmail(Request $request): Response
    {
        $token = is_string($request->input('token')) ? $request->input('token') : '';

        return $this->authView('auth.verify-email', 'Verify email', 'Confirm your email address using a one-time verification link.', $request, [
            'token' => $token,
            'error' => preg_match('/^[a-f0-9]{64}$/D', $token) === 1 ? null : 'This verification link is invalid.',
        ]);
    }

    public function verifyEmail(Request $request): Response
    {
        $token = is_string($request->input('token')) ? $request->input('token') : '';
        $result = $this->auth->verifyEmail($token, $request->ip(), $this->userAgent($request));
        if ($result->successful) {
            $this->session->flash('auth_message', $result->message);
            return $this->redirect('/login')->withHeader('Cache-Control', 'no-store');
        }

        return $this->authView('auth.verify-email', 'Verify email', 'Confirm your email address using a one-time verification link.', $request, [
            'token' => $token,
            'error' => $result->message,
        ], 422);
    }

    public function account(Request $request): Response
    {
        $user = $request->attribute('auth.user');
        $userId = is_array($user) ? (int) ($user['id'] ?? 0) : 0;

        return $this->authView('auth.account', 'Your account', 'Secure AIMS Nigeria account access.', $request, [
            'user' => is_array($user) ? $user : [],
            'message' => $this->session->pullFlash('auth_message'),
            'canManageMembership' => $this->permissions?->allows($userId, 'member.view') === true,
            'canAccessReports' => $this->permissions?->allows($userId, 'report.view') === true,
            'canAccessAdmin' => $this->hasAnyPermission($userId, self::ADMIN_PERMISSIONS),
            'canAccessMemberPortal' => $userId > 0
                && $this->memberPortal?->dashboard($userId)->successful === true,
        ]);
    }

    public function showSecurity(Request $request): Response
    {
        $user = $request->attribute('auth.user');

        return $this->authView('auth.security', 'Account security', 'Change your password and protect your active account sessions.', $request, [
            'user' => is_array($user) ? $user : [],
            'message' => $this->session->pullFlash('security_message'),
            'error' => null,
        ]);
    }

    public function changePassword(Request $request): Response
    {
        $currentPassword = is_string($request->input('current_password')) ? $request->input('current_password') : '';
        $newPassword = is_string($request->input('new_password')) ? $request->input('new_password') : '';
        $confirmation = is_string($request->input('new_password_confirmation')) ? $request->input('new_password_confirmation') : '';

        if (!hash_equals($newPassword, $confirmation)) {
            return $this->securityView($request, 'The new password confirmation does not match.', 422);
        }

        $result = $this->auth->changePassword(
            $currentPassword,
            $newPassword,
            $request->ip(),
            $this->userAgent($request),
        );

        if ($result->successful) {
            $this->session->flash('security_message', $result->message);

            return $this->redirect('/account/security', 303)->withHeader('Cache-Control', 'no-store');
        }

        $status = $result->code === 'throttled' ? 429 : 422;
        $response = $this->securityView($request, $result->message, $status);

        return $result->retryAfter === null
            ? $response
            : $response->withHeader('Retry-After', (string) $result->retryAfter);
    }

    public function logout(Request $request): Response
    {
        $this->auth->logout($request->ip(), $this->userAgent($request));

        return $this->redirect('/login')->withHeader('Cache-Control', 'no-store');
    }

    private function resultView(
        string $template,
        string $title,
        string $description,
        Request $request,
        AuthResult $result,
        string $email,
        int $successStatus,
    ): Response {
        return $this->authView($template, $title, $description, $request, [
            'message' => $result->successful ? $result->message : null,
            'email' => $email,
            'error' => $result->successful ? null : $result->message,
        ], $result->successful ? $successStatus : 422);
    }

    /** @param array<string, mixed> $data */
    private function authView(
        string $template,
        string $title,
        string $description,
        Request $request,
        array $data,
        int $status = 200,
    ): Response {
        $response = $this->view($template, array_merge([
            'site' => $this->content->site(),
            'navigation' => $this->content->navigation(),
            'page' => [
                'title' => $title,
                'meta_title' => $title . ' | AIMS Nigeria',
                'description' => $description,
                'robots' => 'noindex, nofollow',
            ],
            'activePage' => 'auth',
            'requestPath' => $request->path(),
            'e' => [Security::class, 'escape'],
            'passwordMinimum' => $this->auth->passwordMinimumLength(),
            'passwordMaximum' => $this->auth->passwordMaximumLength(),
        ], $data), 'layouts.public', $status);

        return $response
            ->withHeader('Cache-Control', 'no-store, private')
            ->withHeader('Referrer-Policy', 'no-referrer');
    }

    private function userAgent(Request $request): string
    {
        return (string) ($request->header('User-Agent', '') ?? '');
    }

    private function securityView(Request $request, string $error, int $status): Response
    {
        $user = $request->attribute('auth.user');

        return $this->authView('auth.security', 'Account security', 'Change your password and protect your active account sessions.', $request, [
            'user' => is_array($user) ? $user : [],
            'message' => null,
            'error' => $error,
        ], $status);
    }

    /** @param list<string> $permissions */
    private function hasAnyPermission(int $userId, array $permissions): bool
    {
        if ($userId < 1 || $this->permissions === null) {
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
