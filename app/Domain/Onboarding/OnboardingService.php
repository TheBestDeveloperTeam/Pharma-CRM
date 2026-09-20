<?php
declare(strict_types=1);
namespace App\Domain\Onboarding;

use App\Core\Database;
use App\Core\RefGenerator;
use App\Core\Exceptions\ValidationException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Security\PasswordHasher;
use App\Domain\Parties\PartyService;
use App\Domain\Leads\LeadService;

final class OnboardingService
{
    public function __construct(
        private Database $db,
        private PartyService $partyService,
        private LeadService $leadService,
        private PasswordHasher $hasher,
    ) {}

    /**
     * Issue an onboarding invite link (valid for 72h)
     */
    public function issueInvite(string $orgRef, string $franchiseRef, ?string $leadRef, string $actorRef): array
    {
        // 48-byte random token
        $rawToken = bin2hex(random_bytes(24));
        $tokenHash = hash('sha256', $rawToken);
        $inviteRef = RefGenerator::generate('INV');
        $expiresAt = date('Y-m-d H:i:s', time() + (72 * 3600));

        $sql = "INSERT INTO onboarding_invites (
            invite_ref, org_ref, franchise_ref, lead_ref, token_hash, expires_at, created_by_ref
        ) VALUES (
            :invite_ref, :org_ref, :franchise_ref, :lead_ref, :token_hash, :expires_at, :created_by_ref
        )";

        $this->db->prepare($sql)->execute([
            ':invite_ref'     => $inviteRef,
            ':org_ref'        => $orgRef,
            ':franchise_ref'  => $franchiseRef,
            ':lead_ref'       => $leadRef,
            ':token_hash'     => $tokenHash,
            ':expires_at'     => $expiresAt,
            ':created_by_ref' => $actorRef,
        ]);

        return [
            'invite_ref' => $inviteRef,
            'token'      => $rawToken, // Provided once to admin/inviter
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * Complete partner registration via invite token
     */
    public function completeRegistration(string $rawToken, array $formData): array
    {
        $tokenHash = hash('sha256', $rawToken);

        $invite = $this->db->fetchOne(
            "SELECT * FROM onboarding_invites WHERE token_hash = :h LIMIT 1",
            [':h' => $tokenHash]
        );

        if (!$invite) {
            throw new NotFoundException('INVALID_INVITE_TOKEN', 'Onboarding invite token is invalid.');
        }

        if (!empty($invite['used_at'])) {
            throw new ValidationException('INVITE_ALREADY_USED', 'This invite link has already been used.');
        }

        if (strtotime($invite['expires_at']) < time()) {
            throw new ValidationException('INVITE_EXPIRED', 'This onboarding invite link has expired.');
        }

        $orgRef       = $invite['org_ref'];
        $franchiseRef = $invite['franchise_ref'];
        $leadRef      = $invite['lead_ref'];

        // Begin transaction
        $this->db->beginTransaction();
        try {
            // 1. Create party
            $partyData = [
                'org_ref'                 => $orgRef,
                'franchise_ref'           => $franchiseRef,
                'party_code'              => $formData['party_code'] ?? null,
                'firm_name'               => $formData['firm_name'],
                'contact_name'            => $formData['contact_name'] ?? null,
                'mobile'                  => $formData['mobile'] ?? null,
                'email'                   => $formData['email'] ?? null,
                'gstin'                   => $formData['gstin'] ?? null,
                'drug_license_no'         => $formData['drug_license_no'] ?? null,
                'billing_address'         => $formData['billing_address'] ?? null,
                'shipping_address'        => $formData['shipping_address'] ?? null,
                'state_ref'               => $formData['state_ref'] ?? null,
                'district_ref'            => $formData['district_ref'] ?? null,
                'city_ref'                => $formData['city_ref'] ?? null,
                'pincode'                 => $formData['pincode'] ?? null,
                'converted_from_lead_ref' => $leadRef,
                'status'                  => 'ACTIVE',
                'created_by_ref'          => 'ONBOARDING',
            ];

            $partyRef = $this->partyService->create($partyData);

            // 2. Create distributor portal user
            $userRef = RefGenerator::generate('USR');
            $rawPass = bin2hex(random_bytes(6));
            $passHash = $this->hasher->hash($formData['password'] ?? $rawPass);

            $this->db->prepare(
                "INSERT INTO users (
                    user_ref, org_ref, franchise_ref, role, party_ref,
                    full_name, email, mobile, password_hash,
                    must_change_password, status, created_by_ref
                ) VALUES (
                    :user_ref, :org_ref, :franchise_ref, 'DISTRIBUTOR', :party_ref,
                    :full_name, :email, :mobile, :password_hash,
                    1, 'ACTIVE', 'ONBOARDING'
                )"
            )->execute([
                ':user_ref'      => $userRef,
                ':org_ref'       => $orgRef,
                ':franchise_ref' => $franchiseRef,
                ':party_ref'     => $partyRef,
                ':full_name'     => $formData['contact_name'] ?? $formData['firm_name'],
                ':email'         => strtolower($formData['email']),
                ':mobile'        => $formData['mobile'] ?? null,
                ':password_hash' => $passHash,
            ]);

            // 3. Mark invite as used
            $this->db->prepare("UPDATE onboarding_invites SET used_at = NOW() WHERE id = :id")->execute([':id' => $invite['id']]);

            // 4. If linked to a lead, transition lead to CONVERTED
            if ($leadRef) {
                $this->leadService->changeStatus(
                    $franchiseRef,
                    $leadRef,
                    'CONVERTED',
                    'ONBOARDING',
                    'Lead converted to party via onboarding registration.',
                    $partyRef
                );
            }

            $this->db->commit();

            return [
                'party_ref' => $partyRef,
                'user_ref'  => $userRef,
                'email'     => $formData['email'],
                'status'    => 'SUCCESS',
            ];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
