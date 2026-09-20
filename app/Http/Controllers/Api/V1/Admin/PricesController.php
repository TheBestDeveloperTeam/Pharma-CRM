<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api\V1\Admin;

use App\Core\{Request, Response, Container, Validation, TenantContext, RefGenerator};
use App\Repositories\Contracts\{ProductPriceRepositoryInterface, ProductRepositoryInterface, PricingTierRepositoryInterface};
use App\Domain\Pricing\PriceResolver;
use App\Domain\Audit\AuditService;
use App\Core\Exceptions\{NotFoundException, ForbiddenException, ValidationException};

final class PricesController
{
    public function __construct(
        private ProductPriceRepositoryInterface $prices,
        private ProductRepositoryInterface $products,
        private PricingTierRepositoryInterface $tiers,
        private PriceResolver $resolver,
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
        $perPage = (int) $r->query('per_page', '50');
        $filters = [
            'product_ref' => $r->query('product_ref', ''),
            'tier_ref'    => $r->query('tier_ref', ''),
            'party_ref'   => $r->query('party_ref', ''),
            'status'      => $r->query('status', ''),
        ];

        $res = $this->prices->list($franchiseRef, $filters, $page, $perPage);
        return Response::json(200, $res['data'], $res['meta']);
    }

    public function create(Request $r): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();

        $clean = Validation::validate($r->all(), [
            'product_ref'    => 'required|string',
            'rate'           => 'required|numeric',
            'effective_from' => 'required|date',
        ]);

        $prod = $this->products->findByRef($franchiseRef, $clean['product_ref']);
        if (!$prod) throw new NotFoundException('PRODUCT_NOT_FOUND', "Product {$clean['product_ref']} not found.");

        $tierRef = $r->input('tier_ref');
        $partyRef = $r->input('party_ref');

        // Check: cannot specify both tier and party
        if ($tierRef && $partyRef) {
            throw new ValidationException('INVALID_TARGET', 'Cannot specify both tier_ref and party_ref on a price rule.');
        }

        $priceRef = RefGenerator::make('PRC');
        $data = [
            'price_ref'      => $priceRef,
            'org_ref'        => $ctx->orgRef,
            'franchise_ref'  => $franchiseRef,
            'product_ref'    => $clean['product_ref'],
            'tier_ref'       => $tierRef,
            'party_ref'      => $partyRef,
            'rate'           => (float)$clean['rate'],
            'priority'       => (int)$r->input('priority', 100),
            'effective_from' => $clean['effective_from'],
            'effective_to'   => $r->input('effective_to'),
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
            after: $data
        );

        return Response::json(201, $data);
    }

    public function resolve(Request $r): Response
    {
        $ctx = $this->getCtx();
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
}
