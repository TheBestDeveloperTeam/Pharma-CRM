<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api\V1\Admin;

use App\Core\{Container, QueryParams, RefGenerator, Request, Response, TenantContext, Validation};
use App\Core\Exceptions\{ConflictException, ForbiddenException, NotFoundException};
use App\Domain\Audit\AuditService;
use App\Domain\Authorization\AuthorizationService;
use App\Repositories\Contracts\CatalogMasterRepositoryInterface;

final class CatalogMastersController
{
    /** @var array<string,string> */
    private const KEYS = [
        'dosageForms'            => 'DOSAGE_FORM',
        'dosage-forms'           => 'DOSAGE_FORM',
        'dosage_forms'           => 'DOSAGE_FORM',
        'schemeTypes'            => 'SCHEME_TYPE',
        'scheme-types'           => 'SCHEME_TYPE',
        'scheme_types'           => 'SCHEME_TYPE',
        'leadSources'            => 'LEAD_SOURCE',
        'lead-sources'           => 'LEAD_SOURCE',
        'lead_sources'           => 'LEAD_SOURCE',
        'leadStatuses'           => 'LEAD_STATUS',
        'lead-statuses'          => 'LEAD_STATUS',
        'lead_statuses'          => 'LEAD_STATUS',
        'partyTypes'             => 'PARTY_TYPE',
        'party-types'            => 'PARTY_TYPE',
        'party_types'            => 'PARTY_TYPE',
        'businessTypes'          => 'PARTY_TYPE',
        'business-types'         => 'PARTY_TYPE',
        'business_types'         => 'PARTY_TYPE',
        'constitutionTypes'      => 'CONSTITUTION_TYPE',
        'constitution-types'     => 'CONSTITUTION_TYPE',
        'constitution_types'     => 'CONSTITUTION_TYPE',
        'packagingTypes'         => 'PACKAGING_TYPE',
        'packaging-types'        => 'PACKAGING_TYPE',
        'packaging_types'        => 'PACKAGING_TYPE',
        'uoms'                   => 'UOM',
        'units'                  => 'UOM',
        'paymentModes'           => 'PAYMENT_MODE',
        'payment-modes'          => 'PAYMENT_MODE',
        'payment_modes'          => 'PAYMENT_MODE',
        'workTypes'              => 'WORK_TYPE',
        'work-types'             => 'WORK_TYPE',
        'work_types'             => 'WORK_TYPE',
        'followUpTypes'          => 'FOLLOWUP_TYPE',
        'follow-up-types'        => 'FOLLOWUP_TYPE',
        'follow_up_types'        => 'FOLLOWUP_TYPE',
        'followup_types'         => 'FOLLOWUP_TYPE',
        'stockAdjustmentReasons' => 'STOCK_ADJUSTMENT_REASON',
        'stock-adjustment-reasons' => 'STOCK_ADJUSTMENT_REASON',
        'stock_adjustment_reasons' => 'STOCK_ADJUSTMENT_REASON',
        'transporterModes'       => 'TRANSPORTER_MODE',
        'transporter-modes'      => 'TRANSPORTER_MODE',
        'transporter_modes'      => 'TRANSPORTER_MODE',
    ];

    public function __construct(
        private CatalogMasterRepositoryInterface $masters,
        private AuditService $audit,
        private AuthorizationService $authorization
    ) {}

    private function context(): TenantContext
    {
        /** @var TenantContext $ctx */
        $ctx = Container::getInstance()->make(TenantContext::class);
        return $ctx;
    }

    private function key(Request $r): string
    {
        $raw = trim((string)$r->param('category', ''));
        if (isset(self::KEYS[$raw])) {
            return self::KEYS[$raw];
        }

        // Normalize camelCase, kebab-case, or snake_case to UPPER_SNAKE_CASE
        $normalized = strtoupper(preg_replace('/(?<!^)[A-Z]/', '_$0', str_replace('-', '_', $raw)));
        if (!empty($normalized)) {
            return $normalized;
        }

        throw new NotFoundException('MASTER_NOT_FOUND', "Catalog master category '{$raw}' not found.");
    }

