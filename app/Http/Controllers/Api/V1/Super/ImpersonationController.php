<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api\V1\Super;

use App\Core\{Request, Response, Container, TenantContext, RefGenerator};
use App\Core\Security\Jwt;
use App\Policies\SuperOrganizationPolicy;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Domain\Audit\AuditService;
use App\Core\Exceptions\NotFoundException;

final class ImpersonationController
{
    public function __construct(
        private UserRepositoryInterface $userRepo,
        private Jwt $jwt,
        private AuditService $audit,
    ) {}

    public function impersonate(Request $r): Response
    {
        /** @var TenantContext $ctx */
        $ctx = Container::getInstance()->make(TenantContext::class);
        $policy = new SuperOrganizationPolicy($ctx);
        $policy->authorize('impersonate');

        $userRef = $r->param('ref');
        $targetUser = $this->userRepo->findByRef($userRef);
        if (!$targetUser) {
            throw new NotFoundException('USER_NOT_FOUND', "Target user {$userRef} not found.");
        }

        $now = time();
        $jwtExp = $now + 900; // 15-minute expiration window

        $surface = match ($targetUser['role']) {
            'SUPER_ADMIN'     => 'super',
            'FRANCHISE_ADMIN' => 'admin',
            'SALES'           => 'sales',
            'DISTRIBUTOR'     => 'portal',
        };

        $claims = [
            'sub'  => $targetUser['user_ref'],
            'aud'  => $surface,
            'org'  => $targetUser['org_ref'],
            'frn'  => $targetUser['franchise_ref'],
            'role' => $targetUser['role'],
            'scp'  => $targetUser['role'] === 'SUPER_ADMIN' ? 'PLATFORM' : 'FRANCHISE',
            'pty'  => $targetUser['party_ref'] ?? null,
            'sid'  => 'IMP-' . bin2hex(random_bytes(8)),
            'imp'  => $ctx->userRef, // Impersonator Ref recorded in claims
            'typ'  => 'access',
            'exp'  => $jwtExp,
            'nbf'  => $now - 1,
            'iat'  => $now,
            'jti'  => RefGenerator::make('KEY'),
        ];

        $token = $this->jwt->sign($claims);

        $this->audit->log(
            ctx: $ctx,
            category: 'SECURITY',
            action: 'super.impersonate',
            entityType: 'user',
            entityRef: $targetUser['user_ref'],
            reason: "Impersonated by Super Admin {$ctx->userRef} for 15 minutes"
        );

        return Response::json(200, [
            'access_token'     => $token,
            'expires_in'       => 900,
            'token_type'       => 'Bearer',
            'surface'          => $surface,
            'impersonating'    => $targetUser['full_name'],
            'impersonator_ref' => $ctx->userRef,
        ]);
    }
}
