<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api\V1\Admin;

use App\Core\{Request, Response, Container, Validation, TenantContext, RefGenerator, QueryParams};
use App\Repositories\Contracts\{SchemeRepositoryInterface, ProductRepositoryInterface, PricingTierRepositoryInterface};
use App\Domain\Schemes\SchemeCalculator;
use App\Domain\Audit\AuditService;
use App\Domain\Authorization\AuthorizationService;
use App\Core\Exceptions\{NotFoundException, ForbiddenException, ValidationException};

final class SchemesController
{
    public function __construct(
        private SchemeRepositoryInterface $schemes,
        private ProductRepositoryInterface $products,
        private PricingTierRepositoryInterface $tiers,
        private SchemeCalculator $calculator,
        private AuditService $audit,
        private AuthorizationService $authorization,
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
        $this->authorization->requirePermission($ctx, 'schemes', 'view');
        $query = QueryParams::fromRequest($r);
        $filters = [
            'status'   => $query['status'],
            'tier_ref' => $r->query('tier_ref', ''),
        ];

        $res = $this->schemes->list($franchiseRef, $filters, $query['page'], $query['per_page']);
        return Response::json(200, $res['data'], $res['meta']);
    }

    public function show(Request $r): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();
        $this->authorization->requirePermission($ctx, 'schemes', 'view');
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
        $this->authorization->requirePermission($ctx, 'schemes', 'create');

        $clean = Validation::validate($r->all(), [
            'scheme_name' => 'required|string|min:2',
            'scheme_type' => 'required|string',
            'start_date'  => 'required|date:Y-m-d',
            'end_date'    => 'required|date:Y-m-d',
            'priority'    => 'required|integer|min:1',
            'rules'       => 'required|array|min:1',
        ]);
        if ($clean['end_date'] < $clean['start_date']) {
            throw new ValidationException('END_DATE_BEFORE_START', 'end_date must be on or after start_date.');
        }
        $rules = $this->validateRules($ctx, $franchiseRef, $r->input('rules'));
        $tierRef = $r->input('tier_ref');
        if ($tierRef !== null && $tierRef !== '' && (!$this->tiers->findByRef($franchiseRef, (string)$tierRef) || ($this->tiers->findByRef($franchiseRef, (string)$tierRef)['status'] ?? '') !== 'ACTIVE')) {
            throw new ValidationException('INVALID_TIER', 'tier_ref must reference an active pricing tier.');
        }

        $schemeRef = RefGenerator::make('SCH');
        $schemeData = [
            'scheme_ref'       => $schemeRef,
            'org_ref'          => $ctx->orgRef,
            'franchise_ref'    => $franchiseRef,
            'scheme_name'      => trim($clean['scheme_name']),
            'scheme_type'      => trim($clean['scheme_type']),
            'start_date'       => $clean['start_date'],
            'end_date'         => $clean['end_date'],
            'priority'         => (int)$clean['priority'],
            'stacking_allowed' => $r->input('stacking_allowed', 0) ? 1 : 0,
            'tier_ref'         => $r->input('tier_ref'),
            'status'           => 'ACTIVE',
            'created_by_ref'   => $ctx->userRef,
            'created_at'       => date('Y-m-d H:i:s'),
        ];

        $this->schemes->createScheme($schemeData);

        $this->schemes->replaceRules($franchiseRef, $schemeRef, $rules);
        foreach ($rules as &$rule) { $rule['scheme_ref'] = $schemeRef; }
        unset($rule);

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
        $this->authorization->requirePermission($ctx, 'schemes', 'view');

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

