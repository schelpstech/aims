<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Http\Request;
use App\Http\Response;
use App\Security\Csrf;
use Closure;

final class CsrfMiddleware implements MiddlewareInterface
{
    /** @param list<string> $except */
    public function __construct(
        private readonly Csrf $csrf,
        private readonly array $except = [],
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $safeMethods = ['GET', 'HEAD', 'OPTIONS'];
        if (in_array($request->method(), $safeMethods, true) || in_array($request->path(), $this->except, true)) {
            return $next($request);
        }

        if (!$this->csrf->verify($request)) {
            $response = $request->expectsJson()
                ? Response::json(['error' => 'Invalid or expired CSRF token.'], 419)
                : Response::html('<h1>419 Page Expired</h1>', 419);

            return $response->withHeader('Cache-Control', 'no-store');
        }

        return $next($request);
    }
}
