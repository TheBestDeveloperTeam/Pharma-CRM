<?php

declare(strict_types=1);

namespace App\Domain\Authorization;

use App\Core\Database;

/**
 * B1 — provisions the three baseline roles every franchise needs and assigns
 * them from a user's legacy surface role (users.role still decides WHICH
 * login surface a user may use; it no longer decides WHAT they may do).
 *
 * Role/permission refs are deterministic and identical to the ones migrations
 * 002 and 012 generate, so code and SQL seeding never create duplicates.
 *
 *   admin       — protected system role, default scope ALL, every catalogue key.
 *   sales-team  — default scope OWN, exactly the grant list of migration 002.
 *   distributor — default scope OWN, portal self-service keys (migration 012).
 */
final class SystemRoles
{
    public const ADMIN = 'admin';
    public const SALES_TEAM = 'sales-team';
    public const DISTRIBUTOR = 'distributor';

    /** Migration 002's sales-team grant list — keep in sync with it. */
    private const SALES_TEAM_PERMISSIONS = [
        ['leads', 'view'], ['leads', 'create'], ['leads', 'edit'], ['leads', 'convert'],
        ['followUps', 'view'], ['followUps', 'create'], ['followUps', 'edit'], ['followUps', 'complete'], ['followUps', 'reschedule'],
        ['parties', 'view'], ['parties', 'create'], ['parties', 'edit'],
        ['orders', 'view'], ['orders', 'create'], ['orders', 'editDraft'], ['orders', 'submit'],
        ['payments', 'view'], ['payments', 'create'], ['payments', 'edit'],
        ['dashboard', 'view'], ['reports', 'view'],
    ];

    /** Migration 012's distributor grant list — the portal self-service surface. */
    private const DISTRIBUTOR_PERMISSIONS = [
        ['portal', 'view'], ['portal', 'placeOrder'], ['portal', 'editProfile'],
    ];

    /** Legacy users.role → baseline role slug. SUPER_ADMIN has no franchise role (platform bypass). */
    private const LEGACY_ROLE_MAP = [
        'FRANCHISE_ADMIN' => self::ADMIN,
        'SALES' => self::SALES_TEAM,
        'DISTRIBUTOR' => self::DISTRIBUTOR,
    ];

    public function __construct(private readonly Database $db) {}

    public static function roleRef(string $franchiseRef, string $slug): string
    {
        return 'ROLE-' . strtoupper(substr(hash('sha256', $franchiseRef . ':' . $slug), 0, 20));
    }

    private static function permissionRef(string $module, string $action): string
    {
        return 'PER-' . strtoupper(substr(hash('sha256', $module . ':' . $action), 0, 20));
    }

    /** Idempotent: creates missing baseline roles and their grants for one franchise. */
    public function ensureForFranchise(string $orgRef, string $franchiseRef, string $actorRef): void
    {
        // Every catalogue key must exist in auth_permissions before it can be granted.
        $this->db->prepare(
            "INSERT IGNORE INTO auth_permissions (permission_ref, module_key, action_key, label, is_sensitive)
             SELECT CONCAT('PER-', UPPER(SUBSTRING(SHA2(CONCAT(module_key, ':', action_key), 256), 1, 20))), module_key, action_key, label, is_sensitive
             FROM auth_permission_catalogue"
        )->execute();

        // Grants are applied ONLY when the role is created here. An existing role is
        // never topped up by code: that would silently hand out keys it never had.
        if ($this->ensureRole($orgRef, $franchiseRef, self::ADMIN, 'Admin', 'Protected full-access system role', true, 'ALL', $actorRef)) {
            // Admin: every catalogue key (same as migration 002's CROSS JOIN).
            $this->db->prepare(
                "INSERT IGNORE INTO auth_role_permissions (role_ref, permission_ref, granted_by_ref)
                 SELECT :r, p.permission_ref, :a FROM auth_permissions p"
            )->execute([':r' => self::roleRef($franchiseRef, self::ADMIN), ':a' => $actorRef]);
        }
        if ($this->ensureRole($orgRef, $franchiseRef, self::SALES_TEAM, 'Sales Team', 'Default own-scope sales role', false, 'OWN', $actorRef)) {
            $this->grant(self::roleRef($franchiseRef, self::SALES_TEAM), self::SALES_TEAM_PERMISSIONS, $actorRef);
        }
        if ($this->ensureRole($orgRef, $franchiseRef, self::DISTRIBUTOR, 'Distributor', 'Distributor portal self-service', false, 'OWN', $actorRef)) {
            $this->grant(self::roleRef($franchiseRef, self::DISTRIBUTOR), self::DISTRIBUTOR_PERMISSIONS, $actorRef);
        }
    }

    /** Assigns the baseline role that matches a user's legacy surface role (no-op for SUPER_ADMIN). */
    public function assignForLegacyRole(string $userRef, string $legacyRole, string $orgRef, string $franchiseRef, string $actorRef): void
    {
        $slug = self::LEGACY_ROLE_MAP[$legacyRole] ?? null;
        if ($slug === null) return;
        $this->ensureForFranchise($orgRef, $franchiseRef, $actorRef);
        // Resolve by slug (unique per franchise), not by the derived ref, in case the role pre-dates deterministic refs.
        $roleRef = $this->db->fetchColumn('SELECT role_ref FROM auth_roles WHERE franchise_ref = ? AND role_slug = ? LIMIT 1', [$franchiseRef, $slug]);
        if (!$roleRef) return;
        $this->db->prepare('INSERT IGNORE INTO auth_user_roles (user_ref, role_ref, assigned_by_ref) VALUES (:u, :r, :a)')
            ->execute([':u' => $userRef, ':r' => $roleRef, ':a' => $actorRef]);
    }

    /** @return bool true when the role was created by this call */
    private function ensureRole(string $orgRef, string $franchiseRef, string $slug, string $name, string $description, bool $isSystem, string $scope, string $actorRef): bool
    {
        $stmt = $this->db->prepare(
            "INSERT IGNORE INTO auth_roles (role_ref, org_ref, franchise_ref, role_name, role_slug, description, is_system, status, default_scope, created_by_ref)
             VALUES (:ref, :o, :f, :n, :s, :d, :sys, 'ACTIVE', :scope, :a)"
        );
        $stmt->execute([
            ':ref' => self::roleRef($franchiseRef, $slug), ':o' => $orgRef, ':f' => $franchiseRef, ':n' => $name, ':s' => $slug,
            ':d' => $description, ':sys' => $isSystem ? 1 : 0, ':scope' => $scope, ':a' => $actorRef,
        ]);
        return $stmt->rowCount() > 0;
    }

    /** @param array<int,array{0:string,1:string}> $permissions */
    private function grant(string $roleRef, array $permissions, string $actorRef): void
    {
        $stmt = $this->db->prepare('INSERT IGNORE INTO auth_role_permissions (role_ref, permission_ref, granted_by_ref) VALUES (:r, :p, :a)');
        foreach ($permissions as [$module, $action]) {
            $stmt->execute([':r' => $roleRef, ':p' => self::permissionRef($module, $action), ':a' => $actorRef]);
        }
    }
}
