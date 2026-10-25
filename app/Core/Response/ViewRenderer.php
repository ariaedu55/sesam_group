<?php

declare(strict_types=1);

namespace Sesam\Core\Response;

use RuntimeException;

final class ViewRenderer
{
    public function __construct(private readonly string $viewsPath)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = [], string $layout = 'layouts/main'): string
    {
        $inner = $this->capture($template, $data);
        $layoutData = $data;
        $layoutData['content'] = $inner;
        $layoutData['title'] = $data['title'] ?? 'SESAM';
        $layoutData['current'] = $data['current'] ?? '';

        return $this->capture($layout, $layoutData);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function capture(string $template, array $data): string
    {
        $templateFile = $this->resolve($template);
        ob_start();
        try {
            (static function (string $__templateFile, array $__data): void {
                extract($__data, EXTR_SKIP);
                require $__templateFile;
            })($templateFile, $data);
        } catch (\Throwable $exception) {
            ob_end_clean();
            throw $exception;
        }

        return (string) ob_get_clean();
    }

    private function resolve(string $template): string
    {
        if (!preg_match('/^[a-z0-9_\/-]+$/', $template)) {
            throw new RuntimeException('Vue refusée.');
        }

        $path = $this->viewsPath . '/' . $template . '.php';
        if (!is_file($path)) {
            throw new RuntimeException('Vue introuvable.');
        }

        return $path;
    }
}
