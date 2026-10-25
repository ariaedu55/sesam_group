<?php

declare(strict_types=1);

namespace Sesam\Controllers;

use Sesam\Core\Request\Request;
use Sesam\Core\Response\Response;
use Sesam\Core\Response\ViewRenderer;

abstract class Controller
{
    public function __construct(protected readonly ViewRenderer $views)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function render(string $template, array $data = [], int $status = 200, string $layout = 'layouts/main'): Response
    {
        return Response::html($this->views->render($template, $data, $layout), $status);
    }
}
