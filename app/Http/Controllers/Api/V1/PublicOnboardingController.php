<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Core\{Container, Request, Response};
use App\Core\Exceptions\{NotFoundException, ConflictException, ValidationException};
use App\Domain\Onboarding\OnboardingService;

final class PublicOnboardingController
{
    public function __construct(
        private OnboardingService $service,
        private ?\PDO $pdo = null
    ) {
        $this->pdo = $pdo ?? Container::getInstance()->make(\PDO::class);
    }

    public function register(Request $r): Response
    {
        return Response::json(201, $this->service->register((string)$r->input('token'), $r->all()));
    }

    public function validateToken(Request $r): Response
    {
        $token = (string)($r->query('token', $r->input('token', '')));
        if ($token === '') {
            throw new ValidationException('TOKEN_REQUIRED', 'Onboarding token is required.');
        }

        $tokenHash = hash('sha256', $token);
        $stmt = $this->pdo->prepare("SELECT * FROM onboarding_invites WHERE token_hash = ? LIMIT 1");
        $stmt->execute([$tokenHash]);
        $invite = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$invite) {
            throw new NotFoundException('INVALID_INVITE_TOKEN', 'Onboarding invite token is invalid.');
        }

        if ($invite['used_at']) {
            throw new ConflictException('INVITE_ALREADY_USED', 'This onboarding invite has already been used.');
        }

        if (strtotime($invite['expires_at']) < time()) {
            throw new ValidationException('INVITE_EXPIRED', 'This onboarding invite has expired.');
        }

        return Response::json(200, [
            'valid'             => true,
            'invite_ref'        => $invite['invite_ref'],
            'lead_ref'          => $invite['lead_ref'],
            'assigned_user_ref' => $invite['assigned_user_ref'],
            'expires_at'        => $invite['expires_at'],
        ]);
    }

    public function inviteContext(Request $r): Response
    {
        $token = (string)($r->query('token', $r->input('token', '')));
        if ($token === '') {
            throw new ValidationException('TOKEN_REQUIRED', 'Onboarding token is required.');
        }

        $tokenHash = hash('sha256', $token);
        $stmt = $this->pdo->prepare("SELECT * FROM onboarding_invites WHERE token_hash = ? LIMIT 1");
        $stmt->execute([$tokenHash]);
        $invite = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$invite) {
            throw new NotFoundException('INVALID_INVITE_TOKEN', 'Onboarding invite token is invalid.');
        }

        if ($invite['used_at']) {
            throw new ConflictException('INVITE_ALREADY_USED', 'This onboarding invite has already been used.');
        }

        if (strtotime($invite['expires_at']) < time()) {
            throw new ValidationException('INVITE_EXPIRED', 'This onboarding invite has expired.');
        }

        $frnStmt = $this->pdo->prepare("SELECT franchise_ref, franchise_name, state_ref FROM franchises WHERE franchise_ref = ? LIMIT 1");
        $frnStmt->execute([$invite['franchise_ref']]);
        $franchise = $frnStmt->fetch(\PDO::FETCH_ASSOC);

        $lead = null;
        if (!empty($invite['lead_ref'])) {
            $leadStmt = $this->pdo->prepare("SELECT lead_ref, lead_number, firm_name, contact_name, mobile, email, gstin, drug_license_no, state_ref, district_ref, city_ref, pincode FROM leads WHERE franchise_ref = ? AND lead_ref = ? LIMIT 1");
            $leadStmt->execute([$invite['franchise_ref'], $invite['lead_ref']]);
            $lead = $leadStmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        }

        $catStmt = $this->pdo->prepare("SELECT category_ref, category_name FROM categories WHERE franchise_ref = ? AND status = 'ACTIVE' ORDER BY category_name ASC");
        $catStmt->execute([$invite['franchise_ref']]);
        $categories = $catStmt->fetchAll(\PDO::FETCH_ASSOC);

        return Response::json(200, [
            'valid'      => true,
            'invite'     => [
                'invite_ref' => $invite['invite_ref'],
                'expires_at' => $invite['expires_at'],
            ],
            'franchise'  => $franchise ?: null,
            'lead'       => $lead,
            'categories' => $categories,
        ]);
    }
}
