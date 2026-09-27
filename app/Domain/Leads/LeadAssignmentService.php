<?php
declare(strict_types=1);
namespace App\Domain\Leads;

use App\Core\Database;
use App\Domain\Authorization\AuthorizationService;

final class LeadAssignmentService
{
    public function __construct(private Database $db, private AuthorizationService $authorization) {}

    /**
     * Round-robin assign lead to an active field user in the franchise.
     *
     * B1 — "field user" is no longer users.role = 'SALES'. It is anyone who can
     * work a lead (an ACTIVE role granting leads.edit) without franchise-wide
     * lead scope (effective leads scope ≠ ALL), i.e. people who own their leads.
     * For the seeded roles this is the same set as before (Sales Team → OWN;
     * Admin → ALL, excluded), and it now includes custom field roles too.
     */
    public function assignNext(string $orgRef, string $franchiseRef): ?string
    {
        // 1. Candidates: active users holding leads.edit through an active role.
        $candidates = $this->db->fetchAll(
            "SELECT DISTINCT u.user_ref
             FROM users u
             JOIN auth_user_roles ur ON ur.user_ref = u.user_ref
             JOIN auth_roles r ON r.role_ref = ur.role_ref AND r.status = 'ACTIVE' AND r.franchise_ref = u.franchise_ref
             JOIN auth_role_permissions rp ON rp.role_ref = r.role_ref
             JOIN auth_permissions p ON p.permission_ref = rp.permission_ref AND p.module_key = 'leads' AND p.action_key = 'edit'
             WHERE u.franchise_ref = :f AND u.status = 'ACTIVE'
             ORDER BY u.user_ref ASC",
            [':f' => $franchiseRef]
        );

        // 2. Keep only users whose effective leads scope is narrower than ALL.
        $userRefs = [];
        foreach (array_column($candidates, 'user_ref') as $userRef) {
            $effective = $this->authorization->effectiveForUser($userRef, $franchiseRef);
            if (($effective['scopes']['leads'] ?? 'NONE') !== 'ALL') {
                $userRefs[] = $userRef;
            }
        }

        if (empty($userRefs)) {
            return null;
        }

        $count = count($userRefs);

        // 2. Fetch current pointer from system_settings
        $pointer = $this->db->fetchColumn(
            "SELECT setting_value FROM system_settings WHERE franchise_ref = :f AND setting_key = 'lead_assign_pointer' LIMIT 1",
            [':f' => $franchiseRef]
        );

        $nextIndex = 0;
        if ($pointer !== null && $pointer !== false) {
            $currentIndex = array_search($pointer, $userRefs, true);
            if ($currentIndex !== false) {
                $nextIndex = ($currentIndex + 1) % $count;
            }
        }

        $nextUserRef = $userRefs[$nextIndex];

        // 3. Update system_settings pointer
        $this->db->prepare(
            "INSERT INTO system_settings (org_ref, franchise_ref, setting_key, setting_value)
             VALUES (:o, :f, 'lead_assign_pointer', :val)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()"
        )->execute([
            ':o'   => $orgRef,
            ':f'   => $franchiseRef,
            ':val' => $nextUserRef,
        ]);

        return $nextUserRef;
    }
}
