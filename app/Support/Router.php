<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\NotFoundException;
use Closure;

final class Router
{
    /**
     * @var list<array{method: string, pattern: string, regex: string, handler: Closure}>
     */
    private array $routes = [];

    public function get(string $pattern, Closure $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, Closure $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    public function patch(string $pattern, Closure $handler): void
    {
        $this->add('PATCH', $pattern, $handler);
    }

    public function delete(string $pattern, Closure $handler): void
    {
        $this->add('DELETE', $pattern, $handler);
    }

    public function add(string $method, string $pattern, Closure $handler): void
    {
        $normalized = '/' . ltrim($pattern, '/');
        if ($normalized !== '/') {
            $normalized = rtrim($normalized, '/');
        }

        $regex = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            static fn (array $m): string => '(?P<' . $m[1] . '>[A-Za-z0-9._-]+)',
            $normalized
        );

        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $normalized,
            'regex' => '#^' . $regex . '$#',
            'handler' => $handler,
        ];
    }

    public function dispatch(HttpRequest $request): HttpResponse
    {
        $method = $request->method() === 'HEAD' ? 'GET' : $request->method();
        $path = $request->path();

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            if (preg_match($route['regex'], $path, $matches) === 1) {
                $params = [];
                foreach ($matches as $key => $val) {
                    if (is_string($key)) {
                        $params[$key] = $val;
                    }
                }

                /** @var HttpResponse $response */
                $response = ($route['handler'])($request, $params);

                return $response;
            }
        }

        throw new NotFoundException('Endpoint or page not found.');
    }
}
