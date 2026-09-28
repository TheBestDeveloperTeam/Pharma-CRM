<?php
declare(strict_types=1);

require_once __DIR__ . '/autoload.php';
require_once __DIR__ . '/../app/Support/env.php';

\App\Support\Env::load(__DIR__ . '/../.env');
date_default_timezone_set(\App\Support\Config::get('app.timezone', 'Asia/Kolkata'));

$container = \App\Core\Container::getInstance();
$bindingsFn = require __DIR__ . '/bindings.php';
$bindingsFn($container);

$router = new \App\Core\Router();
$routesFn = require __DIR__ . '/routes.php';
$routesFn($router);

$globalMiddleware = require __DIR__ . '/middleware.php';

// Only auto-capture & dispatch if called from a web server (CLI scripts require bootstrap without dispatching)
if (PHP_SAPI !== 'cli') {
    try {
        $request = \App\Core\Request::capture();
    } catch (\App\Core\Exceptions\ValidationException $e) {
        $response = \App\Core\Http\CorsPolicy::apply(
            \App\Core\Response::error(422, 'VALIDATION_FAILED', $e->getMessage()),
            $_SERVER['HTTP_ORIGIN'] ?? ''
        );
        $response->send();
        exit(0);
    } catch (\Throwable $e) {
        $response = \App\Core\Http\CorsPolicy::apply(
            \App\Core\Response::error(400, 'BAD_REQUEST', $e->getMessage()),
            $_SERVER['HTTP_ORIGIN'] ?? ''
        );
        $response->send();
        exit(0);
    }

    // Fast-path OPTIONS preflight requests for zero CORS errors
    if ($request->isOptions()) {
        $response = \App\Core\Http\CorsPolicy::preflightResponse($request->origin());
        $response->send();
        exit(0);
    }

    $match = $router->dispatch($request);

    if ($match === null) {
        $response = \App\Core\Http\CorsPolicy::apply(
            \App\Core\Response::error(404, 'NOT_FOUND', 'The requested resource was not found.'),
            $request->origin()
        );
    } elseif (isset($match['__405'])) {
        $response = \App\Core\Http\CorsPolicy::apply(
            \App\Core\Response::error(405, 'METHOD_NOT_ALLOWED', 'Method not allowed.'),
            $request->origin()
        );
    } else {
        $request->params = $match['params'];
        $handler = $match['handler'];
        $routeMiddleware = $match['middleware'];

        // Resolve middleware instances from container
        $allMiddleware = array_map(
            fn($mw) => is_string($mw) ? $container->make($mw) : $mw,
            array_merge(
                array_map(fn($m) => is_string($m) ? $container->make($m) : $m, $globalMiddleware),
                array_map(fn($m) => is_string($m) ? $container->make($m) : $m, $routeMiddleware)
            )
        );

        // Resolve controller if class@method notation
        $controllerFn = is_array($handler)
            ? fn(\App\Core\Request $r) => $container->make($handler[0])->{$handler[1]}($r)
            : $handler;

        try {
            $response = \App\Core\Pipeline::run($request, $allMiddleware, $controllerFn);
        } catch (\App\Core\Exceptions\AppException $e) {
            $response = \App\Core\Http\CorsPolicy::apply(
                \App\Core\Response::error($e->statusCode(), $e->errorCode(), $e->getMessage(), $e->fields()),
                $request->origin()
            );
        } catch (\Throwable $e) {
            // Log it, return generic 500 with CORS headers intact
            error_log($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            $response = \App\Core\Http\CorsPolicy::apply(
                \App\Core\Response::error(500, 'INTERNAL_ERROR',
                    \App\Support\Config::get('app.debug') ? $e->getMessage() : 'An internal error occurred.'),
                $request->origin()
            );
        }
    }

    $response->send();
}
