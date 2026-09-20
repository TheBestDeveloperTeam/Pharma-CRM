<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Exceptions\Handler;

/**
 * Application — Boots and runs the HTTP kernel.
 */
class Application
{
    private Container $container;
    private Router    $router;
    private array     $globalMiddleware = [];
    private array     $namedMiddleware  = [];

    public function __construct(private readonly string $basePath)
    {
        $this->container = new Container();
        $this->router    = new Router();

        // Bind self
        $this->container->instance(Application::class, $this);
        $this->container->instance(Container::class, $this->container);
        $this->container->instance(Router::class, $this->router);
    }

    // ── Middleware ────────────────────────────────────────────────────────────

    public function addMiddleware(string $class): void
    {
        $this->globalMiddleware[] = $class;
    }

    public function addNamedMiddleware(string $alias, string|callable $middleware): void
    {
        $this->namedMiddleware[$alias] = $middleware;
    }

    public function getNamedMiddleware(string $alias): string|callable|null
    {
        return $this->namedMiddleware[$alias] ?? null;
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    public function getContainer(): Container
    {
        return $this->container;
    }

    public function getRouter(): Router
    {
        return $this->router;
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    // ── Run ───────────────────────────────────────────────────────────────────

    public function run(): void
    {
        $handler = new Handler($this->container);

        try {
            $request  = Request::capture();
            $response = $this->handle($request);
        } catch (\Throwable $e) {
            $response = $handler->render($e);
        }

        $response->send();
    }

    private function handle(Request $request): Response
    {
        // Build global middleware pipeline
        $pipeline = $this->buildPipeline(
            $this->globalMiddleware,
            function (Request $req) { return $this->dispatch($req); }
        );

        return $pipeline($request);
    }

    private function dispatch(Request $request): Response
    {
        $match = $this->router->match($request);

        if ($match === null) {
            return Response::json([
                'success' => false,
                'error'   => ['code' => 'NOT_FOUND', 'message' => 'Route not found.'],
                'meta'    => ['request_id' => $request->getId()],
            ], 404);
        }

        // Inject route params
        $request->setRouteParams($match['params']);

        // Named middleware for this route
        $routeMiddleware = array_map(
            fn($alias) => $this->resolveNamedMiddleware($alias),
            $match['middleware'] ?? []
        );

        $pipeline = $this->buildPipeline(
            $routeMiddleware,
            fn(Request $req) => $this->callAction($match['action'], $req)
        );

        return $pipeline($request);
    }

    private function callAction(array|callable $action, Request $request): Response
    {
        if (is_callable($action)) {
            $result = $action($request);
            return $this->wrapResult($result, $request);
        }

        [$class, $method] = $action;
        $controller = $this->container->make($class);
        $result     = $controller->$method($request);
        return $this->wrapResult($result, $request);
    }

    private function wrapResult(mixed $result, Request $request): Response
    {
        if ($result instanceof Response) {
            return $result;
        }
        return Response::json([
            'success' => true,
            'data'    => $result,
            'meta'    => ['request_id' => $request->getId()],
        ]);
    }

    private function buildPipeline(array $middleware, callable $core): callable
    {
        $pipeline = $core;
        foreach (array_reverse($middleware) as $mw) {
            $resolved = is_callable($mw)
                ? $mw
                : [$this->container->make($mw), 'handle'];

            $next     = $pipeline;
            $pipeline = fn(Request $req) => $resolved($req, $next);
        }
        return $pipeline;
    }

    private function resolveNamedMiddleware(string $alias): callable|string
    {
        $mw = $this->getNamedMiddleware($alias);
        if ($mw === null) {
            throw new \RuntimeException("Unknown middleware alias: $alias");
        }
        return $mw;
    }
}
