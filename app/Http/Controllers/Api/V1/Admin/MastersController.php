<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api\V1\Admin;

use App\Core\{Request, Response, Container, Validation, TenantContext, RefGenerator, QueryParams};
use App\Repositories\Contracts\{
    ProductCategoryRepositoryInterface,
    PricingTierRepositoryInterface,
    TransporterRepositoryInterface,
    SystemSettingsRepositoryInterface,
    NotificationTemplateRepositoryInterface
};
use App\Domain\Audit\AuditService;
use App\Domain\Authorization\AuthorizationService;
use App\Core\Exceptions\{NotFoundException, ConflictException, ForbiddenException, ValidationException};

final class MastersController
{
    public function __construct(
        private ProductCategoryRepositoryInterface $categories,
        private PricingTierRepositoryInterface $tiers,
        private TransporterRepositoryInterface $transporters,
        private SystemSettingsRepositoryInterface $settings,
        private NotificationTemplateRepositoryInterface $templates,
        private AuditService $audit,
        private AuthorizationService $authorization,
    ) {}

    private function getCtx(): TenantContext
    {
        /** @var TenantContext $ctx */
        $ctx = Container::getInstance()->make(TenantContext::class);
        // B1 — no role-name gate: every action below checks its masters/products/pricing/schemes permission key.
        return $ctx;
    }

    // ── Categories ──────────────────────────────────────────────────────────

    public function listCategories(Request $r): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();
        $this->authorization->requirePermission($ctx, 'masters', 'view');
        $query = QueryParams::fromRequest($r);
        $page = $query['page']; $perPage = $query['per_page'];
        $filters = [
            'status' => $query['status'], 'search' => $query['search'],
        ];

