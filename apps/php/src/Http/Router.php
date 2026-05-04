<?php

declare(strict_types=1);

namespace Cataloga\Http;

final class Router
{
    /** @var array<int, array{methods: array<int,string>, pattern: string, regex: string, handler: callable}> */
    private array $routes = [];

    public function add(string|array $methods, string $pattern, callable $handler): void
    {
        $methods = (array) $methods;
        $normalizedMethods = array_map(static fn (string $method): string => strtoupper($method), $methods);

        $regex = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $pattern);
        if (!is_string($regex)) {
            throw new \RuntimeException('Failed to compile route pattern.');
        }

        $this->routes[] = [
            'methods' => $normalizedMethods,
            'pattern' => $pattern,
            'regex' => '#^' . $regex . '$#',
            'handler' => $handler,
        ];
    }

    public function dispatch(Request $request): Response
    {
        $method = $request->method();
        $path = rtrim($request->path(), '/');
        if ($path === '') {
            $path = '/';
        }
        $pathMatched = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }
            $pathMatched = true;

            if (!in_array($method, $route['methods'], true)) {
                continue;
            }

            $params = [];
            foreach ($matches as $key => $value) {
                if (!is_string($key)) {
                    continue;
                }
                $params[$key] = rawurldecode($value);
            }

            /** @var callable $handler */
            $handler = $route['handler'];

            return $handler($request, $params);
        }

        if ($pathMatched) {
            return Response::html('Method Not Allowed', 405);
        }

        return Response::html('Not Found', 404);
    }
}
