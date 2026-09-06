<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Http\Request;
use App\Http\Response;
use App\Security\SessionManager;
use Closure;

final class SessionMiddleware implements MiddlewareInterface
{
    /** @param list<string> $except */
    public function __construct(
        private readonly SessionManager $session,
        private readonly array $except = [],
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $safeMethod = in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true);

        // SessionManager starts lazily when authentication, flash data, or a CSRF
        // token is actually used. Avoid creating and locking a session for anonymous,
        // read-only pages that never need one.
        if (!$safeMethod && !in_array($request->path(), $this->except, true)) {
            $this->session->start();
        }

        return $next($request);
    }
}