    public function update(Request $r): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();
        $this->authorization->requirePermission($ctx, 'schemes', 'edit');
        $ref = $r->param('ref');
        $before = $this->schemes->findByRef($franchiseRef, $ref);
        if (!$before) throw new NotFoundException('SCHEME_NOT_FOUND', "Scheme {$ref} not found.");
        $clean = Validation::validate($r->all(), [
            'scheme_name' => 'required|string|min:2', 'scheme_type' => 'required|string',
            'start_date' => 'required|date:Y-m-d', 'end_date' => 'required|date:Y-m-d',
            'priority' => 'required|integer|min:1', 'rules' => 'required|array|min:1',
        ]);
        if ($clean['end_date'] < $clean['start_date']) {
            throw new ValidationException('END_DATE_BEFORE_START', 'end_date must be on or after start_date.');
        }
        $rules = $this->validateRules($ctx, $franchiseRef, $r->input('rules'));
        $tierRef = $r->input('tier_ref');
        if ($tierRef !== null && $tierRef !== '' && (!$this->tiers->findByRef($franchiseRef, (string)$tierRef) || ($this->tiers->findByRef($franchiseRef, (string)$tierRef)['status'] ?? '') !== 'ACTIVE')) {
            throw new ValidationException('INVALID_TIER', 'tier_ref must reference an active pricing tier.');
        }
        $data = [
            'scheme_name' => trim($clean['scheme_name']), 'scheme_type' => trim($clean['scheme_type']),
            'start_date' => $clean['start_date'], 'end_date' => $clean['end_date'],
            'priority' => (int)$clean['priority'], 'tier_ref' => $r->input('tier_ref'),
            'stacking_allowed' => $r->input('stacking_allowed', $before['stacking_allowed'] ?? 0) ? 1 : 0,
            'updated_by_ref' => $ctx->userRef,
        ];
        $this->schemes->updateScheme($franchiseRef, $ref, $data);
        $this->schemes->replaceRules($franchiseRef, $ref, $rules);
        $this->audit->log(ctx: $ctx, category: 'BUSINESS', action: 'scheme.updated', entityType: 'scheme', entityRef: $ref, before: $before, after: array_merge($data, ['rules' => $rules]));
        return Response::json(200, array_merge($data, ['scheme_ref' => $ref, 'rules' => $rules]));
    }

    public function status(Request $r): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();
        $this->authorization->requirePermission($ctx, 'schemes', 'deactivate');
        $ref = $r->param('ref');
        $before = $this->schemes->findByRef($franchiseRef, $ref);
        if (!$before) throw new NotFoundException('SCHEME_NOT_FOUND', "Scheme {$ref} not found.");
        $status = strtoupper((string)$r->input('status', ''));
        if (!in_array($status, ['ACTIVE', 'INACTIVE'], true)) throw new ValidationException('INVALID_STATUS', 'status must be ACTIVE or INACTIVE.');
        $this->schemes->updateScheme($franchiseRef, $ref, ['status' => $status, 'updated_by_ref' => $ctx->userRef]);
        $this->audit->log(ctx: $ctx, category: 'BUSINESS', action: 'scheme.status_changed', entityType: 'scheme', entityRef: $ref, before: $before, after: ['status' => $status]);
        return Response::json(200, ['scheme_ref' => $ref, 'status' => $status]);
    }

    private function validateRules(TenantContext $ctx, string $franchiseRef, mixed $input): array
    {
        if (!is_array($input) || count($input) < 1) throw new ValidationException('RULES_REQUIRED', 'At least one scheme rule is required.');
        $out = [];
        foreach ($input as $rule) {
            if (!is_array($rule)) throw new ValidationException('INVALID_RULE', 'Each scheme rule must be an object.');
            $productRef = (string)($rule['product_ref'] ?? '');
            $min = (int)($rule['min_qty'] ?? 0); $free = (int)($rule['free_qty'] ?? 0);
            $max = array_key_exists('max_qty', $rule) && $rule['max_qty'] !== null && $rule['max_qty'] !== '' ? (int)$rule['max_qty'] : null;
            $product = $this->products->findByRef($franchiseRef, $productRef);
            if (!$product || ($product['status'] ?? '') !== 'ACTIVE') throw new ValidationException('INVALID_PRODUCT', "Product {$productRef} must be active.");
            if ($min < 1 || $free < 1 || ($max !== null && $max < $min)) throw new ValidationException('INVALID_RULE_QUANTITY', 'Scheme quantities are invalid.');
            $out[] = ['rule_ref' => RefGenerator::make('RUL'), 'org_ref' => $ctx->orgRef, 'franchise_ref' => $franchiseRef, 'scheme_ref' => '', 'product_ref' => $productRef, 'min_qty' => $min, 'max_qty' => $max, 'free_qty' => $free];
        }
        return $out;
    }
}
