<?php
declare(strict_types=1);
namespace App\Http\Middleware;

use App\Core\{Request, Response, Container, TenantContext};
use App\Core\Exceptions\ForbiddenException;

final class Role
{
    /**
     * Factory to create role checking middleware instance.
     */
    public static function require(string ...$allowedRoles): \Closure
    {
        return function(Request $r, callable $next) use ($allowedRoles): Response {
            if (!Container::getInstance()->has(TenantContext::class)) {
                throw new ForbiddenException('FORBIDDEN', 'Authentication context missing.');
            }

            /** @var TenantContext $ctx */
            $ctx = Container::getInstance()->make(TenantContext::class);

            if (!in_array($ctx->role, $allowedRoles, true)) {
                throw new ForbiddenException('FORBIDDEN', "Access forbidden for role [{$ctx->role}].");
            }

            return $next($r);
        };
    }
}
