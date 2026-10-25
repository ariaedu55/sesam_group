<?php

declare(strict_types=1);

namespace Sesam\Tests;

use PHPUnit\Framework\TestCase;
use Sesam\Core\Bootstrap;
use Sesam\Core\Request\Request;
use Sesam\Core\Security\Csrf;
use Sesam\Core\Session\Session;
use Sesam\Middleware\CsrfMiddleware;

final class CsrfMiddlewareTest extends TestCase
{
    public function testPostWithoutTokenIsRejectedByTheKernel(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $response = $kernel->handle(new Request('POST', '/'));

        $this->assertSame(419, $response->status());
        $this->assertStringContainsString('Jeton de sécurité invalide', $response->content());
    }

    public function testValidTokenPasses(): void
    {
        $session = new Session();
        $csrf = new Csrf($session);
        $middleware = new CsrfMiddleware($csrf);
        $request = new Request('POST', '/', [], ['_csrf' => $csrf->token()]);

        $response = $middleware->handle($request, static fn (): \Sesam\Core\Response\Response => \Sesam\Core\Response\Response::html('ok'));

        $this->assertSame(200, $response->status());
        $this->assertSame('ok', $response->content());
    }
}
