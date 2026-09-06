<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Http\Request;
use App\Http\Response;
use Closure;

final class SecurityHeadersMiddleware implements MiddlewareInterface
{
    /** @param array<string, string> $headers @param array<string, string> $httpsHeaders */
    public function __construct(private readonly array $headers, private readonly array $httpsHeaders = [])
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $existing = array_change_key_case($response->headers(), CASE_LOWER);
        $configuredHeaders = $request->isSecure()
            ? array_merge($this->headers, $this->httpsHeaders)
            : $this->headers;
        foreach ($configuredHeaders as $name => $value) {
            if (!array_key_exists(strtolower($name), $existing)) {
                $response = $response->withHeader($name, $value);
            }
        }

        return $response;
    }
}
