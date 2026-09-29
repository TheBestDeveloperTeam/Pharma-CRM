<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Core\{Container, Request, Response, TenantContext};
use App\Core\Exceptions\{ConflictException, NotFoundException, ValidationException};
use App\Domain\Audit\AuditService;
use App\Domain\Authorization\AuthorizationService;
use App\Domain\Onboarding\OnboardingService;

final class OnboardingController
{
    public function __construct(
        private OnboardingService $service,
        private AuthorizationService $auth,
        private ?\PDO $pdo = null
    ) {
        $this->pdo = $pdo ?? Container::getInstance()->make(\PDO::class);
    }

    private function ctx(): TenantContext
    {
        return TenantContext::get();
    }

    private function access(TenantContext $c, Request $r): void
    {
        $this->service->assertAccess($c, (string)$r->param('ref'));
    }

    public function invite(Request $r): Response
    {
        $c = $this->ctx();
        $this->auth->requirePermission($c, 'distributorOnboarding', 'generateInvite');
        return Response::json(201, $this->service->issueInvite($c, $r->input('lead_ref'), $r->input('assigned_user_ref')));
    }

    public function index(Request $r): Response
    {
        $c = $this->ctx();
        $this->auth->requirePermission($c, 'distributorOnboarding', 'view');
        return Response::json(200, $this->service->listing($c, $r->query, (int)$r->query('page', 1), min(100, max(1, (int)$r->query('per_page', 25)))));
    }

    public function show(Request $r): Response
    {
        $c = $this->ctx();
        $this->auth->requirePermission($c, 'distributorOnboarding', 'view');
        $this->access($c, $r);
        return Response::json(200, $this->service->detail($c, (string)$r->param('ref')));
    }

    public function approve(Request $r): Response
    {
        $c = $this->ctx();
        $this->auth->requirePermission($c, 'distributorOnboarding', 'approve');
        $this->access($c, $r);
        return Response::json(200, $this->service->transition($c, (string)$r->param('ref'), 'APPROVED', $r->input('remarks')));
    }

    public function reject(Request $r): Response
    {
        $c = $this->ctx();
        $this->auth->requirePermission($c, 'distributorOnboarding', 'reject');
        $this->access($c, $r);
        return Response::json(200, $this->service->transition($c, (string)$r->param('ref'), 'REJECTED', $r->input('remarks')));
    }

    public function requestInfo(Request $r): Response
    {
        $c = $this->ctx();
        $this->auth->requirePermission($c, 'distributorOnboarding', 'review');
        $this->access($c, $r);
        return Response::json(200, $this->service->transition($c, (string)$r->param('ref'), 'INFO_REQUESTED', $r->input('remarks')));
    }

    public function convert(Request $r): Response
    {
        $c = $this->ctx();
        $this->auth->requirePermission($c, 'distributorOnboarding', 'convert');
        $this->access($c, $r);
        return Response::json(200, $this->service->convert($c, (string)$r->param('ref'), $r->all()));
    }

    public function verifyKyc(Request $r): Response
    {
        $c = $this->ctx();
        $s = (string)$r->input('status');
        $this->auth->requirePermission($c, 'kyc', $s === 'REJECTED' ? 'reject' : 'verify');
        $this->access($c, $r);
        return Response::json(200, $this->service->verifyDocument($c, (string)$r->param('ref'), (string)$r->param('document_ref'), $s, (string)$r->input('remarks', '')));
    }

    public function resend(Request $r): Response
    {
        $c = $this->ctx();
        $this->auth->requirePermission($c, 'distributorOnboarding', 'resendRevokeInvite');
        $f = $c->requireFranchise();
        $ref = (string)$r->param('ref');

        $stmt = $this->pdo->prepare("SELECT * FROM onboarding_invites WHERE franchise_ref = ? AND invite_ref = ? LIMIT 1");
        $stmt->execute([$f, $ref]);
        $invite = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$invite) throw new NotFoundException('INVITE_NOT_FOUND', 'Onboarding invite not found.');
        if ($invite['used_at']) throw new ConflictException('INVITE_ALREADY_USED', 'Used invites cannot be resent.');

        $token = bin2hex(random_bytes(24));
        $expires = date('Y-m-d H:i:s', time() + 259200);
        $this->pdo->prepare("UPDATE onboarding_invites SET token_hash = ?, expires_at = ?, created_at = NOW() WHERE franchise_ref = ? AND invite_ref = ?")
            ->execute([hash('sha256', $token), $expires, $f, $ref]);

        $audit = Container::getInstance()->make(AuditService::class);
        $audit->log($c, 'BUSINESS', 'onboarding.invite_resent', 'onboarding_invite', $ref, null, ['expires_at' => $expires]);

        return Response::json(200, [
            'invite_ref' => $ref,
            'token'      => $token,
            'expires_at' => $expires,
            'resent'     => true,
        ]);
    }

    public function revoke(Request $r): Response
    {
        $c = $this->ctx();
        $this->auth->requirePermission($c, 'distributorOnboarding', 'resendRevokeInvite');
        $f = $c->requireFranchise();
        $ref = (string)$r->param('ref');

        $stmt = $this->pdo->prepare("SELECT * FROM onboarding_invites WHERE franchise_ref = ? AND invite_ref = ? LIMIT 1");
        $stmt->execute([$f, $ref]);
        $invite = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$invite) throw new NotFoundException('INVITE_NOT_FOUND', 'Onboarding invite not found.');

        $this->pdo->prepare("UPDATE onboarding_invites SET expires_at = NOW() WHERE franchise_ref = ? AND invite_ref = ?")->execute([$f, $ref]);

        $audit = Container::getInstance()->make(AuditService::class);
        $audit->log($c, 'BUSINESS', 'onboarding.invite_revoked', 'onboarding_invite', $ref, null, ['revoked' => true]);

        return Response::json(200, ['invite_ref' => $ref, 'revoked' => true]);
    }

    public function documents(Request $r): Response
    {
        $c = $this->ctx();
        $this->auth->requirePermission($c, 'distributorOnboarding', 'view');
        $this->access($c, $r);
        $detail = $this->service->detail($c, (string)$r->param('ref'));
        return Response::json(200, $detail['documents'] ?? []);
    }

    public function timeline(Request $r): Response
    {
        $c = $this->ctx();
        $this->auth->requirePermission($c, 'distributorOnboarding', 'view');
        $this->access($c, $r);
        $detail = $this->service->detail($c, (string)$r->param('ref'));
        return Response::json(200, $detail['history'] ?? []);
    }
}
