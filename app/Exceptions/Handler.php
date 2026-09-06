<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Http\Request;
use App\Http\Response;
use App\Logging\Logger;
use ErrorException;
use Throwable;

final class Handler
{
    private bool $handlingFatalError = false;

    public function __construct(
        private readonly Logger $logger,
        private readonly bool $debug = false,
    ) {
    }

    public function register(): void
    {
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }

            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler(function (Throwable $exception): void {
            $this->render($exception, Request::capture())->send();
        });

        register_shutdown_function(function (): void {
            $error = error_get_last();
            if ($error === null || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                return;
            }

            if ($this->handlingFatalError) {
                return;
            }
            $this->handlingFatalError = true;

            $exception = new ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']);
            $this->render($exception, Request::capture())->send();
        });
    }

    public function render(Throwable $exception, ?Request $request = null): Response
    {
        $errorId = bin2hex(random_bytes(8));
        $this->logger->error('Unhandled application exception.', [
            'error_id' => $errorId,
            'exception' => $exception,
            'method' => $request?->method(),
            'path' => $request?->path(),
            'ip' => $request?->ip(),
        ]);

        $message = 'An unexpected error occurred.';
        $payload = [
            'error' => $message,
            'reference' => $errorId,
        ];

        if ($this->debug) {
            $payload['exception'] = $exception::class;
            $payload['message'] = $exception->getMessage();
            $payload['file'] = $exception->getFile();
            $payload['line'] = $exception->getLine();
            $payload['trace'] = $exception->getTraceAsString();
        }

        if ($request?->expectsJson()) {
            $response = Response::json($payload, 500);
        } else {
            $details = $this->debug
                ? '<pre>' . htmlspecialchars(print_r($payload, true), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</pre>'
                : '';
            $response = Response::html(sprintf(
                '<!doctype html><html lang="en"><meta charset="utf-8"><title>Application error</title><h1>%s</h1><p>Reference: %s</p>%s</html>',
                htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                htmlspecialchars($errorId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                $details,
            ), 500);
        }

        return $response
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Frame-Options', 'DENY');
    }
}

