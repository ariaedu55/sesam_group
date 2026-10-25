<?php

declare(strict_types=1);

namespace Sesam\Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sesam\Core\Kernel;
use Sesam\Core\Logger\Logger;
use Sesam\Core\Request\Request;
use Sesam\Core\Response\Response;
use Sesam\Core\Response\ViewRenderer;
use Sesam\Core\Router\Router;
use Sesam\Core\Security\ErrorHandler;

final class ErrorHandlerTest extends TestCase
{
    public function testUnexpectedErrorDoesNotLeakTheException(): void
    {
        $log = tempnam(sys_get_temp_dir(), 'sesam');
        $this->assertNotFalse($log);

        $router = new Router();
        $router->get('/boom', static function (): Response {
            throw new RuntimeException('SECRET_SQL_LEAK SELECT password FROM users');
        });

        $kernel = new Kernel(
            $router,
            new ErrorHandler(new Logger($log), new ViewRenderer(dirname(__DIR__) . '/app/Views')),
            []
        );

        $response = $kernel->handle(new Request('GET', '/boom'));
        $logged = (string) file_get_contents($log);
        unlink($log);

        $this->assertSame(500, $response->status());
        $this->assertStringContainsString('Une erreur est survenue.', $response->content());
        $this->assertStringNotContainsString('SECRET_SQL_LEAK', $response->content());
        $this->assertStringNotContainsString('ErrorHandlerTest', $response->content());
        $this->assertStringContainsString('SECRET_SQL_LEAK', $logged);
    }
}
