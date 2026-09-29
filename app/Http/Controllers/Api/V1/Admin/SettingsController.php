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
}
