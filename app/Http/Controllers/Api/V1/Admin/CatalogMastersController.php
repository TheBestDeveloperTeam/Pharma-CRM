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
    private const KEYS = ['dosageForms' => 'DOSAGE_FORM', 'schemeTypes' => 'SCHEME_TYPE'];

    public function __construct(private CatalogMasterRepositoryInterface $masters, private AuditService $audit, private AuthorizationService $authorization) {}

    private function context(): TenantContext
    {
        /** @var TenantContext $ctx */
        $ctx = Container::getInstance()->make(TenantContext::class);
        if (!$ctx->isAdmin() && !$ctx->isSuper()) throw new ForbiddenException('FORBIDDEN', 'Franchise Admin permission required.');
        return $ctx;
    }

    private function key(Request $r): string
    {
        $key = (string)$r->param('category', '');
        if (!isset(self::KEYS[$key])) throw new NotFoundException('MASTER_NOT_FOUND', 'Product master category not found.');
        return self::KEYS[$key];
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
