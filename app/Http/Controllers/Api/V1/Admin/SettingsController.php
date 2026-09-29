<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api\V1\Admin;

use App\Core\{Request, Response, Container, Validation, TenantContext};
use App\Core\Exceptions\{ForbiddenException, ValidationException};
use App\Domain\Audit\AuditService;

final class SettingsController
{
    public function __construct(
        private \PDO $pdo,
        private AuditService $audit,
    ) {}

    public function show(Request $r): Response
    {
        /** @var TenantContext $ctx */
        $ctx = Container::getInstance()->make(TenantContext::class);
        if (!$ctx->can('settings', 'view')) {
            throw new ForbiddenException('FORBIDDEN', 'Permission required: settings.view');
        }

        $franchiseRef = $ctx->requireFranchise();
        $stmt = $this->pdo->prepare('SELECT franchise_ref, franchise_code, franchise_name, gstin, drug_license_no, address, state_ref, brand_primary_hex, brand_accent_hex, status FROM franchises WHERE franchise_ref = :f LIMIT 1');
        $stmt->execute([':f' => $franchiseRef]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return Response::json(200, $row ?: []);
    }

    public function update(Request $r): Response
    {
        /** @var TenantContext $ctx */
        $ctx = Container::getInstance()->make(TenantContext::class);
        // B1 — permission key instead of the FRANCHISE_ADMIN role name.
        if (!$ctx->can('settings', 'edit')) {
            throw new ForbiddenException('FORBIDDEN', 'Permission required: settings.edit');
        }

        $franchiseRef = $ctx->requireFranchise();

        $accentHex = $r->input('brand_accent_hex');
        if ($accentHex !== null) {
            $hex = strtoupper(trim((string)$accentHex));
            if (!preg_match('/^#[0-9A-F]{6}$/i', $hex)) {
                throw new ValidationException('INVALID_HEX', 'Accent hex must be a valid 6-character hex code like #FF7A00.');
            }

            // Verify contrast against background #F4FBF8 (relative luminance)
            $contrast = $this->calculateContrast($hex, '#F4FBF8');
            if ($contrast < 3.0) { // minimum readable ratio for accent
                throw new ValidationException('INSUFFICIENT_CONTRAST', "Contrast ratio {$contrast} is below required threshold.");
            }

            // Update franchise record
            $stmt = $this->pdo->prepare("UPDATE franchises SET brand_accent_hex = :h WHERE franchise_ref = :f");
            $stmt->execute([':h' => $hex, ':f' => $franchiseRef]);

            // Generate tenant CSS: public/assets/tenant/{franchise_ref}.css
            $dir = dirname(__DIR__, 5) . '/public/assets/tenant';
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }

            $cssPath = $dir . '/' . $franchiseRef . '.css';
            $cssContent = "/* Auto-generated. Do not edit manually. */\n:root[data-theme=\"theme-admin\"] { --acc: {$hex}; }\n";
            file_put_contents($cssPath, $cssContent);

            $this->audit->log(
                ctx: $ctx,
                category: 'BUSINESS',
                action: 'franchise.branding_updated',
                entityType: 'franchise',
                entityRef: $franchiseRef,
                after: ['brand_accent_hex' => $hex]
            );

            return Response::json(200, [
                'franchise_ref'    => $franchiseRef,
                'brand_accent_hex' => $hex,
                'css_url'          => "/assets/tenant/{$franchiseRef}.css",
            ]);
        }

        // Update franchise legal & location settings (BE-091 fix)
        $stateRef = $r->input('state_ref');
        $franchiseName = $r->input('franchise_name');
        $gstin = $r->input('gstin');
        $address = $r->input('address');
        $drugLicense = $r->input('drug_license_no');

        $updates = [];
        $params = [];

        if ($stateRef !== null) {
            $updates[] = 'state_ref = :state_ref';
            $params[':state_ref'] = trim((string)$stateRef);
        }
        if ($franchiseName !== null) {
            $updates[] = 'franchise_name = :franchise_name';
            $params[':franchise_name'] = trim((string)$franchiseName);
        }
        if ($gstin !== null) {
            $updates[] = 'gstin = :gstin';
            $params[':gstin'] = strtoupper(trim((string)$gstin));
        }
        if ($address !== null) {
            $updates[] = 'address = :address';
            $params[':address'] = trim((string)$address);
        }
        if ($drugLicense !== null) {
            $updates[] = 'drug_license_no = :drug_license_no';
            $params[':drug_license_no'] = trim((string)$drugLicense);
        }

        if (!empty($updates)) {
            $params[':f'] = $franchiseRef;
            $sql = 'UPDATE franchises SET ' . implode(', ', $updates) . ' WHERE franchise_ref = :f';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            $this->audit->log(
                ctx: $ctx,
                category: 'BUSINESS',
                action: 'franchise.settings_updated',
                entityType: 'franchise',
                entityRef: $franchiseRef,
                after: $params
            );
        }

        return Response::json(200, ['updated' => true, 'franchise_ref' => $franchiseRef]);
    }

