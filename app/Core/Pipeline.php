<?php
declare(strict_types=1);
namespace App\Core;

final class Pipeline
{
    /**
     * Run middleware stack → handler.
     * Middleware signature: fn(Request $r, callable $next): Response
     *
     * @param callable[] $middlewares
     * @param callable   $handler     fn(Request): Response
     */
    public static function run(Request $request, array $middlewares, callable $handler): Response
    {
        // Build onion from inside out
        $next = $handler;

        foreach (array_reverse($middlewares) as $mw) {
            $outerNext = $next;
            $next = function(Request $r) use ($mw, $outerNext): Response {
                return $mw($r, $outerNext);
            };
        }

        return $next($request);
    }
}
