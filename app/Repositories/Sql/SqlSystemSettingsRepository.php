<?php
declare(strict_types=1);
namespace App\Repositories\Sql;

use App\Core\Database;
use App\Repositories\Contracts\SystemSettingsRepositoryInterface;

final class SqlSystemSettingsRepository implements SystemSettingsRepositoryInterface
{
    public function __construct(private Database $db) {}

    public function list(string $franchiseRef): array
    {
        return $this->db->fetchAll(
            "SELECT setting_key, setting_value, updated_at FROM system_settings WHERE franchise_ref = ?",
            [$franchiseRef]
        );
    }

    public function get(string $franchiseRef, string $key, ?string $default = null): ?string
    {
        $val = $this->db->fetchColumn(
            "SELECT setting_value FROM system_settings WHERE franchise_ref = ? AND setting_key = ? LIMIT 1",
            [$franchiseRef, $key]
        );
        return ($val !== false && $val !== null) ? (string)$val : $default;
    }

    public function set(string $orgRef, string $franchiseRef, string $key, string $value, string $actorRef): bool
    {
        $existing = $this->db->fetchOne(
            "SELECT id FROM system_settings WHERE franchise_ref = ? AND setting_key = ? LIMIT 1",
            [$franchiseRef, $key]
        );

        if ($existing) {
            return $this->db->update(
                'system_settings',
                ['setting_value' => $value, 'updated_by_ref' => $actorRef],
                'franchise_ref = ? AND setting_key = ?',
                [$franchiseRef, $key]
            ) > 0;
        }

        $this->db->insert('system_settings', [
            'org_ref'        => $orgRef,
            'franchise_ref'  => $franchiseRef,
            'setting_key'    => $key,
            'setting_value'  => $value,
            'updated_by_ref' => $actorRef,
        ]);
        return true;
    }
}
