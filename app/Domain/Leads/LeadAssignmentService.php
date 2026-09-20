<?php
declare(strict_types=1);
namespace App\Domain\Leads;

use App\Core\Database;

final class LeadAssignmentService
{
    public function __construct(private Database $db) {}

    /**
     * Round-robin assign lead to active sales user in franchise
     */
    public function assignNext(string $orgRef, string $franchiseRef): ?string
    {
        // 1. Fetch active sales users ordered by user_ref
        $salesUsers = $this->db->fetchAll(
            "SELECT user_ref FROM users WHERE franchise_ref = :f AND role = 'SALES' AND status = 'ACTIVE' ORDER BY user_ref ASC",
            [':f' => $franchiseRef]
        );

        if (empty($salesUsers)) {
            return null;
        }

        $userRefs = array_column($salesUsers, 'user_ref');
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