    private function calculateContrast(string $c1, string $c2): float
    {
        $l1 = $this->luminance($c1);
        $l2 = $this->luminance($c2);
        $lighter = max($l1, $l2);
        $darker  = min($l1, $l2);
        return round(($lighter + 0.05) / ($darker + 0.05), 2);
    }

    private function luminance(string $hex): float
    {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2)) / 255;
        $g = hexdec(substr($hex, 2, 2)) / 255;
        $b = hexdec(substr($hex, 4, 2)) / 255;

        $transform = fn($c) => $c <= 0.04045 ? $c / 12.92 : pow(($c + 0.055) / 1.055, 2.4);
        return 0.2126 * $transform($r) + 0.7152 * $transform($g) + 0.0722 * $transform($b);
    }

    // --- System Settings Helpers (SET-001 to SET-016) ---

    private function getSystemSetting(string $key, array $defaults): array
    {
        /** @var TenantContext $ctx */
        $ctx = Container::getInstance()->make(TenantContext::class);
        $franchiseRef = $ctx->requireFranchise();

        $stmt = $this->pdo->prepare("SELECT setting_value_json FROM system_settings WHERE franchise_ref = :f AND setting_key = :k LIMIT 1");
        $stmt->execute([':f' => $franchiseRef, ':k' => $key]);
        $val = $stmt->fetchColumn();

        if ($val === false || $val === null) {
            return $defaults;
        }

        $decoded = json_decode((string)$val, true);
        return is_array($decoded) ? array_merge($defaults, $decoded) : $defaults;
    }

    private function saveSystemSetting(string $key, array $data, string $action): array
    {
        /** @var TenantContext $ctx */
        $ctx = Container::getInstance()->make(TenantContext::class);
        if (!$ctx->can('settings', 'edit')) {
            throw new ForbiddenException('FORBIDDEN', 'Permission required: settings.edit');
        }

        $franchiseRef = $ctx->requireFranchise();
        $json = json_encode($data, JSON_UNESCAPED_SLASHES);

        $stmt = $this->pdo->prepare("INSERT INTO system_settings (org_ref, franchise_ref, setting_key, setting_value_json) 
            VALUES (:org, :f, :k, :val) 
            ON DUPLICATE KEY UPDATE setting_value_json = VALUES(setting_value_json), updated_at = NOW()");
        $stmt->execute([
            ':org' => $ctx->orgRef,
            ':f'   => $franchiseRef,
            ':k'   => $key,
            ':val' => $json,
        ]);

        $this->audit->log(
            ctx: $ctx,
            category: 'BUSINESS',
            action: 'settings.' . $action,
            entityType: 'setting',
            entityRef: $key,
            after: $data
        );

        return $data;
    }

    // SET-001 & SET-002: Near-expiry thresholds
    public function getNearExpiryThresholds(Request $r): Response
    {
        return Response::json(200, $this->getSystemSetting('near_expiry_thresholds', [
            'days_threshold' => 180,
            'levels'         => [180, 90, 60, 30],
            'action'         => 'WARN',
        ]));
    }

    public function updateNearExpiryThresholds(Request $r): Response
    {
        $data = $r->all();
        return Response::json(200, $this->saveSystemSetting('near_expiry_thresholds', $data, 'near_expiry_thresholds_updated'));
    }

    // SET-003 & SET-004: SLA configuration
    public function getSla(Request $r): Response
    {
        return Response::json(200, $this->getSystemSetting('sla_policy', [
            'response_time_hours' => 4,
            'escalation_hours'    => 8,
            'auto_reassign'       => false,
        ]));
    }

    public function updateSla(Request $r): Response
    {
        $data = $r->all();
        return Response::json(200, $this->saveSystemSetting('sla_policy', $data, 'sla_policy_updated'));
    }

    // SET-005 & SET-006: Territory policy
    public function getTerritoryPolicy(Request $r): Response
    {
        return Response::json(200, $this->getSystemSetting('territory_policy', [
            'unassigned_pincode_action' => 'BLOCK',
            'allow_override'            => true,
            'require_approval'          => true,
        ]));
    }

    public function updateTerritoryPolicy(Request $r): Response
    {
        $data = $r->all();
        return Response::json(200, $this->saveSystemSetting('territory_policy', $data, 'territory_policy_updated'));
    }

    // SET-007 & SET-008: Credit policy
    public function getCreditPolicy(Request $r): Response
    {
        return Response::json(200, $this->getSystemSetting('credit_policy', [
            'mode'                 => 'HARD_BLOCK',
            'grace_period_days'    => 7,
            'max_overdue_invoices' => 3,
        ]));
    }

    public function updateCreditPolicy(Request $r): Response
    {
        $data = $r->all();
        return Response::json(200, $this->saveSystemSetting('credit_policy', $data, 'credit_policy_updated'));
    }

    // SET-009 & SET-010: DCR config
    public function getDcrConfig(Request $r): Response
    {
        return Response::json(200, $this->getSystemSetting('dcr_config', [
            'cutoff_time'         => '23:00',
            'allow_backdate_days' => 1,
            'require_gps'         => false,
            'require_visits'      => true,
        ]));
    }

    public function updateDcrConfig(Request $r): Response
    {
        $data = $r->all();
        return Response::json(200, $this->saveSystemSetting('dcr_config', $data, 'dcr_config_updated'));
    }

    // SET-011 & SET-012: Invite config
    public function getInviteConfig(Request $r): Response
    {
        return Response::json(200, $this->getSystemSetting('invite_config', [
            'token_validity_days' => 7,
            'required_documents'  => ['DRUG_LICENSE', 'GST_CERTIFICATE', 'PAN_CARD'],
        ]));
    }

    public function updateInviteConfig(Request $r): Response
    {
        $data = $r->all();
        return Response::json(200, $this->saveSystemSetting('invite_config', $data, 'invite_config_updated'));
    }

    // SET-013 & SET-014: Scheme stacking
    public function getSchemeStacking(Request $r): Response
    {
        return Response::json(200, $this->getSystemSetting('scheme_stacking', [
            'allow_stacking' => false,
            'priority_order' => ['PRODUCT_SPECIFIC', 'CATEGORY_WIDE', 'GLOBAL'],
        ]));
    }

    public function updateSchemeStacking(Request $r): Response
    {
        $data = $r->all();
        return Response::json(200, $this->saveSystemSetting('scheme_stacking', $data, 'scheme_stacking_updated'));
    }

    // SET-015 & SET-016: Min shelf-life
    public function getMinShelfLife(Request $r): Response
    {
        return Response::json(200, $this->getSystemSetting('min_shelf_life', [
            'min_shelf_life_months'  => 6,
            'min_shelf_life_percent' => 60,
            'enforce_at_dispatch'    => true,
        ]));
    }

    public function updateMinShelfLife(Request $r): Response
    {
        $data = $r->all();
        return Response::json(200, $this->saveSystemSetting('min_shelf_life', $data, 'min_shelf_life_updated'));
    }
}
