<?php

declare(strict_types=1);

namespace Sesam\Middleware;

use Sesam\Core\Request\Request;
use Sesam\Core\Response\Response;
use Sesam\Core\Session\Session;

final class SessionMiddleware implements Middleware
{
    public function __construct(private readonly Session $session)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        $this->session->start($request->isSecure());
        $this->session->expireIdle();

        return $next($request);
    }
}
