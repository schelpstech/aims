<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Authorization\PermissionCheckerInterface;
use App\Http\Request;
use App\Http\Response;
use Closure;

final class AuthorizeMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly PermissionCheckerInterface $permissions,
        private readonly string $permission,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->attribute('auth.user');
        $userId = is_array($user) ? (int) ($user['id'] ?? 0) : 0;
        if (!$this->permissions->allows($userId, $this->permission)) {
            return ($request->expectsJson()
                ? Response::json(['error' => 'Forbidden.'], 403)
                : Response::html('<h1>403 Forbidden</h1>', 403))
                ->withHeader('Cache-Control', 'no-store, private');
        }

        return $next($request)->withHeader('Cache-Control', 'no-store, private');
    }
}
