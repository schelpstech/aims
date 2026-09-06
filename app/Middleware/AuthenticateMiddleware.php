<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Auth\AuthService;
use App\Http\Request;
use App\Http\Response;
use Closure;

final class AuthenticateMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly AuthService $auth)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $this->auth->currentUser();
        if ($user === null) {
            return $request->expectsJson()
                ? Response::json(['error' => 'Authentication required.'], 401)->withHeader('Cache-Control', 'no-store')
                : Response::redirect('/login')->withHeader('Cache-Control', 'no-store');
        }

        $request->setAttribute('auth.user', $user);

        return $next($request)->withHeader('Cache-Control', 'no-store, private');
    }
}
