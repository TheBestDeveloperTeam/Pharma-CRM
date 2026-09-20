<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api\V1\Admin;

use App\Core\{Request, Response, Container, Validation, TenantContext, RefGenerator};
use App\Repositories\Contracts\{
    ProductCategoryRepositoryInterface,
    PricingTierRepositoryInterface,
    TransporterRepositoryInterface,
    SystemSettingsRepositoryInterface,
    NotificationTemplateRepositoryInterface
};
use App\Domain\Audit\AuditService;
use App\Core\Exceptions\{NotFoundException, ConflictException, ForbiddenException};

final class MastersController
{
    public function __construct(
        private ProductCategoryRepositoryInterface $categories,
        private PricingTierRepositoryInterface $tiers,
        private TransporterRepositoryInterface $transporters,
        private SystemSettingsRepositoryInterface $settings,
        private NotificationTemplateRepositoryInterface $templates,
        private AuditService $audit,
    ) {}

    private function getCtx(): TenantContext
    {
        /** @var TenantContext $ctx */
        $ctx = Container::getInstance()->make(TenantContext::class);
        if (!$ctx->isAdmin() && !$ctx->isSuper()) {
            throw new ForbiddenException('FORBIDDEN', 'Franchise Admin permission required.');
        }
        return $ctx;
    }

    // ── Categories ──────────────────────────────────────────────────────────

    public function listCategories(Request $r): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();
        $page = (int) $r->query('page', '1');
        $perPage = (int) $r->query('per_page', '50');
        $filters = [
            'status' => $r->query('status', ''),
            'search' => $r->query('search', ''),
        ];

        $res = $this->categories->list($franchiseRef, $filters, $page, $perPage);
        return Response::json(200, $res['data'], $res['meta']);
    }

    public function createCategory(Request $r): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();
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

    // ── Pricing Tiers ───────────────────────────────────────────────────────

    public function listTiers(Request $r): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();
        $page = (int) $r->query('page', '1');
        $perPage = (int) $r->query('per_page', '50');
        $filters = [
            'status' => $r->query('status', ''),
            'search' => $r->query('search', ''),
        ];

        $res = $this->tiers->list($franchiseRef, $filters, $page, $perPage);
        return Response::json(200, $res['data'], $res['meta']);
    }

    public function createTier(Request $r): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();
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

    // ── Transporters ────────────────────────────────────────────────────────

    public function listTransporters(Request $r): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();
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

    // ── Settings ────────────────────────────────────────────────────────────

    public function listSettings(Request $r): Response
    {
        $ctx = $this->getCtx();
        $rows = $this->settings->list($ctx->requireFranchise());
        return Response::json(200, $rows);
    }

    // ── Notification Templates ──────────────────────────────────────────────

    public function listTemplates(Request $r): Response
    {
        $ctx = $this->getCtx();
        $rows = $this->templates->list($ctx->requireFranchise());
        return Response::json(200, $rows);
    }
}
