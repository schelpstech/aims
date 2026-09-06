<?php

declare(strict_types=1);

namespace App\View;

use RuntimeException;
use Throwable;

final class View
{
    public function __construct(private readonly string $root)
    {
    }

    /** @param array<string, mixed> $data */
    public function render(string $view, array $data = [], ?string $layout = null): string
    {
        $data['view'] ??= $this;
        $content = $this->evaluate($this->resolve($view), $data);

        if ($layout === null) {
            return $content;
        }

        return $this->evaluate($this->resolve($layout), array_merge($data, ['content' => $content]));
    }

    private function resolve(string $view): string
    {
        if ($view === '' || str_contains($view, '..') || preg_match('#^[A-Za-z0-9_./-]+$#', $view) !== 1) {
            throw new RuntimeException('Invalid view name.');
        }

        $relative = str_replace(['.', '/', '\\'], DIRECTORY_SEPARATOR, $view) . '.php';
        $file = $this->root . DIRECTORY_SEPARATOR . $relative;
        $resolvedRoot = realpath($this->root);
        $resolvedFile = realpath($file);

        if ($resolvedRoot === false || $resolvedFile === false || !str_starts_with($resolvedFile, $resolvedRoot . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException(sprintf('View %s was not found.', $view));
        }

        return $resolvedFile;
    }

    /** @param array<string, mixed> $data */
    private function evaluate(string $file, array $data): string
    {
        ob_start();

        try {
            (static function (string $__file, array $__data): void {
                extract($__data, EXTR_SKIP);
                require $__file;
            })($file, $data);

            return (string) ob_get_clean();
        } catch (Throwable $exception) {
            ob_end_clean();
            throw $exception;
        }
    }
}
