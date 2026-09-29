<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api\V1\Admin;

use App\Core\{Request, Response, Container, Validation, TenantContext, RefGenerator, QueryParams};
use App\Repositories\Contracts\{ProductRepositoryInterface, ProductCategoryRepositoryInterface};
use App\Domain\Audit\AuditService;
use App\Domain\Authorization\AuthorizationService;
use App\Core\Exceptions\{NotFoundException, ConflictException, ForbiddenException};

final class ProductsController
{
    public function __construct(
        private ProductRepositoryInterface $products,
        private ProductCategoryRepositoryInterface $categories,
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

    public function index(Request $r): Response
    {
        $ctx = $this->getCtx();
        $this->authorization->requirePermission($ctx, 'products', 'view');
        $franchiseRef = $ctx->requireFranchise();

        $query = QueryParams::fromRequest($r, ['created_at', 'product_name', 'sku']);
        $page = $query['page'];
        $perPage = $query['per_page'];
        $filters = [
            'status'       => $r->query('status', ''),
            'category_ref' => $r->query('category_ref', ''),
            'search'       => $query['search'],
        ];

        $res = $this->products->list($franchiseRef, $filters, $page, $perPage);
        return Response::json(200, $res['data'], $res['meta']);
    }

    public function show(Request $r): Response
    {
        $ctx = $this->getCtx();
        $this->authorization->requirePermission($ctx, 'products', 'view');
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
        $this->authorization->requirePermission($ctx, 'products', 'create');
        $franchiseRef = $ctx->requireFranchise();

        $clean = Validation::validate($r->all(), [
            'sku'            => 'required|string',
            'product_name'   => 'required|string',
            'franchise_rate' => 'required|numeric|min:0',
            'mrp'            => 'numeric|min:0',
            'pts'            => 'numeric|min:0',
            'gst_percent'    => 'numeric|min:0|max:100',
            'shelf_life_days' => 'integer|min:0',
            'availability'   => 'enum:IN_STOCK,LOW_STOCK,OUT_OF_STOCK',
        ]);

        $sku = strtoupper(trim($clean['sku']));
        if ($this->products->findBySku($franchiseRef, $sku)) {
            throw new ConflictException('DUPLICATE_SKU', "Product with SKU '{$sku}' already exists in this franchise.");
        }

        $categoryRef = $r->input('category_ref');
        $category = $categoryRef ? $this->categories->findByRef($franchiseRef, $categoryRef) : null;
        if ($categoryRef && (!$category || $category['status'] !== 'ACTIVE')) {
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
            'mrp'                 => (float)($clean['mrp'] ?? 0.0),
            'pts'                 => (float)($clean['pts'] ?? 0.0),
            'franchise_rate'      => (float)$clean['franchise_rate'],
            'gst_percent'         => (float)($clean['gst_percent'] ?? 12.0),
            'hsn_code'            => $r->input('hsn_code'),
            'shelf_life_days'     => (int)($clean['shelf_life_days'] ?? 0),
            'storage_requirement' => $r->input('storage_requirement'),
            'scheme_eligible'     => $r->input('scheme_eligible', 0) ? 1 : 0,
            'description'         => $r->input('description'),
            'availability'        => $clean['availability'] ?? 'IN_STOCK',
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
        $this->authorization->requirePermission($ctx, 'products', 'edit');
        $franchiseRef = $ctx->requireFranchise();
        $ref = $r->param('ref');

        $prod = $this->products->findByRef($franchiseRef, $ref);
        if (!$prod) {
            throw new NotFoundException('PRODUCT_NOT_FOUND', "Product {$ref} not found.");
        }

        $clean = Validation::validate($r->all(), [
            'product_name' => 'string|min:2', 'mrp' => 'numeric|min:0', 'pts' => 'numeric|min:0',
            'franchise_rate' => 'numeric|min:0', 'gst_percent' => 'numeric|min:0|max:100',
            'shelf_life_days' => 'integer|min:0', 'availability' => 'enum:IN_STOCK,LOW_STOCK,OUT_OF_STOCK',
        ]);
        $categoryRef = $r->input('category_ref');
        if ($categoryRef !== null) {
            $category = $this->categories->findByRef($franchiseRef, (string)$categoryRef);
            if (!$category || $category['status'] !== 'ACTIVE') throw new NotFoundException('CATEGORY_NOT_FOUND', "Category {$categoryRef} not found.");
        }

        $allowed = [
            'product_name', 'category_ref', 'composition', 'pack_size', 'dosage_form',
            'mrp', 'pts', 'franchise_rate', 'gst_percent', 'hsn_code', 'shelf_life_days',
            'storage_requirement', 'scheme_eligible', 'description', 'availability'
        ];

        $updates = [];
        foreach ($allowed as $f) {
            if ($r->has($f)) {
                $updates[$f] = $clean[$f] ?? $r->input($f);
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
        $this->authorization->requirePermission($ctx, 'products', 'activateDeactivate');
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
        $this->authorization->requirePermission($ctx, 'products', 'activateDeactivate');
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
        $this->authorization->requirePermission($ctx, 'products', 'archive');
        $franchiseRef = $ctx->requireFranchise();
        $ref = $r->param('ref');

        $prod = $this->products->findByRef($franchiseRef, $ref);
        if (!$prod) throw new NotFoundException('PRODUCT_NOT_FOUND', "Product {$ref} not found.");

        // Rule: products are archived, NEVER hard deleted
        $this->products->setStatus($franchiseRef, $ref, 'ARCHIVED');
        $this->audit->log(ctx: $ctx, category: 'BUSINESS', action: 'product.archived', entityType: 'product', entityRef: $ref);

        return Response::json(200, ['status' => 'ARCHIVED']);
    }

    public function restore(Request $r): Response
    {
        $ctx = $this->getCtx();
        $this->authorization->requirePermission($ctx, 'products', 'archive');
        $franchiseRef = $ctx->requireFranchise();
        $ref = (string)$r->param('ref');
        $product = $this->products->findByRef($franchiseRef, $ref);
        if (!$product) throw new NotFoundException('PRODUCT_NOT_FOUND', "Product {$ref} not found.");
        $this->products->setStatus($franchiseRef, $ref, 'ACTIVE');
        $this->audit->log(ctx: $ctx, category: 'BUSINESS', action: 'product.restored', entityType: 'product', entityRef: $ref, before: $product, after: array_merge($product, ['status' => 'ACTIVE']));
        return Response::json(200, ['product_ref' => $ref, 'status' => 'ACTIVE']);
    }

    public function delete(Request $r): Response
    {
        $ctx = $this->getCtx();
        $this->authorization->requirePermission($ctx, 'products', 'archive');
        $franchiseRef = $ctx->requireFranchise();
        $ref = (string)$r->param('ref');
        $product = $this->products->findByRef($franchiseRef, $ref);
        if (!$product) throw new NotFoundException('PRODUCT_NOT_FOUND', "Product {$ref} not found.");
        if ($this->products->isReferenced($franchiseRef, $ref)) throw new ConflictException('PRODUCT_REFERENCED', 'Referenced products cannot be physically deleted; archive the product instead.');
        $this->products->delete($franchiseRef, $ref);
        $this->audit->log(ctx: $ctx, category: 'BUSINESS', action: 'product.deleted', entityType: 'product', entityRef: $ref, before: $product);
        return Response::json(200, ['product_ref' => $ref, 'deleted' => true]);
    }

    /** UTL-003: Lightweight product list for order form autocomplete */
    public function dropdown(Request $r): Response
    {
        $ctx = $this->getCtx();
        $this->authorization->requirePermission($ctx, 'products', 'view');
        $franchiseRef = $ctx->requireFranchise();

        $res = $this->products->list($franchiseRef, ['status' => 'ACTIVE'], 1, 500);
        $items = array_map(function($p) {
            return [
                'product_ref'  => $p['product_ref'],
                'product_name' => $p['product_name'],
                'sku'          => $p['sku'],
                'mrp'          => $p['mrp'] ?? 0,
                'ptr'          => $p['ptr'] ?? 0,
                'pts'          => $p['pts'] ?? 0,
                'category_ref' => $p['category_ref'] ?? null,
                'status'       => $p['status'] ?? 'ACTIVE',
            ];
        }, $res['data'] ?? []);

        return Response::json(200, $items);
    }

    public function batches(Request $r): Response
    {
        $ctx = $this->getCtx();
        $this->authorization->requirePermission($ctx, 'products', 'view');
        $f = $ctx->requireFranchise();
        $ref = (string)$r->param('ref');
        $product = $this->products->findByRef($f, $ref);
        if (!$product) throw new NotFoundException('PRODUCT_NOT_FOUND', "Product {$ref} not found.");

        $pdo = Container::getInstance()->make(\PDO::class);
        $stmt = $pdo->prepare(
            "SELECT * FROM inventory_batches WHERE franchise_ref = ? AND product_ref = ? ORDER BY expiry_date ASC"
        );
        $stmt->execute([$f, $ref]);
        return Response::json(200, $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function prices(Request $r): Response
    {
        $ctx = $this->getCtx();
        $this->authorization->requirePermission($ctx, 'products', 'view');
        $f = $ctx->requireFranchise();
        $ref = (string)$r->param('ref');
        $product = $this->products->findByRef($f, $ref);
        if (!$product) throw new NotFoundException('PRODUCT_NOT_FOUND', "Product {$ref} not found.");

        $pdo = Container::getInstance()->make(\PDO::class);
        $stmt = $pdo->prepare(
            "SELECT pr.*, t.tier_name, pty.firm_name as party_name
             FROM product_prices pr
             LEFT JOIN pricing_tiers t ON t.franchise_ref = pr.franchise_ref AND t.tier_ref = pr.tier_ref
             LEFT JOIN parties pty ON pty.franchise_ref = pr.franchise_ref AND pty.party_ref = pr.party_ref
             WHERE pr.franchise_ref = ? AND pr.product_ref = ?
             ORDER BY pr.priority ASC, pr.effective_from DESC"
        );
        $stmt->execute([$f, $ref]);
        return Response::json(200, $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function schemes(Request $r): Response
    {
        $ctx = $this->getCtx();
        $this->authorization->requirePermission($ctx, 'products', 'view');
        $f = $ctx->requireFranchise();
        $ref = (string)$r->param('ref');
        $product = $this->products->findByRef($f, $ref);
        if (!$product) throw new NotFoundException('PRODUCT_NOT_FOUND', "Product {$ref} not found.");

        $pdo = Container::getInstance()->make(\PDO::class);
        $stmt = $pdo->prepare(
            "SELECT s.*, r.min_qty, r.max_qty, r.free_qty, t.tier_name
             FROM scheme_rules r
             JOIN schemes s ON s.franchise_ref = r.franchise_ref AND s.scheme_ref = r.scheme_ref
             LEFT JOIN pricing_tiers t ON t.franchise_ref = s.franchise_ref AND t.tier_ref = s.tier_ref
             WHERE r.franchise_ref = ? AND r.product_ref = ? AND s.status = 'ACTIVE'
             ORDER BY s.priority ASC"
        );
        $stmt->execute([$f, $ref]);
        return Response::json(200, $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function stockSummary(Request $r): Response
    {
        $ctx = $this->getCtx();
        $this->authorization->requirePermission($ctx, 'products', 'view');
        $f = $ctx->requireFranchise();
        $ref = (string)$r->param('ref');
        $product = $this->products->findByRef($f, $ref);
        if (!$product) throw new NotFoundException('PRODUCT_NOT_FOUND', "Product {$ref} not found.");

        $pdo = Container::getInstance()->make(\PDO::class);
        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(on_hand_qty), 0) as total_on_hand,
                    COALESCE(SUM(reserved_qty), 0) as total_reserved,
                    COALESCE(SUM(on_hand_qty - reserved_qty), 0) as total_available,
                    COALESCE(SUM(damaged_qty), 0) as total_damaged,
                    COUNT(id) as batch_count
             FROM inventory_batches
             WHERE franchise_ref = ? AND product_ref = ? AND status = 'SALEABLE'"
        );
        $stmt->execute([$f, $ref]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [
            'total_on_hand'   => 0,
            'total_reserved'  => 0,
            'total_available' => 0,
            'total_damaged'   => 0,
            'batch_count'     => 0,
        ];

        return Response::json(200, array_merge(['product_ref' => $ref], $row));
    }
}
