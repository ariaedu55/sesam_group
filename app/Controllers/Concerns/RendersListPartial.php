<?php

declare(strict_types=1);

namespace Sesam\Controllers\Concerns;

use Sesam\Core\Kernel;
use Sesam\Core\Request\Request;
use Sesam\Core\Response\Response;

trait RendersListPartial
{
    /**
     * @param array<string, mixed> $data
     */
    protected function renderListPartial(Request $request, string $template, array $data, int $status = 200): ?Response
    {
        if ($request->header(Kernel::PARTIAL_HEADER) === '1') {
            return $this->render($template, $data, $status, 'layouts/fragment');
        }

        return null;
    }
}
