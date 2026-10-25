<?php

declare(strict_types=1);

namespace Sesam\Core\Router;

use Sesam\Core\HttpException;
use Sesam\Core\Request\Request;
use Sesam\Core\Response\Response;

final class Router
{
    /**
     * @var list<array{method: string, regex: string, handler: callable}>
     */
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function dispatch(Request $request): Response
    {
        $pathMatched = false;

        foreach ($this->routes as $route) {
            $matches = [];
            if (!preg_match($route['regex'], $request->path(), $matches)) {
                continue;
            }

            $method = $request->method();
            // HEAD = GET sans corps : utile pour les sondes HTTPS / reverse proxy.
            if ($method === 'HEAD') {
                $method = 'GET';
            }
            if ($route['method'] !== $method) {
                $pathMatched = true;
                continue;
            }

            $params = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = $value;
                }
            }

            $response = $route['handler']($request->withAttributes($params));
            if (!$response instanceof Response) {
                throw new HttpException(500, 'Une erreur est survenue.');
            }

            return $response;
        }

        if ($pathMatched) {
            throw new HttpException(405, 'Méthode non autorisée.');
        }

        throw new HttpException(404, 'Page introuvable.');
    }

    private function add(string $method, string $path, callable $handler): void
    {
        $this->routes[] = [
            'method' => $method,
            'regex' => $this->compile($path),
            'handler' => $handler,
        ];
    }

    private function compile(string $path): string
    {
        $quoted = preg_quote($path, '#');
        $regex = preg_replace(
            '#\\\\\{([A-Za-z_][A-Za-z0-9_]*)\\\\\}#',
            '(?P<$1>[^/]+)',
            $quoted
        );

        return '#^' . $regex . '$#';
    }
}
