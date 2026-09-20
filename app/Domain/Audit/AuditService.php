<?php

declare(strict_types=1);

namespace App\Domain\Audit;

use App\Core\{RefGenerator, TenantContext, RequestId};
use App\Repositories\Contracts\AuditRepositoryInterface;

/**
 * AuditService — Append-only audit event recorder.
 * Writes to audit_logs for state changes, security events, and tenant bypasses.
 */
class AuditService
{
    public function __construct(
        private readonly AuditRepositoryInterface $repository,
    ) {}

    /**
     * @param TenantContext $ctx
     * @param string        $category    BUSINESS|SECURITY|TENANT_BYPASS|SYSTEM
     * @param string        $action      e.g. 'user.created', 'order.confirmed'
     * @param string        $entityType  'user', 'lead', 'order', etc.
     * @param string|null   $entityRef   Entity ref (e.g. ORD-xxx)
     * @param array|null    $before      State before the change
     * @param array|null    $after       State after the change
     * @param string|null   $reason      Optional explanation/reason
     */
    public function log(
        TenantContext $ctx,
        string        $category,
        string        $action,
        string        $entityType,
        ?string       $entityRef = null,
        ?array        $before = null,
        ?array        $after = null,
        ?string       $reason = null
    ): void {
        $auditRef  = RefGenerator::make('AUD');
        $ip        = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $requestId = RequestId::current();

        $this->repository->insert([
            'audit_ref'        => $auditRef,
            'org_ref'          => $ctx->orgRef,
            'franchise_ref'    => $ctx->franchiseRef,
            'actor_ref'        => $ctx->userRef,
            'actor_role'       => $ctx->role,
            'impersonator_ref' => $ctx->impersonatorRef,
            'category'         => $category,
            'action'           => $action,
            'entity_type'      => $entityType,
            'entity_ref'       => $entityRef,
            'before_json'      => $before ? json_encode($this->sanitize($before)) : null,
            'after_json'       => $after ? json_encode($this->sanitize($after)) : null,
            'reason'           => $reason,
            'ip_address'       => $ip,
            'user_agent'       => $userAgent ? substr($userAgent, 0, 255) : null,
            'request_id'       => $requestId,
            'created_at'       => date('Y-m-d H:i:s'),
        ]);
    }

    private function sanitize(array $data): array
    {
        $sensitive = ['password', 'password_hash', 'token', 'secret', 'secret_enc'];
        foreach ($sensitive as $key) {
            if (array_key_exists($key, $data)) {
                $data[$key] = '[REDACTED]';
            }
        }
        return $data;
    }
}
