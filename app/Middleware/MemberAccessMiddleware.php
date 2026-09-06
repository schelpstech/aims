<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Http\Request;
use App\Http\Response;
use App\Services\MemberPortal\MemberPortalService;
use Closure;

final class MemberAccessMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly MemberPortalService $portal)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->attribute('auth.user');
        $userId = is_array($user) ? (int) ($user['id'] ?? 0) : 0;
        $result = $this->portal->dashboard($userId);
        if (!$result->successful) {
            return ($request->expectsJson()
                ? Response::json(['error' => 'Member access required.'], 403)
                : Response::html('<h1>403 Member Access Required</h1>', 403))
                ->withHeader('Cache-Control', 'no-store, private');
        }
        $request->setAttribute('member.portal', $result->data);

        return $next($request)->withHeader('Cache-Control', 'no-store, private');
    }
}
