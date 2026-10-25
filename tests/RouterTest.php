<?php

declare(strict_types=1);

namespace Sesam\Tests;

use PHPUnit\Framework\TestCase;
use Sesam\Core\Kernel;
use Sesam\Core\Logger\Logger;
use Sesam\Core\Request\Request;
use Sesam\Core\Response\Response;
use Sesam\Core\Response\ViewRenderer;
use Sesam\Core\Router\Router;
use Sesam\Core\Security\ErrorHandler;

final class RouterTest extends TestCase
{
    public function testMatchesGetRouteAndParameter(): void
    {
        $router = new Router();
        $router->get('/items/{id}', static function (Request $request): Response {
            return Response::html((string) $request->attribute('id'));
        });

        $response = $router->dispatch(new Request('GET', '/items/12'));

        $this->assertSame(200, $response->status());
        $this->assertSame('12', $response->content());
    }

    public function testUnknownPathRendersNotFoundWithoutInternalDetails(): void
    {
        $response = $this->kernel(new Router())->handle(new Request('GET', '/absente'));

        $this->assertSame(404, $response->status());
        $this->assertStringContainsString('Page introuvable', $response->content());
        $this->assertStringNotContainsString('vendor', $response->content());
    }

    public function testWrongMethodIsRejected(): void
    {
        $router = new Router();
        $router->get('/items/{id}', static fn (Request $request): Response => Response::html('ok'));

        $response = $this->kernel($router)->handle(new Request('POST', '/items/12'));

        $this->assertSame(405, $response->status());
    }

    private function kernel(Router $router): Kernel
    {
        $log = tempnam(sys_get_temp_dir(), 'sesam');
        $this->assertNotFalse($log);

        return new Kernel(
            $router,
            new ErrorHandler(new Logger($log), new ViewRenderer(dirname(__DIR__) . '/app/Views')),
            []
        );
    }
}
