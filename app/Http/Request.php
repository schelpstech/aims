<?php

declare(strict_types=1);

namespace App\Http;

final class Request
{
    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     * @param array<string, mixed> $files
     * @param array<string, string> $cookies
     * @param array<string, mixed> $server
     * @param array<string, string> $headers
     * @param array<string, string> $routeParameters
     */
    public function __construct(
        private readonly string $method,
        private readonly string $uri,
        private readonly array $query = [],
        private readonly array $body = [],
        private readonly array $files = [],
        private readonly array $cookies = [],
        private readonly array $server = [],
        private readonly array $headers = [],
        private array $routeParameters = [],
        private array $attributes = [],
        private readonly string $rawBody = '',
    ) {
    }

    public static function capture(): self
    {
        $server = $_SERVER;
        $headers = [];

        foreach ($server as $key => $value) {
            if (!is_string($value)) {
                continue;
            }

            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', strtolower(substr($key, 5)));
                $headers[$name] = $value;
            }
        }

        if (isset($server['CONTENT_TYPE'])) {
            $headers['content-type'] = (string) $server['CONTENT_TYPE'];
        }
        if (isset($server['CONTENT_LENGTH'])) {
            $headers['content-length'] = (string) $server['CONTENT_LENGTH'];
        }

        $body = $_POST;
        $rawBody = (string) (file_get_contents('php://input') ?: '');
        $contentType = strtolower($headers['content-type'] ?? '');
        if (str_contains($contentType, 'application/json')) {
            $decoded = json_decode($rawBody, true);
            if (is_array($decoded)) {
                $body = $decoded;
            }
        }

        return new self(
            method: strtoupper((string) ($server['REQUEST_METHOD'] ?? 'GET')),
            uri: (string) ($server['REQUEST_URI'] ?? '/'),
            query: $_GET,
            body: $body,
            files: $_FILES,
            cookies: $_COOKIE,
            server: $server,
            headers: $headers,
            rawBody: $rawBody,
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        $path = parse_url($this->uri, PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? $path : '/';

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return array_replace($this->query, $this->body);
    }

    /** @return array<string, mixed> */
    public function files(): array
    {
        return $this->files;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return $this->headers;
    }

    public function rawBody(): string
    {
        return $this->rawBody;
    }

    public function expectsJson(): bool
    {
        return str_contains(strtolower($this->header('Accept', '') ?? ''), 'application/json')
            || str_contains(strtolower($this->header('Content-Type', '') ?? ''), 'application/json');
    }

    public function isSecure(): bool
    {
        return ($this->server['HTTPS'] ?? '') !== '' && ($this->server['HTTPS'] ?? '') !== 'off';
    }

    public function ip(): string
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? 'unknown');
    }

    /** @param array<string, string> $parameters */
    public function setRouteParameters(array $parameters): void
    {
        $this->routeParameters = $parameters;
    }

    public function route(string $key, mixed $default = null): mixed
    {
        return $this->routeParameters[$key] ?? $default;
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }
}
