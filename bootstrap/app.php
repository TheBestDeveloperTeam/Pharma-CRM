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
    $request  = \App\Core\Request::capture();
    $match    = $router->dispatch($request);

    if ($match === null) {
        $response = \App\Core\Response::error(404, 'NOT_FOUND', 'The requested resource was not found.');
    } elseif (isset($match['__405'])) {
        $response = \App\Core\Response::error(405, 'METHOD_NOT_ALLOWED', 'Method not allowed.');
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
            $response = \App\Core\Response::error($e->statusCode(), $e->errorCode(), $e->getMessage(), $e->fields());
        } catch (\Throwable $e) {
            // Log it, return generic 500
            error_log($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            $response = \App\Core\Response::error(500, 'INTERNAL_ERROR',
                \App\Support\Config::get('app.debug') ? $e->getMessage() : 'An internal error occurred.');
        }
    }

    $response->send();
}
