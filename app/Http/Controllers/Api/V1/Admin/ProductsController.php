<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api\V1\Admin;

use App\Core\{Request, Response, Container, Validation, TenantContext, RefGenerator};
use App\Repositories\Contracts\{ProductRepositoryInterface, ProductCategoryRepositoryInterface};
use App\Domain\Audit\AuditService;
use App\Core\Exceptions\{NotFoundException, ConflictException, ForbiddenException, BusinessRuleException};

final class ProductsController
{
    public function __construct(
        private ProductRepositoryInterface $products,
        private ProductCategoryRepositoryInterface $categories,
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

    public function index(Request $r): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();

        $page = (int) $r->query('page', '1');
        $perPage = (int) $r->query('per_page', '25');
        $filters = [
            'status'       => $r->query('status', ''),
            'category_ref' => $r->query('category_ref', ''),
            'search'       => $r->query('search', ''),
        ];

        $res = $this->products->list($franchiseRef, $filters, $page, $perPage);
        return Response::json(200, $res['data'], $res['meta']);
    }

    public function show(Request $r): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();
        $ref = $r->param('ref');

        $prod = $this->products->findByRef($franchiseRef, $ref);
        if (!$prod) {
            throw new NotFoundException('PRODUCT_NOT_FOUND', "Product {$ref} not found.");
        }

        return Response::json(200, $prod);
    }

    public function create(Request $r): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();

        $clean = Validation::validate($r->all(), [
            'sku'            => 'required|string',
            'product_name'   => 'required|string',
            'franchise_rate' => 'required|numeric',
        ]);

        $sku = strtoupper(trim($clean['sku']));
        if ($this->products->findBySku($franchiseRef, $sku)) {
            throw new ConflictException('DUPLICATE_SKU', "Product with SKU '{$sku}' already exists in this franchise.");
        }

        $categoryRef = $r->input('category_ref');
        if ($categoryRef && !$this->categories->findByRef($franchiseRef, $categoryRef)) {
            throw new NotFoundException('CATEGORY_NOT_FOUND', "Category {$categoryRef} not found.");
        }

        $prodRef = RefGenerator::make('PRD');
        $data = [
            'product_ref'         => $prodRef,
            'org_ref'             => $ctx->orgRef,
            'franchise_ref'       => $franchiseRef,
            'sku'                 => $sku,
            'product_name'        => trim($clean['product_name']),
            'category_ref'        => $categoryRef,
            'composition'         => $r->input('composition'),
            'pack_size'           => $r->input('pack_size'),
            'dosage_form'         => $r->input('dosage_form'),
            'mrp'                 => (float)$r->input('mrp', 0.0),
            'pts'                 => (float)$r->input('pts', 0.0),
            'franchise_rate'      => (float)$clean['franchise_rate'],
            'gst_percent'         => (float)$r->input('gst_percent', 12.0),
            'hsn_code'            => $r->input('hsn_code'),
            'shelf_life_days'     => (int)$r->input('shelf_life_days', 0),
            'storage_requirement' => $r->input('storage_requirement'),
            'scheme_eligible'     => $r->input('scheme_eligible', 0) ? 1 : 0,
            'status'              => 'ACTIVE',
            'created_by_ref'      => $ctx->userRef,
            'created_at'          => date('Y-m-d H:i:s'),
        ];

        $this->products->create($data);
        $this->audit->log(
            ctx: $ctx,
            category: 'BUSINESS',
            action: 'product.created',
            entityType: 'product',
            entityRef: $prodRef,
            after: $data
        );

        return Response::json(201, $data);
    }

    public function update(Request $r): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();
        $ref = $r->param('ref');

        $prod = $this->products->findByRef($franchiseRef, $ref);
        if (!$prod) {
            throw new NotFoundException('PRODUCT_NOT_FOUND', "Product {$ref} not found.");
        }

        $allowed = [
            'product_name', 'category_ref', 'composition', 'pack_size', 'dosage_form',
            'mrp', 'pts', 'franchise_rate', 'gst_percent', 'hsn_code', 'shelf_life_days',
            'storage_requirement', 'scheme_eligible'
        ];

        $updates = [];
        foreach ($allowed as $f) {
            if ($r->has($f)) {
                $updates[$f] = $r->input($f);
            }
        }

        if (!empty($updates)) {
            $updates['updated_by_ref'] = $ctx->userRef;
            $this->products->update($franchiseRef, $ref, $updates);
            $this->audit->log(
                ctx: $ctx,
                category: 'BUSINESS',
                action: 'product.updated',
                entityType: 'product',
                entityRef: $ref,
                before: $prod,
                after: array_merge($prod, $updates)
            );
        }

        return Response::json(200, $this->products->findByRef($franchiseRef, $ref));
    }

    public function activate(Request $r): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();
        $ref = $r->param('ref');

        $prod = $this->products->findByRef($franchiseRef, $ref);
        if (!$prod) throw new NotFoundException('PRODUCT_NOT_FOUND', "Product {$ref} not found.");

        $this->products->setStatus($franchiseRef, $ref, 'ACTIVE');
        $this->audit->log(ctx: $ctx, category: 'BUSINESS', action: 'product.activated', entityType: 'product', entityRef: $ref);

        return Response::json(200, ['status' => 'ACTIVE']);
    }

    public function deactivate(Request $r): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();
        $ref = $r->param('ref');

        $prod = $this->products->findByRef($franchiseRef, $ref);
        if (!$prod) throw new NotFoundException('PRODUCT_NOT_FOUND', "Product {$ref} not found.");

        $this->products->setStatus($franchiseRef, $ref, 'INACTIVE');
        $this->audit->log(ctx: $ctx, category: 'BUSINESS', action: 'product.deactivated', entityType: 'product', entityRef: $ref);

        return Response::json(200, ['status' => 'INACTIVE']);
    }

    public function archive(Request $r): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();
        $ref = $r->param('ref');

        $prod = $this->products->findByRef($franchiseRef, $ref);
        if (!$prod) throw new NotFoundException('PRODUCT_NOT_FOUND', "Product {$ref} not found.");

        // Rule: products are archived, NEVER hard deleted
        $this->products->setStatus($franchiseRef, $ref, 'ARCHIVED');
        $this->audit->log(ctx: $ctx, category: 'BUSINESS', action: 'product.archived', entityType: 'product', entityRef: $ref);

        return Response::json(200, ['status' => 'ARCHIVED']);
    }
}
