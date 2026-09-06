<?php

declare(strict_types=1);

namespace App\Security;

use App\Http\Request;

final class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    public function __construct(private readonly SessionManager $session)
    {
    }

    public function token(): string
    {
        $token = $this->session->get(self::SESSION_KEY);

        if (!is_string($token) || strlen($token) < 64) {
            $token = bin2hex(random_bytes(32));
            $this->session->put(self::SESSION_KEY, $token);
        }

        return $token;
    }

    public function verify(Request $request): bool
    {
        $submitted = $request->header('X-CSRF-TOKEN') ?? $request->input('_csrf_token');

        return is_string($submitted) && hash_equals($this->token(), $submitted);
    }

    public function field(): string
    {
        return sprintf(
            '<input type="hidden" name="_csrf_token" value="%s">',
            htmlspecialchars($this->token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        );
    }

    public function rotate(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->session->put(self::SESSION_KEY, $token);

        return $token;
    }
}
