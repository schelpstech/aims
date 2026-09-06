<?php

declare(strict_types=1);

namespace App\Routing;

use App\Http\Request;
use App\Http\Response;
use App\Middleware\MiddlewareInterface;
use Closure;
use LogicException;

final class Router
{
    /** @var list<array{method: string, path: string, pattern: string, handler: callable, middleware: list<MiddlewareInterface>}> */
    private array $routes = [];

    /** @var list<MiddlewareInterface> */
    private array $middleware = [];

    public function middleware(MiddlewareInterface $middleware): void
    {
        $this->middleware[] = $middleware;
    }

    /** @param callable(Request): Response $handler
     *  @param list<MiddlewareInterface> $middleware
     */
    public function get(string $path, callable $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    /** @param callable(Request): Response $handler
     *  @param list<MiddlewareInterface> $middleware
     */
    public function post(string $path, callable $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    /** @param callable(Request): Response $handler
     *  @param list<MiddlewareInterface> $middleware
     */
    public function add(string $method, string $path, callable $handler, array $middleware = []): void
    {
        $path = $this->normalizePath($path);
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'pattern' => $this->compile($path),
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    public function dispatch(Request $request): Response
    {
        $destination = fn (Request $request): Response => $this->dispatchRoute($request);

        return $this->pipeline($this->middleware, $destination)($request);
    }

    private function dispatchRoute(Request $request): Response
    {
        $path = $request->path();
        $method = $request->method() === 'HEAD' ? 'GET' : $request->method();
        $allowed = [];

        foreach ($this->routes as $route) {
            if (preg_match($route['pattern'], $path, $matches) !== 1) {
                continue;
            }

            if ($route['method'] !== $method) {
                $allowed[] = $route['method'];
                continue;
            }

            $parameters = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $parameters[$key] = rawurldecode($value);
                }
            }
            $request->setRouteParameters($parameters);

            $destination = static function (Request $request) use ($route): Response {
                $response = ($route['handler'])($request);
                if (!$response instanceof Response) {
                    throw new LogicException('Route handlers must return a Response instance.');
                }

                return $response;
            };

            return $this->pipeline($route['middleware'], $destination)($request);
        }

        if ($allowed !== []) {
            $response = $request->expectsJson()
                ? Response::json(['error' => 'Method not allowed.'], 405)
                : Response::html('<h1>405 Method Not Allowed</h1>', 405);

            return $response->withHeader('Allow', implode(', ', array_unique($allowed)));
        }

        return $request->expectsJson()
            ? Response::json(['error' => 'Not found.'], 404)
            : Response::html('<h1>404 Not Found</h1>', 404);
    }

    /**
     * @param list<MiddlewareInterface> $middleware
     * @param Closure(Request): Response $destination
     * @return Closure(Request): Response
     */
    private function pipeline(array $middleware, Closure $destination): Closure
    {
        return array_reduce(
            array_reverse($middleware),
            static fn (Closure $next, MiddlewareInterface $item): Closure =>
                static fn (Request $request): Response => $item->handle($request, $next),
            $destination,
        );
    }

    private function normalizePath(string $path): string
    {
        $path = '/' . trim($path, '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    private function compile(string $path): string
    {
        $parts = preg_split('/(\{[A-Za-z_][A-Za-z0-9_]*\})/', $path, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $pattern = '';

        foreach ($parts as $part) {
            if (preg_match('/^\{([A-Za-z_][A-Za-z0-9_]*)\}$/', $part, $matches) === 1) {
                $pattern .= '(?P<' . $matches[1] . '>[^/]+)';
            } else {
                $pattern .= preg_quote($part, '#');
            }
        }

        return '#^' . $pattern . '$#';
    }
}
