<?php

declare(strict_types=1);

namespace App\Controllers\Payments;

use App\Controllers\Controller;
use App\Http\Request;
use App\Http\Response;
use App\Security\Csrf;
use App\Security\Security;
use App\Security\SessionManager;
use App\Services\Payments\PaymentResult;
use App\Services\Payments\PaymentService;
use App\Services\PublicSite\PublicContent;
use App\View\View;

final class PaymentController extends Controller
{
    public function __construct(View $view, Csrf $csrf, private readonly PaymentService $service, private readonly SessionManager $session, private readonly PublicContent $content) { parent::__construct($view, $csrf); }

    public function index(Request $request): Response
    {
        return $this->view('payments.index', [
            'site' => $this->content->site(), 'navigation' => $this->content->navigation(),
            'page' => ['title' => 'Invoices and payments', 'meta_title' => 'Invoices and payments | AIMS Nigeria', 'description' => 'Secure member invoices and payments.', 'robots' => 'noindex, nofollow'],
            'activePage' => 'portal', 'requestPath' => $request->path(), 'e' => [Security::class, 'escape'],
            'invoices' => $this->service->invoices($this->actor($request)),
            'message' => $this->session->pullFlash('payment_message'), 'error' => $this->session->pullFlash('payment_error'),
        ], 'layouts.public')->withHeader('Cache-Control', 'no-store, private');
    }

    public function initialize(Request $request): Response
    {
        $result = $this->service->initialize($this->actor($request), (string) $request->route('invoice', ''), (string) $request->input('idempotency_key', ''));
        if ($result->successful && isset($result->data['authorization_url'])) return Response::redirect((string) $result->data['authorization_url'], 303)->withHeader('Cache-Control', 'no-store');
        $this->session->flash($result->successful ? 'payment_message' : 'payment_error', $result->message);
        return Response::redirect('/account/invoices', 303)->withHeader('Cache-Control', 'no-store');
    }

    public function callback(Request $request): Response
    {
        $result = $this->service->callback((string) $request->input('reference', ''));
        return $this->resultJson($result);
    }

    public function webhook(Request $request): Response
    {
        return $this->resultJson($this->service->webhook((string) $request->input('gateway', ''), $request->rawBody(), $request->headers()));
    }

    private function resultJson(PaymentResult $result): Response
    {
        return Response::json(['ok' => $result->successful, 'message' => $result->message, 'data' => $result->data], $result->status)->withHeader('Cache-Control', 'no-store');
    }

    private function actor(Request $request): int { $user = $request->attribute('auth.user'); return is_array($user) ? (int) ($user['id'] ?? 0) : 0; }
}
