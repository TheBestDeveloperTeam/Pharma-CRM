<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api\V1\Admin;

use App\Core\{Request, Response, Container, Validation, TenantContext, RefGenerator};
use App\Repositories\Contracts\{SchemeRepositoryInterface, ProductRepositoryInterface, PricingTierRepositoryInterface};
use App\Domain\Schemes\SchemeCalculator;
use App\Domain\Audit\AuditService;
use App\Core\Exceptions\{NotFoundException, ForbiddenException, ValidationException};

final class SchemesController
{
    public function __construct(
        private SchemeRepositoryInterface $schemes,
        private ProductRepositoryInterface $products,
        private PricingTierRepositoryInterface $tiers,
        private SchemeCalculator $calculator,
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
            'status'   => $r->query('status', ''),
            'tier_ref' => $r->query('tier_ref', ''),
        ];

        $res = $this->schemes->list($franchiseRef, $filters, $page, $perPage);
        return Response::json(200, $res['data'], $res['meta']);
    }

    public function show(Request $r): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();
        $ref = $r->param('ref');

        $scheme = $this->schemes->findByRef($franchiseRef, $ref);
        if (!$scheme) throw new NotFoundException('SCHEME_NOT_FOUND', "Scheme {$ref} not found.");

        $scheme['rules'] = $this->schemes->findRules($franchiseRef, $ref);
        return Response::json(200, $scheme);
    }

    public function create(Request $r): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();

        $clean = Validation::validate($r->all(), [
            'scheme_name' => 'required|string',
            'start_date'  => 'required|date',
            'end_date'    => 'required|date',
        ]);

        $schemeRef = RefGenerator::make('SCH');
        $schemeData = [
            'scheme_ref'       => $schemeRef,
            'org_ref'          => $ctx->orgRef,
            'franchise_ref'    => $franchiseRef,
            'scheme_name'      => trim($clean['scheme_name']),
            'start_date'       => $clean['start_date'],
            'end_date'         => $clean['end_date'],
            'priority'         => (int)$r->input('priority', 100),
            'stacking_allowed' => $r->input('stacking_allowed', 0) ? 1 : 0,
            'tier_ref'         => $r->input('tier_ref'),
            'status'           => 'ACTIVE',
            'created_by_ref'   => $ctx->userRef,
            'created_at'       => date('Y-m-d H:i:s'),
        ];

        $this->schemes->createScheme($schemeData);

        // Add rules if provided
        $rulesInput = $r->input('rules', []);
        $rules = [];
        if (is_array($rulesInput)) {
            foreach ($rulesInput as $rule) {
                if (!empty($rule['product_ref']) && !empty($rule['min_qty']) && !empty($rule['free_qty'])) {
                    $ruleRef = RefGenerator::make('RUL');
                    $ruleData = [
                        'rule_ref'      => $ruleRef,
                        'org_ref'       => $ctx->orgRef,
                        'franchise_ref' => $franchiseRef,
                        'scheme_ref'    => $schemeRef,
                        'product_ref'   => $rule['product_ref'],
                        'min_qty'       => (int)$rule['min_qty'],
                        'max_qty'       => !empty($rule['max_qty']) ? (int)$rule['max_qty'] : null,
                        'free_qty'      => (int)$rule['free_qty'],
                    ];
                    $this->schemes->createRule($ruleData);
                    $rules[] = $ruleData;
                }
            }
        }

        $schemeData['rules'] = $rules;
        $this->audit->log(
            ctx: $ctx,
            category: 'BUSINESS',
            action: 'scheme.created',
            entityType: 'scheme',
            entityRef: $schemeRef,
            after: $schemeData
        );

        return Response::json(201, $schemeData);
    }

    public function calculate(Request $r): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();

        $clean = Validation::validate($r->all(), [
            'product_ref' => 'required|string',
            'ordered_qty' => 'required|numeric',
        ]);

        $date = $r->input('date', date('Y-m-d'));
        $tierRef = $r->input('tier_ref');
        $qty = (int)$clean['ordered_qty'];

        $result = $this->calculator->calculate($franchiseRef, $tierRef, $clean['product_ref'], $qty, $date);
        return Response::json(200, $result);
    }
}