    /**
     * List all catalog master values grouped by category for zero-local-data pre-fetching.
     */
    public function listAll(Request $r): Response
    {
        $ctx = $this->context();
        $this->authorization->requirePermission($ctx, 'masters', 'view');
        $franchiseRef = $ctx->requireFranchise();

        // Read all active master values for the franchise
        $container = Container::getInstance();
        /** @var \App\Core\Database $db */
        $db = $container->make(\App\Core\Database::class);

        $rows = $db->fetchAll(
            "SELECT master_ref, master_key, name, description, status
             FROM catalog_master_values
             WHERE franchise_ref = ? AND status = 'ACTIVE'
             ORDER BY master_key ASC, name ASC",
            [$franchiseRef]
        );

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['master_key']][] = [
                'master_ref'  => $row['master_ref'],
                'code'        => $row['name'],
                'label'       => $row['name'],
                'description' => $row['description'],
            ];
        }

        return Response::json(200, $grouped);
    }

    public function index(Request $r): Response
    {
        $ctx = $this->context();
        $this->authorization->requirePermission($ctx, 'masters', 'view');
        $query = QueryParams::fromRequest($r, ['name', 'created_at']);
        $res = $this->masters->list($ctx->requireFranchise(), $this->key($r), $query, $query['page'], $query['per_page']);
        return Response::json(200, $res['data'], $res['meta']);
    }

    public function show(Request $r): Response
    {
        $ctx = $this->context();
        $this->authorization->requirePermission($ctx, 'masters', 'view');
        $row = $this->masters->findByRef($ctx->requireFranchise(), (string)$r->param('ref'));
        if (!$row || $row['master_key'] !== $this->key($r)) throw new NotFoundException('MASTER_NOT_FOUND', 'Master value not found.');
        return Response::json(200, $row);
    }

    public function create(Request $r): Response
    {
        $ctx = $this->context();
        $this->authorization->requirePermission($ctx, 'masters', 'create');
        $franchiseRef = $ctx->requireFranchise();
        $key = $this->key($r);
        $clean = Validation::validate($r->all(), ['name' => 'required|string|min:2|max:191']);
        $name = trim($clean['name']);
        if ($this->masters->findByName($franchiseRef, $key, $name)) throw new ConflictException('DUPLICATE_MASTER', "Master value '{$name}' already exists.");
        $data = [
            'master_ref' => RefGenerator::make('MST'), 'org_ref' => $ctx->orgRef, 'franchise_ref' => $franchiseRef,
            'master_key' => $key, 'name' => $name, 'description' => $r->input('description'), 'status' => 'ACTIVE',
            'created_by_ref' => $ctx->userRef, 'created_at' => date('Y-m-d H:i:s'),
        ];
        $this->masters->create($data);
        $this->audit->log(ctx: $ctx, category: 'BUSINESS', action: 'master.created', entityType: 'catalog_master', entityRef: $data['master_ref'], after: $data);
        return Response::json(201, $data);
    }

    public function update(Request $r): Response
    {
        $ctx = $this->context();
        $this->authorization->requirePermission($ctx, 'masters', 'edit');
        $ref = (string)$r->param('ref');
        $old = $this->masters->findByRef($ctx->requireFranchise(), $ref);
        if (!$old || $old['master_key'] !== $this->key($r)) throw new NotFoundException('MASTER_NOT_FOUND', 'Master value not found.');
        $clean = Validation::validate($r->all(), ['name' => 'required|string|min:2|max:191']);
        $data = ['name' => trim($clean['name']), 'description' => $r->input('description'), 'updated_by_ref' => $ctx->userRef];
        $this->masters->update($ctx->requireFranchise(), $ref, $data);
        $this->audit->log(ctx: $ctx, category: 'BUSINESS', action: 'master.updated', entityType: 'catalog_master', entityRef: $ref, before: $old, after: array_merge($old, $data));
        return Response::json(200, $this->masters->findByRef($ctx->requireFranchise(), $ref));
    }

    public function status(Request $r): Response
    {
        $ctx = $this->context();
        $this->authorization->requirePermission($ctx, 'masters', 'activateDeactivate');
        $ref = (string)$r->param('ref');
        $old = $this->masters->findByRef($ctx->requireFranchise(), $ref);
        if (!$old || $old['master_key'] !== $this->key($r)) throw new NotFoundException('MASTER_NOT_FOUND', 'Master value not found.');
        $clean = Validation::validate($r->all(), ['status' => 'required|enum:ACTIVE,INACTIVE']);
        $this->masters->update($ctx->requireFranchise(), $ref, ['status' => $clean['status'], 'updated_by_ref' => $ctx->userRef]);
        $this->audit->log(ctx: $ctx, category: 'BUSINESS', action: 'master.status_changed', entityType: 'catalog_master', entityRef: $ref, before: $old, after: array_merge($old, ['status' => $clean['status']]));
        return Response::json(200, ['master_ref' => $ref, 'status' => $clean['status']]);
    }
}
