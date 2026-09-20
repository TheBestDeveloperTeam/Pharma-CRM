<?php
declare(strict_types=1);
namespace App\Http\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\TenantContext;
use App\Core\Exceptions\ConflictException;
use App\Core\Exceptions\ValidationException;
use App\Repositories\Contracts\IdempotencyRepositoryInterface;

final class Idempotency
{
    public function __construct(private IdempotencyRepositoryInterface $repo) {}

    public function __invoke(Request $r, callable $next): Response
    {
        // Only enforce idempotency on write methods
        if (!in_array($r->method, ['POST', 'PUT', 'PATCH'], true)) {
            return $next($r);
        }

        // Web shell views and login bypass
        if (!str_starts_with($r->path, '/api/v1/')) {
            return $next($r);
        }

        // Exclude oauth token and public endpoints from strict idempotency
        if (str_starts_with($r->path, '/api/v1/oauth/') || str_starts_with($r->path, '/api/v1/webhooks/')) {
            return $next($r);
        }

        $key = $r->header('Idempotency-Key') ?? $r->header('idempotency-key');
        if (empty($key)) {
            return $next($r);
        }

        if (!preg_match('/^[A-Za-z0-9_\-]{16,120}$/', $key)) {
            throw new ValidationException('INVALID_IDEMPOTENCY_KEY', 'Idempotency-Key must be 16-120 alphanumeric chars.');
        }

        $ctx = TenantContext::get();
        $franchiseRef = $ctx->franchiseRef ?? 'PLATFORM';
        $orgRef = $ctx->orgRef;

        $bodyCanonical = json_encode($r->all());
        $requestHash = hash('sha256', $r->method . '|' . $r->path . '|' . $bodyCanonical);

        $existing = $this->repo->find($franchiseRef, $key);
        if ($existing) {
            if ($existing['status'] === 'COMPLETED') {
                if ($existing['request_hash'] === $requestHash) {
                    $json = json_decode($existing['response_json'], true) ?? [];
                    return Response::json((int)$existing['response_status'], $json);
                }
                throw new ConflictException('IDEMPOTENCY_MISMATCH', 'Different payload submitted with the same idempotency key.');
            }

            if ($existing['status'] === 'IN_PROGRESS') {
                throw new ConflictException('REQUEST_IN_PROGRESS', 'A request with this idempotency key is currently processing.');
            }
        }

        $locked = $this->repo->lock($orgRef, $franchiseRef, $key, $r->method, $r->path, $requestHash);
        if (!$locked) {
            throw new ConflictException('REQUEST_IN_PROGRESS', 'Concurrent request with same idempotency key detected.');
        }

        try {
            /** @var Response $res */
            $res = $next($r);
            $bodyData = json_decode($res->body(), true)['data'] ?? [];
            $this->repo->complete($franchiseRef, $key, $res->status(), $bodyData);
            return $res;
        } catch (\Throwable $e) {
            $this->repo->fail($franchiseRef, $key);
            throw $e;
        }
    }
}
