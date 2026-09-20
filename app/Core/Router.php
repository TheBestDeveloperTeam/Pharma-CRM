<?php
declare(strict_types=1);
namespace App\Core;

final class Router
{
    private array $routes = [];
    private array $groupMiddleware = [];
    private array $groupPrefix = [];

    // ── Registration ──────────────────────────────────────────────────────

    public function get(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $path, $handler, $middleware);
    }

    public function post(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $path, $handler, $middleware);
    }

    public function put(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->addRoute('PUT', $path, $handler, $middleware);
    }

    public function patch(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->addRoute('PATCH', $path, $handler, $middleware);
    }

    public function delete(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    /**
     * Group routes with shared prefix + middleware.
     */
    public function group(string $prefix, array $middleware, callable $callback): void
    {
        // Push context
        $this->groupPrefix[]     = $prefix;
        $this->groupMiddleware[] = $middleware;

        $callback($this);

        // Pop context
        array_pop($this->groupPrefix);
        array_pop($this->groupMiddleware);
    }

    private function addRoute(string $method, string $path, callable|array $handler, array $middleware): void
    {
        // Apply group prefix
        $fullPath = implode('', $this->groupPrefix) . $path;
        // Apply group middleware (outer first)
        $allMiddleware = empty($this->groupMiddleware)
            ? $middleware
            : array_merge(...array_merge($this->groupMiddleware, [$middleware]));

        // Convert {param} to named capture groups
        $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $fullPath);
        $pattern = '#^' . $pattern . '$#';

        $this->routes[] = [
            'method'     => $method,
            'path'       => $fullPath,
            'pattern'    => $pattern,
            'handler'    => $handler,
            'middleware' => $allMiddleware,
        ];
    }

    // ── Dispatch ──────────────────────────────────────────────────────────

    /**
     * Find a matching route. Returns ['handler', 'middleware', 'params'] or null.
     * Sets 405 if path matches but method doesn't.
     */
    public function dispatch(Request $request): ?array
    {
        $method = $request->method;
        $path   = $request->path;

        $methodNotAllowed = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['pattern'], $path, $matches)) {
                continue; // path doesn't match
            }

            if ($route['method'] !== $method) {
                $methodNotAllowed = true;
                continue; // path matches, method doesn't
            }

            // Extract named params
            $params = [];
            foreach ($matches as $k => $v) {
                if (is_string($k)) {
                    $params[$k] = $v;
                }
            }

            return [
                'handler'    => $route['handler'],
                'middleware' => $route['middleware'],
                'params'     => $params,
            ];
        }

        if ($methodNotAllowed) {
            return ['__405' => true]; // caller handles
        }

        return null; // 404
    }

    /**
     * Export route table for OpenAPI parity check.
     * Returns: array of { method, path }
     */
    public function getRouteTable(): array
    {
        return array_map(fn($r) => [
            'method' => $r['method'],
            'path'   => $r['path'],
        ], $this->routes);
    }
}