        $res = $this->categories->list($franchiseRef, $filters, $page, $perPage);
        return Response::json(200, $res['data'], $res['meta']);
    }

    public function createCategory(Request $r): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();
        $this->authorization->requirePermission($ctx, 'masters', 'create');
        $clean = Validation::validate($r->all(), [
            'category_name' => 'required|string',
        ]);

        $name = trim($clean['category_name']);
        if ($this->categories->findByName($franchiseRef, $name)) {
            throw new ConflictException('DUPLICATE_CATEGORY', "Category '{$name}' already exists in this franchise.");
        }

        $catRef = RefGenerator::make('CAT');
        $data = [
            'category_ref'   => $catRef,
            'org_ref'        => $ctx->orgRef,
            'franchise_ref'  => $franchiseRef,
            'category_name'  => $name,
            'status'         => 'ACTIVE',
            'created_by_ref' => $ctx->userRef,
            'created_at'     => date('Y-m-d H:i:s'),
        ];

        $this->categories->create($data);
        $this->audit->log(
            ctx: $ctx,
            category: 'BUSINESS',
            action: 'category.created',
            entityType: 'product_category',
            entityRef: $catRef,
            after: $data
        );

        return Response::json(201, $data);
    }

    public function showCategory(Request $r): Response
    {
        $ctx = $this->getCtx(); $this->authorization->requirePermission($ctx, 'masters', 'view');
        $row = $this->categories->findByRef($ctx->requireFranchise(), $r->param('ref'));
        if (!$row) throw new NotFoundException('CATEGORY_NOT_FOUND', 'Category not found.');
        return Response::json(200, $row);
    }

    public function updateCategory(Request $r): Response
    {
        $ctx = $this->getCtx(); $franchiseRef = $ctx->requireFranchise();
        $this->authorization->requirePermission($ctx, 'masters', 'edit');
        $ref = $r->param('ref'); if (!$this->categories->findByRef($franchiseRef, $ref)) throw new NotFoundException('CATEGORY_NOT_FOUND', 'Category not found.');
        $clean = Validation::validate($r->all(), ['category_name' => 'required|string']);
        $name = trim($clean['category_name']); $existing = $this->categories->findByName($franchiseRef, $name);
        if ($existing && $existing['category_ref'] !== $ref) throw new ConflictException('DUPLICATE_CATEGORY', 'Category already exists.');
        $this->categories->update($franchiseRef, $ref, ['category_name' => $name]);
        return Response::json(200, $this->categories->findByRef($franchiseRef, $ref));
    }

    public function categoryStatus(Request $r): Response
    {
        $ctx = $this->getCtx(); $franchiseRef = $ctx->requireFranchise(); $ref = $r->param('ref');
        $this->authorization->requirePermission($ctx, 'masters', 'activateDeactivate');
        if (!$this->categories->findByRef($franchiseRef, $ref)) throw new NotFoundException('CATEGORY_NOT_FOUND', 'Category not found.');
        $status = strtoupper((string)$r->input('status', '')); if (!in_array($status, ['ACTIVE', 'INACTIVE'], true)) throw new ValidationException('INVALID_STATUS', 'status must be ACTIVE or INACTIVE.');
        $this->categories->update($franchiseRef, $ref, ['status' => $status]); return Response::json(200, ['category_ref' => $ref, 'status' => $status]);
    }

    // ── Pricing Tiers ───────────────────────────────────────────────────────

    public function listTiers(Request $r): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();
        $this->authorization->requirePermission($ctx, 'masters', 'view');
        $query = QueryParams::fromRequest($r);
        $page = $query['page']; $perPage = $query['per_page'];
        $filters = [
            'status' => $query['status'], 'search' => $query['search'],
        ];

        $res = $this->tiers->list($franchiseRef, $filters, $page, $perPage);
        return Response::json(200, $res['data'], $res['meta']);
    }

    public function createTier(Request $r): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();
        $this->authorization->requirePermission($ctx, 'masters', 'create');
        $clean = Validation::validate($r->all(), [
            'tier_name' => 'required|string',
        ]);

        $name = strtoupper(trim($clean['tier_name']));
        if ($this->tiers->findByName($franchiseRef, $name)) {
            throw new ConflictException('DUPLICATE_TIER', "Pricing tier '{$name}' already exists in this franchise.");
        }

        $tierRef = RefGenerator::make('TIR');
        $data = [
            'tier_ref'       => $tierRef,
            'org_ref'        => $ctx->orgRef,
            'franchise_ref'  => $franchiseRef,
            'tier_name'      => $name,
            'status'         => 'ACTIVE',
            'created_by_ref' => $ctx->userRef,
            'created_at'     => date('Y-m-d H:i:s'),
        ];

        $this->tiers->create($data);
        $this->audit->log(
            ctx: $ctx,
            category: 'BUSINESS',
            action: 'tier.created',
            entityType: 'pricing_tier',
            entityRef: $tierRef,
            after: $data
        );

        return Response::json(201, $data);
    }

    public function showTier(Request $r): Response
    {
        $ctx = $this->getCtx(); $this->authorization->requirePermission($ctx, 'masters', 'view');
        $row = $this->tiers->findByRef($ctx->requireFranchise(), $r->param('ref'));
        if (!$row) throw new NotFoundException('TIER_NOT_FOUND', 'Pricing tier not found.');
        return Response::json(200, $row);
    }

    public function updateTier(Request $r): Response
    {
        $ctx = $this->getCtx(); $franchiseRef = $ctx->requireFranchise(); $this->authorization->requirePermission($ctx, 'masters', 'edit');
        $ref = $r->param('ref'); if (!$this->tiers->findByRef($franchiseRef, $ref)) throw new NotFoundException('TIER_NOT_FOUND', 'Pricing tier not found.');
        $clean = Validation::validate($r->all(), ['tier_name' => 'required|string']); $name = strtoupper(trim($clean['tier_name']));
        $existing = $this->tiers->findByName($franchiseRef, $name); if ($existing && $existing['tier_ref'] !== $ref) throw new ConflictException('DUPLICATE_TIER', 'Pricing tier already exists.');
        $this->tiers->update($franchiseRef, $ref, ['tier_name' => $name]); return Response::json(200, $this->tiers->findByRef($franchiseRef, $ref));
    }

    public function tierStatus(Request $r): Response
    {
        $ctx = $this->getCtx(); $franchiseRef = $ctx->requireFranchise(); $ref = $r->param('ref'); $this->authorization->requirePermission($ctx, 'masters', 'activateDeactivate');
        if (!$this->tiers->findByRef($franchiseRef, $ref)) throw new NotFoundException('TIER_NOT_FOUND', 'Pricing tier not found.');
        $status = strtoupper((string)$r->input('status', '')); if (!in_array($status, ['ACTIVE', 'INACTIVE'], true)) throw new ValidationException('INVALID_STATUS', 'status must be ACTIVE or INACTIVE.');
        $this->tiers->update($franchiseRef, $ref, ['status' => $status]); return Response::json(200, ['tier_ref' => $ref, 'status' => $status]);
    }

    // ── Transporters ────────────────────────────────────────────────────────

    public function listTransporters(Request $r): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();
        $this->authorization->requirePermission($ctx, 'masters', 'view');
        $page = (int) $r->query('page', '1');
        $perPage = (int) $r->query('per_page', '50');
        $filters = [
            'status' => $r->query('status', ''),
            'search' => $r->query('search', ''),
        ];

        $res = $this->transporters->list($franchiseRef, $filters, $page, $perPage);
        return Response::json(200, $res['data'], $res['meta']);
    }

    public function createTransporter(Request $r): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();
        $this->authorization->requirePermission($ctx, 'masters', 'create');
        $clean = Validation::validate($r->all(), [
            'transporter_name' => 'required|string',
        ]);

        $name = trim($clean['transporter_name']);
        if ($this->transporters->findByName($franchiseRef, $name)) {
            throw new ConflictException('DUPLICATE_TRANSPORTER', "Transporter '{$name}' already exists in this franchise.");
        }

        $trnRef = RefGenerator::make('TRN');
        $data = [
            'transporter_ref'       => $trnRef,
            'org_ref'               => $ctx->orgRef,
            'franchise_ref'         => $franchiseRef,
            'transporter_name'      => $name,
            'tracking_url_template' => $r->input('tracking_url_template'),
            'status'                => 'ACTIVE',
        ];

        $this->transporters->create($data);
        $this->audit->log(
            ctx: $ctx,
            category: 'BUSINESS',
            action: 'transporter.created',
            entityType: 'transporter',
            entityRef: $trnRef,
            after: $data
        );

        return Response::json(201, $data);
    }

    public function showTransporter(Request $r): Response
    {
        $ctx = $this->getCtx();
        $this->authorization->requirePermission($ctx, 'masters', 'view');
        $row = $this->transporters->findByRef($ctx->requireFranchise(), (string)$r->param('ref'));
        if (!$row) throw new NotFoundException('TRANSPORTER_NOT_FOUND', 'Transporter not found.');
        return Response::json(200, $row);
    }

    public function updateTransporter(Request $r): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();
        $this->authorization->requirePermission($ctx, 'masters', 'edit');
        $ref = (string)$r->param('ref');
        $existing = $this->transporters->findByRef($franchiseRef, $ref);
        if (!$existing) throw new NotFoundException('TRANSPORTER_NOT_FOUND', 'Transporter not found.');

        $data = [];
        if ($r->has('transporter_name')) {
            $name = trim((string)$r->input('transporter_name'));
            $dup = $this->transporters->findByName($franchiseRef, $name);
            if ($dup && $dup['transporter_ref'] !== $ref) {
                throw new ConflictException('DUPLICATE_TRANSPORTER', "Transporter '{$name}' already exists.");
            }
            $data['transporter_name'] = $name;
        }
        if ($r->has('tracking_url_template')) {
            $data['tracking_url_template'] = $r->input('tracking_url_template');
        }

        if (!empty($data)) {
            $this->transporters->update($franchiseRef, $ref, $data);
        }

        return Response::json(200, $this->transporters->findByRef($franchiseRef, $ref));
    }

    public function transporterStatus(Request $r): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();
        $this->authorization->requirePermission($ctx, 'masters', 'activateDeactivate');
        $ref = (string)$r->param('ref');
        if (!$this->transporters->findByRef($franchiseRef, $ref)) {
            throw new NotFoundException('TRANSPORTER_NOT_FOUND', 'Transporter not found.');
        }

        $status = strtoupper((string)$r->input('status', ''));
        if (!in_array($status, ['ACTIVE', 'INACTIVE'], true)) {
            throw new ValidationException('INVALID_STATUS', 'status must be ACTIVE or INACTIVE.');
        }

        $this->transporters->update($franchiseRef, $ref, ['status' => $status]);
        return Response::json(200, ['transporter_ref' => $ref, 'status' => $status]);
    }

    // ── Settings ────────────────────────────────────────────────────────────

    public function listSettings(Request $r): Response
    {
        $ctx = $this->getCtx();
        $this->authorization->requirePermission($ctx, 'settings', 'view');
        $rows = $this->settings->list($ctx->requireFranchise());
        return Response::json(200, $rows);
    }

    // ── Notification Templates ──────────────────────────────────────────────

    public function listTemplates(Request $r): Response
    {
        $ctx = $this->getCtx();
        $this->authorization->requirePermission($ctx, 'notifications', 'manageTemplates');
        $rows = $this->templates->list($ctx->requireFranchise());
        return Response::json(200, $rows);
    }
}
