<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api\V1\Admin;

use App\Core\{Request, Response, Container, QueryParams, Validation, TenantContext, RefGenerator};
use App\Repositories\Contracts\{ProductPriceRepositoryInterface, ProductRepositoryInterface, PricingTierRepositoryInterface};
use App\Domain\Pricing\PriceResolver;
use App\Domain\Audit\AuditService;
use App\Domain\Authorization\AuthorizationService;
use App\Core\Exceptions\{NotFoundException, ForbiddenException, ValidationException};

final class PricesController
{
    public function __construct(
        private ProductPriceRepositoryInterface $prices,
        private ProductRepositoryInterface $products,
        private PricingTierRepositoryInterface $tiers,
        private PriceResolver $resolver,
        private AuditService $audit,
        private AuthorizationService $authorization,
        private \PDO $pdo,
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
        $this->authorization->requirePermission($ctx, 'pricing', 'view');
        $franchiseRef = $ctx->requireFranchise();

        $query = QueryParams::fromRequest($r, ['priority', 'effective_from', 'created_at']);
        $page = $query['page'];
        $perPage = $query['per_page'];
        $filters = [
            'product_ref' => $r->query('product_ref', ''),
            'tier_ref'    => $r->query('tier_ref', ''),
            'party_ref'   => $r->query('party_ref', ''),
            'status'      => $r->query('status', ''),
            'search'      => $query['search'],
        ];

        $res = $this->prices->list($franchiseRef, $filters, $page, $perPage);
        return Response::json(200, $res['data'], $res['meta']);
    }

    public function create(Request $r): Response
    {
        $ctx = $this->getCtx();
        $this->authorization->requirePermission($ctx, 'pricing', 'create');
        $franchiseRef = $ctx->requireFranchise();

        $clean = Validation::validate($r->all(), [
            'product_ref'    => 'required|string',
            'mrp'            => 'required|numeric|min:0',
            'pts'            => 'required|numeric|min:0',
            'net_rate'       => 'required|numeric|min:0',
            'effective_from' => 'required|date:Y-m-d',
        ]);

        $prod = $this->products->findByRef($franchiseRef, $clean['product_ref']);
        if (!$prod) throw new NotFoundException('PRODUCT_NOT_FOUND', "Product {$clean['product_ref']} not found.");

        $tierRef = $r->input('tier_ref');
        $partyRef = $r->input('party_ref');

        // Check: cannot specify both tier and party
        if ($tierRef && $partyRef) {
            throw new ValidationException('INVALID_TARGET', 'Cannot specify both tier_ref and party_ref on a price rule.');
        }
        if (!$tierRef && !$partyRef) throw new ValidationException('INVALID_TARGET', 'Either tier_ref or party_ref is required.');
        if ($tierRef) {
            $tier = $this->tiers->findByRef($franchiseRef, (string)$tierRef);
            if (!$tier || $tier['status'] !== 'ACTIVE') throw new NotFoundException('TIER_NOT_FOUND', 'Active pricing tier not found.');
        }
        if ($partyRef) {
            $stmt = $this->pdo->prepare("SELECT party_ref, status FROM parties WHERE franchise_ref = :f AND party_ref = :p LIMIT 1");
            $stmt->execute([':f' => $franchiseRef, ':p' => $partyRef]);
            $party = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$party || $party['status'] !== 'ACTIVE') throw new NotFoundException('PARTY_NOT_FOUND', 'Active party not found.');
        }
        $effectiveTo = $r->input('effective_to');
        if ($effectiveTo !== null && $effectiveTo !== '' && $effectiveTo < $clean['effective_from']) throw new ValidationException('INVALID_DATE_RANGE', 'effective_to must be on or after effective_from.', ['effective_to' => ['Must be on or after effective_from.']]);
        $overlap = $this->prices->findOverlapping($franchiseRef, $clean['product_ref'], $partyRef, $tierRef, $clean['effective_from'], $effectiveTo);
        $overrideReason = trim((string)$r->input('override_reason', ''));
        if ($overlap) {
            $this->authorization->requirePermission($ctx, 'pricing', 'priceOverride');
            if ($overrideReason === '') throw new ValidationException('OVERRIDE_REASON_REQUIRED', 'Override reason is required for an overlapping active rate.', ['override_reason' => ['This field is required when replacing an active rate.']]);
        }

        $priceRef = RefGenerator::make('PRC');
        $data = [
            'price_ref'      => $priceRef,
            'org_ref'        => $ctx->orgRef,
            'franchise_ref'  => $franchiseRef,
            'product_ref'    => $clean['product_ref'],
            'tier_ref'       => $tierRef,
            'party_ref'      => $partyRef,
            'rate'           => (float)$clean['net_rate'],
            'mrp'            => (float)$clean['mrp'],
            'pts'            => (float)$clean['pts'],
            'net_rate'       => (float)$clean['net_rate'],
            'priority'       => (int)$r->input('priority', 100),
            'effective_from' => $clean['effective_from'],
            'effective_to'   => $effectiveTo ?: null,
            'override_reason' => $overrideReason !== '' ? $overrideReason : null,
            'status'         => 'ACTIVE',
            'created_by_ref' => $ctx->userRef,
            'created_at'     => date('Y-m-d H:i:s'),
        ];

        $this->prices->create($data);
        $this->audit->log(
            ctx: $ctx,
            category: 'BUSINESS',
            action: 'price.created',
            entityType: 'product_price',
            entityRef: $priceRef,
            after: $data,
            reason: $overrideReason !== '' ? $overrideReason : null
        );

        return Response::json(201, $data);
    }

    public function resolve(Request $r): Response
    {
        $ctx = $this->getCtx();
        $this->authorization->requirePermission($ctx, 'pricing', 'view');
        $franchiseRef = $ctx->requireFranchise();

        $clean = Validation::validate($r->all(), [
            'product_ref' => 'required|string',
        ]);

        $date = $r->input('date', date('Y-m-d'));
        $partyRef = $r->input('party_ref');
        $tierRef = $r->input('tier_ref');

        $result = $this->resolver->resolve($franchiseRef, $clean['product_ref'], $partyRef, $tierRef, $date);
        return Response::json(200, $result);
    }

    public function show(Request $r): Response
    {
        $ctx = $this->getCtx();
        $this->authorization->requirePermission($ctx, 'pricing', 'view');
        $row = $this->prices->findByRef($ctx->requireFranchise(), (string)$r->param('ref'));
        if (!$row) throw new NotFoundException('PRICE_NOT_FOUND', 'Pricing rate not found.');
        return Response::json(200, $row);
    }

    public function status(Request $r): Response
    {
        $ctx = $this->getCtx();
        $this->authorization->requirePermission($ctx, 'pricing', 'edit');
        $ref = (string)$r->param('ref');
        $old = $this->prices->findByRef($ctx->requireFranchise(), $ref);
        if (!$old) throw new NotFoundException('PRICE_NOT_FOUND', 'Pricing rate not found.');
        $clean = Validation::validate($r->all(), ['status' => 'required|enum:ACTIVE,INACTIVE']);
        $this->prices->update($ctx->requireFranchise(), $ref, ['status' => $clean['status'], 'updated_by_ref' => $ctx->userRef]);
        $this->audit->log(ctx: $ctx, category: 'BUSINESS', action: 'price.status_changed', entityType: 'product_price', entityRef: $ref, before: $old, after: array_merge($old, ['status' => $clean['status']]));
        return Response::json(200, ['price_ref' => $ref, 'status' => $clean['status']]);
    }
}
