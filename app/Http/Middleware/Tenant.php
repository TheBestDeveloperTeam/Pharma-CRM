<?php
declare(strict_types=1);
namespace App\Http\Middleware;

use App\Core\{Request, Response, Container, TenantContext};
use App\Core\Exceptions\{ForbiddenException, UnauthorizedException};

final class Tenant
{
    public function __invoke(Request $r, callable $next): Response
    {
        if (!Container::getInstance()->has(TenantContext::class)) {
            return $next($r);
        }

        /** @var TenantContext $ctx */
        $ctx = Container::getInstance()->make(TenantContext::class);

        // Optional header X-Franchise-Ref check
        $headerFranchise = $r->header('x-franchise-ref');
        
        $headerFranchiseCode = $r->header('x-franchise-code');
        if ($ctx->isSuperAdmin()) {
            if ($headerFranchiseCode !== '') {
                $stmt = \App\Core\Database::connection()->prepare("SELECT franchise_ref FROM franchises WHERE franchise_code = :code LIMIT 1");
                $stmt->execute([':code' => $headerFranchiseCode]);
                $resolvedRef = $stmt->fetchColumn();
            } else {
                // Auto-fallback: If super admin doesn't provide a header, default to the first active franchise
                $stmt = \App\Core\Database::connection()->query("SELECT franchise_ref FROM franchises WHERE status = 'ACTIVE' ORDER BY id ASC LIMIT 1");
                $resolvedRef = $stmt->fetchColumn();
            }

            if ($resolvedRef) {
                $headerFranchise = $resolvedRef;
            } elseif ($headerFranchiseCode !== '') {
                throw new ForbiddenException('TENANT_NOT_FOUND', "Franchise Code '{$headerFranchiseCode}' not found.");
            }

            // Also resolve Party for Distributor Portal bypass
            $headerPartyRef = $r->header('x-party-ref');
            $partyRef = null;
            if ($headerFranchise !== '') {
                if ($headerPartyRef !== '') {
                    $partyRef = $headerPartyRef;
                } else {
                    $stmt = \App\Core\Database::connection()->prepare("SELECT party_ref FROM parties WHERE franchise_ref = ? AND status = 'ACTIVE' ORDER BY id ASC LIMIT 1");
                    $stmt->execute([$headerFranchise]);
                    $partyRef = $stmt->fetchColumn() ?: null;
                }
            }
        }

        if ($headerFranchise !== '') {
            if ($ctx->franchiseRef !== null && $headerFranchise !== $ctx->franchiseRef) {
                if (Container::getInstance()->has(\App\Domain\Audit\AuditService::class)) {
                    /** @var \App\Domain\Audit\AuditService $audit */
                    $audit = Container::getInstance()->make(\App\Domain\Audit\AuditService::class);
                    $audit->log(
                        ctx: $ctx,
                        category: 'SECURITY',
                        action: 'tenant.header_mismatch',
                        entityType: 'franchise',
                        entityRef: $headerFranchise,
                        reason: "Header: {$headerFranchise} vs Token: {$ctx->franchiseRef}"
                    );
                }
                throw new ForbiddenException('TENANT_MISMATCH', 'X-Franchise-Ref header does not match authenticated tenant.');
            } elseif ($ctx->franchiseRef === null && $ctx->isSuperAdmin()) {
                // Super Admin "SignInAs" dynamic impersonation via header
                $ctx = new TenantContext(
                    orgRef: $ctx->orgRef,
                    franchiseRef: $headerFranchise,
                    userRef: $ctx->userRef,
                    role: $ctx->role,
                    scope: 'FRANCHISE', // Switch scope dynamically to allow lower APIs
                    partyRef: $partyRef ?? $ctx->partyRef,
                    requestId: $ctx->requestId,
                    impersonatorRef: $ctx->userRef
                );
                Container::getInstance()->instance(TenantContext::class, $ctx);
            }
        }

        return $next($r);
    }
}
