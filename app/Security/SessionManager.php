<?php

declare(strict_types=1);

namespace App\Security;

use RuntimeException;

final class SessionManager
{
    private bool $started = false;

    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config)
    {
    }

    public function start(): void
    {
        if ($this->started || session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;
            return;
        }

        if (headers_sent($file, $line)) {
            throw new RuntimeException(sprintf('Cannot start session after output at %s:%d.', $file, $line));
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');

        $savePath = (string) ($this->config['save_path'] ?? '');
        if ($savePath !== '') {
            if (!is_dir($savePath) && !mkdir($savePath, 0700, true) && !is_dir($savePath)) {
                throw new RuntimeException('Unable to create the session storage directory.');
            }
            session_save_path($savePath);
        }

        $sameSite = (string) ($this->config['same_site'] ?? 'Lax');
        if (!in_array($sameSite, ['Lax', 'Strict', 'None'], true)) {
            throw new RuntimeException('Invalid session SameSite configuration.');
        }
        $secureCookie = (bool) ($this->config['secure'] ?? true);
        if ($sameSite === 'None' && !$secureCookie) {
            throw new RuntimeException('SameSite=None session cookies must also be Secure.');
        }

        session_name((string) ($this->config['name'] ?? 'aims_session'));
        session_set_cookie_params([
            'lifetime' => max(0, (int) ($this->config['lifetime'] ?? 120)) * 60,
            'path' => (string) ($this->config['path'] ?? '/'),
            'domain' => (string) ($this->config['domain'] ?? ''),
            'secure' => $secureCookie,
            'httponly' => (bool) ($this->config['http_only'] ?? true),
            'samesite' => $sameSite,
        ]);

        if (!session_start()) {
            throw new RuntimeException('Unable to start the application session.');
        }

        $this->started = true;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->start();

        return $_SESSION[$key] ?? $default;
    }

    public function put(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    public function remove(string $key): void
    {
        $this->start();
        unset($_SESSION[$key]);
    }

    public function id(): string
    {
        $this->start();

        return session_id();
    }

    public function flash(string $key, mixed $value): void
    {
        $this->put('_flash.' . $key, $value);
    }

    public function pullFlash(string $key, mixed $default = null): mixed
    {
        $sessionKey = '_flash.' . $key;
        $value = $this->get($sessionKey, $default);
        $this->remove($sessionKey);

        return $value;
    }

    public function regenerate(): void
    {
        $this->start();
        if (!session_regenerate_id(true)) {
            throw new RuntimeException('Unable to regenerate the session identifier.');
        }
    }

    public function invalidate(): void
    {
        $this->start();
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $parameters = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $parameters['path'],
                'domain' => $parameters['domain'],
                'secure' => $parameters['secure'],
                'httponly' => $parameters['httponly'],
                'samesite' => $parameters['samesite'] ?? 'Lax',
            ]);
        }

        session_destroy();
        $this->started = false;
    }
}
