<?php
declare(strict_types=1);
namespace App\Http\Middleware;

use App\Core\{Request, Response, RequestId as Rid};

final class RequestId
{
    public function __invoke(Request $r, callable $next): Response
    {
        $id = $r->header('x-request-id') ?: Rid::generate();
        Rid::set($id);

        $response = $next($r);
        return $response->withHeader('X-Request-ID', $id);
    }
}
