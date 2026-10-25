<?php

declare(strict_types=1);

namespace Sesam\Middleware;

use Sesam\Core\HttpException;
use Sesam\Core\Request\Request;
use Sesam\Core\Response\Response;
use Sesam\Core\Security\Csrf;

final class CsrfMiddleware implements Middleware
{
    public function __construct(private readonly Csrf $csrf)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        if (str_starts_with($request->path(), '/webhooks/')) {
            return $next($request);
        }

        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        $provided = $request->input('_csrf');
        if (!is_string($provided)) {
            $provided = $request->header('X-CSRF-Token');
        }

        if (!$this->csrf->validate($provided)) {
            throw new HttpException(419, 'Jeton de sécurité invalide. Rechargez la page.');
        }

        return $next($request);
    }
}
