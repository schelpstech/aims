<?php

declare(strict_types=1);

namespace App;

use App\Config\Config;
use App\Exceptions\Handler;
use App\Http\Request;
use App\Routing\Router;
use App\View\View;
use Throwable;

final class Application
{
    public function __construct(
        private readonly Router $router,
        private readonly Handler $errorHandler,
        private readonly View $view,
        private readonly Config $config,
    ) {
    }

    public function run(): void
    {
        $request = Request::capture();

        try {
            $response = $this->router->dispatch($request);
        } catch (Throwable $exception) {
            $response = $this->errorHandler->render($exception, $request);
        }

        $response->send($request->method() === 'HEAD');
    }

    public function config(): Config
    {
        return $this->config;
    }

    public function view(): View
    {
        return $this->view;
    }
}

