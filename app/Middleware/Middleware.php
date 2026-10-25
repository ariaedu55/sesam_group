<?php

declare(strict_types=1);

namespace Sesam\Middleware;

use Sesam\Core\Request\Request;
use Sesam\Core\Response\Response;

interface Middleware
{
    public function handle(Request $request, callable $next): Response;
}
