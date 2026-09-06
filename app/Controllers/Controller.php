<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Response;
use App\Security\Csrf;
use App\View\View;

abstract class Controller
{
    public function __construct(
        protected readonly View $viewRenderer,
        protected readonly Csrf $csrf,
    ) {
    }

    /** @param array<string, mixed> $data */
    protected function view(string $template, array $data = [], ?string $layout = null, int $status = 200): Response
    {
        $data['csrf'] ??= $this->csrf;

        return Response::html($this->viewRenderer->render($template, $data, $layout), $status);
    }

    /** @param array<string, mixed> $data */
    protected function json(array $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    protected function redirect(string $location, int $status = 302): Response
    {
        return Response::redirect($location, $status);
    }
}

