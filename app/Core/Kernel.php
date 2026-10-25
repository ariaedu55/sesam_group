<?php

declare(strict_types=1);

namespace Sesam\Core;

use Sesam\Core\Request\Request;
use Sesam\Core\Response\Response;
use Sesam\Core\Router\Router;
use Sesam\Core\Security\ErrorHandler;
use Sesam\Core\Security\SecurityHeaders;
use Sesam\Middleware\Middleware;
use Throwable;

final class Kernel
{
    /**
     * @param list<Middleware> $middleware
     */
    public function __construct(
        private readonly Router $router,
        private readonly ErrorHandler $errors,
        private readonly array $middleware,
        private readonly ?SecurityHeaders $headers = null,
    ) {
    }

    public const MODAL_HEADER = 'X-Sesam-Modal';
    public const MODAL_LOCATION = 'X-Sesam-Location';

    public function handle(Request $request): Response
    {
        $response = $this->forModal($request, $this->dispatch($request));

        return $this->headers?->apply($request, $response) ?? $response;
    }

    /**
     * Un formulaire envoyé depuis une modale (fetch) ne doit pas suivre la redirection :
     * la page cible consommerait le message flash avant que le navigateur ne l'affiche.
     */
    private function forModal(Request $request, Response $response): Response
    {
        $location = $response->headers()['Location'] ?? null;
        if ($request->header(self::MODAL_HEADER) !== '1' || $location === null || $response->status() < 300 || $response->status() >= 400) {
            return $response;
        }

        return new Response('', 200, [self::MODAL_LOCATION => $location]);
    }

    private function dispatch(Request $request): Response
    {
        try {
            $pipeline = array_reduce(
                array_reverse($this->middleware),
                static function (callable $next, Middleware $middleware): callable {
                    return static function (Request $request) use ($middleware, $next): Response {
                        return $middleware->handle($request, $next);
                    };
                },
                fn (Request $request): Response => $this->router->dispatch($request)
            );

            return $pipeline($request);
        } catch (HttpException $exception) {
            return $this->errors->renderHttp($exception);
        } catch (Throwable $exception) {
            return $this->errors->renderUnexpected($exception);
        }
    }
}
