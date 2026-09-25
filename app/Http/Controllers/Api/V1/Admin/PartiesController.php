<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api\V1\Admin;

use App\Core\{Container, QueryParams, Request, Response, TenantContext, Validation};
use App\Core\Exceptions\{NotFoundException, ValidationException};
use App\Domain\Audit\AuditService;
use App\Domain\Authorization\AuthorizationService;
use App\Domain\Parties\PartyService;
use App\Domain\Parties\PartyCreditService;
use App\Repositories\Contracts\{PartyRepositoryInterface, ProductRepositoryInterface, PricingTierRepositoryInterface};

final class PartiesController
{
    public function __construct(
        private PartyRepositoryInterface $parties,
        private PartyService $partyService,
        private PartyCreditService $creditService,
        private ProductRepositoryInterface $products,
        private PricingTierRepositoryInterface $tiers,
        private AuthorizationService $authorization,
        private AuditService $audit,
        private \PDO $pdo,
    ) {}

    private function ctx(): TenantContext { return TenantContext::get(); }

    public function index(Request $r): Response
    {
        $ctx = $this->ctx(); $franchiseRef = $ctx->requireFranchise(); $this->authorization->requirePermission($ctx, 'parties', 'view');
        $query = QueryParams::fromRequest($r, ['firm_name', 'party_code', 'created_at', 'credit_limit', 'status']);
        $scope = $ctx->scopeFor('parties');
        if ($scope === 'NONE') return Response::json(200, [], ['page' => $query['page'], 'per_page' => $query['per_page'], 'total' => 0, 'total_pages' => 0]);
        if ($scope === 'TERRITORY' && !$ctx->territoryRefs) return Response::json(200, [], ['page' => $query['page'], 'per_page' => $query['per_page'], 'total' => 0, 'total_pages' => 0]);
        $filters = ['status' => $query['status'], 'search' => $query['search'], 'party_type' => $r->query('party_type'), 'city_ref' => $r->query('city_ref'), 'area' => $r->query('area'), 'sort_by' => $query['sort_by'], 'sort_dir' => $query['sort_dir']];
        if ($scope === 'OWN') $filters['sales_user_ref'] = $ctx->userRef;
        if ($scope === 'TEAM') $filters['sales_user_refs'] = array_values(array_unique(array_merge([$ctx->userRef], $ctx->teamUserRefs)));
        if ($scope === 'TERRITORY') $filters['territory_refs'] = $ctx->territoryRefs;
        $res = $this->parties->list($franchiseRef, $filters, $query['page'], $query['per_page']);
        return Response::json(200, $res['items'], ['page' => $res['page'], 'per_page' => $res['per_page'], 'total' => $res['total'], 'total_pages' => $res['total_pages']]);
    }

    public function show(Request $r): Response
    {
        $ctx = $this->ctx(); $franchiseRef = $ctx->requireFranchise(); $this->authorization->requirePermission($ctx, 'parties', 'view'); $ref = (string)$r->param('ref');
        $party = $this->parties->findByRef($franchiseRef, $ref); if (!$party) throw new NotFoundException('PARTY_NOT_FOUND', 'Party not found.');
        $this->authorization->requireRecordScope($ctx, 'parties', $party['sales_user_ref'] ?? null, $this->firstTerritory($franchiseRef, $ref), $franchiseRef);
        $party['product_interests'] = $this->parties->listProductInterests($franchiseRef, $ref); $party['credit'] = $this->creditService->check($franchiseRef, $ref); $party['territories'] = $this->territories($franchiseRef, $ref);
        return Response::json(200, $party);
    }

    public function store(Request $r): Response
    {
        $ctx = $this->ctx(); $this->authorization->requirePermission($ctx, 'parties', 'create'); $clean = $this->validateParty($r, true, $ctx); $data = $this->partyData($clean, $ctx);
        $partyRef = $this->partyService->create($data); if (!empty($clean['product_refs'])) $this->parties->replaceProductInterests($ctx->requireFranchise(), $partyRef, $clean['product_refs'], $ctx->orgRef, $ctx->userRef);
        $after = $this->parties->findByRef($ctx->requireFranchise(), $partyRef); $this->audit->log(ctx: $ctx, category: 'BUSINESS', action: 'party.created', entityType: 'party', entityRef: $partyRef, after: $after ?? $data); return Response::json(201, $after);
    }

    public function update(Request $r): Response
    {
        $ctx = $this->ctx(); $franchiseRef = $ctx->requireFranchise(); $ref = (string)$r->param('ref'); $this->authorization->requirePermission($ctx, 'parties', 'edit'); $before = $this->parties->findByRef($franchiseRef, $ref); if (!$before) throw new NotFoundException('PARTY_NOT_FOUND', 'Party not found.');
        $this->authorization->requireRecordScope($ctx, 'parties', $before['sales_user_ref'] ?? null, $this->firstTerritory($franchiseRef, $ref), $franchiseRef); $clean = $this->validateParty($r, false, $ctx); $data = $this->partyData($clean, $ctx, false); unset($data['party_ref'], $data['party_code'], $data['created_by_ref'], $data['franchise_ref'], $data['org_ref'], $data['status'], $data['opening_outstanding']);
        $this->partyService->update($franchiseRef, $ref, $data); if (array_key_exists('product_refs', $clean)) $this->parties->replaceProductInterests($franchiseRef, $ref, $clean['product_refs'], $ctx->orgRef, $ctx->userRef);
        $after = $this->parties->findByRef($franchiseRef, $ref); $this->audit->log(ctx: $ctx, category: 'BUSINESS', action: 'party.updated', entityType: 'party', entityRef: $ref, before: $before, after: $after ?? $data); return Response::json(200, $after);
    }

    public function status(Request $r): Response
    {
        $ctx = $this->ctx(); $f = $ctx->requireFranchise(); $ref = (string)$r->param('ref'); $this->authorization->requirePermission($ctx, 'parties', 'activateDeactivate'); $before = $this->parties->findByRef($f, $ref); if (!$before) throw new NotFoundException('PARTY_NOT_FOUND', 'Party not found.');
        $this->authorization->requireRecordScope($ctx, 'parties', $before['sales_user_ref'] ?? null, $this->firstTerritory($f, $ref), $f); $status = Validation::validate($r->all(), ['status' => 'required|enum:ACTIVE,INACTIVE'])['status']; $this->parties->setStatus($f, $ref, $status); $this->audit->log(ctx: $ctx, category: 'BUSINESS', action: 'party.status_changed', entityType: 'party', entityRef: $ref, before: $before, after: ['status' => $status]); return Response::json(200, ['party_ref' => $ref, 'status' => $status]);
    }

    public function archive(Request $r): Response
    {
        $ctx = $this->ctx(); $f = $ctx->requireFranchise(); $ref = (string)$r->param('ref'); $this->authorization->requirePermission($ctx, 'parties', 'archive'); $before = $this->parties->findByRef($f, $ref); if (!$before) throw new NotFoundException('PARTY_NOT_FOUND', 'Party not found.');
        $this->authorization->requireRecordScope($ctx, 'parties', $before['sales_user_ref'] ?? null, $this->firstTerritory($f, $ref), $f); $this->partyService->archive($f, $ref); $this->audit->log(ctx: $ctx, category: 'BUSINESS', action: 'party.archived', entityType: 'party', entityRef: $ref, before: $before, after: ['status' => 'ARCHIVED']); return Response::json(200, ['party_ref' => $ref, 'status' => 'ARCHIVED']);
    }

    public function restore(Request $r): Response
    {
        $ctx = $this->ctx(); $f = $ctx->requireFranchise(); $ref = (string)$r->param('ref'); $this->authorization->requirePermission($ctx, 'parties', 'archive'); $before = $this->parties->findByRef($f, $ref); if (!$before) throw new NotFoundException('PARTY_NOT_FOUND', 'Party not found.');
        $this->partyService->restore($f, $ref); $this->audit->log(ctx: $ctx, category: 'BUSINESS', action: 'party.restored', entityType: 'party', entityRef: $ref, before: $before, after: ['status' => 'ACTIVE']); return Response::json(200, ['party_ref' => $ref, 'status' => 'ACTIVE']);
    }

    public function ledger(Request $r): Response
    {
        $ctx = $this->ctx(); $f = $ctx->requireFranchise(); $this->authorization->requirePermission($ctx, 'parties', 'view'); $ref = (string)$r->param('ref'); $party = $this->parties->findByRef($f, $ref); if (!$party) throw new NotFoundException('PARTY_NOT_FOUND', 'Party not found.');
        $this->authorization->requireRecordScope($ctx, 'parties', $party['sales_user_ref'] ?? null, $this->firstTerritory($f, $ref), $f); return Response::json(200, $this->creditService->check($f, $ref, (float)$r->query('proposed_exposure', 0)));
    }

    private function validateParty(Request $r, bool $create, TenantContext $ctx): array
    {
        $payload = $r->all();
        foreach (['contact_name','mobile','whatsapp','email','gstin','pincode','payment_terms','payment_terms_days','drug_license_validity','agreement_from','agreement_to'] as $optional) if (array_key_exists($optional, $payload) && $payload[$optional] === '') $payload[$optional] = null;
        $rules = ['firm_name' => ($create ? 'required|' : '') . 'string|min:2', 'contact_name' => 'string', 'mobile' => 'mobile', 'whatsapp' => 'mobile', 'email' => 'email', 'gstin' => 'gstin', 'drug_license_no' => 'string', 'pincode' => 'pincode', 'state_ref' => 'string', 'district_ref' => 'string', 'city_ref' => 'string', 'area' => 'string', 'party_type' => 'string', 'billing_address' => 'string', 'shipping_address' => 'string', 'remarks' => 'string', 'tier_ref' => 'string', 'sales_user_ref' => 'string', 'credit_limit' => 'numeric|min:0', 'opening_outstanding' => 'numeric|min:0', 'payment_terms' => 'string', 'payment_terms_days' => 'integer|min:0', 'drug_license_validity' => 'date:Y-m-d', 'agreement_from' => 'date:Y-m-d', 'agreement_to' => 'date:Y-m-d', 'product_refs' => 'array'];
        $clean = Validation::validate($payload, $rules);
        if (!$create && array_key_exists('opening_outstanding', $clean)) throw new ValidationException('OPENING_OUTSTANDING_IMMUTABLE', 'opening_outstanding can only be set when creating a party.');
        if (($clean['agreement_to'] ?? '') !== '' && ($clean['agreement_from'] ?? '') !== '' && $clean['agreement_to'] < $clean['agreement_from']) throw new ValidationException('INVALID_AGREEMENT_DATES', 'agreement_to must be on or after agreement_from.');
        if (!empty($clean['pincode'])) { $stmt = $this->pdo->prepare('SELECT 1 FROM pincodes WHERE pincode = ? LIMIT 1'); $stmt->execute([$clean['pincode']]); if (!$stmt->fetchColumn()) throw new ValidationException('INVALID_PINCODE', 'pincode is not present in the geography master.'); }
        $exclude = (string)($r->param('ref') ?? '');
        foreach (['mobile' => 'mobile', 'gstin' => 'gstin', 'drug_license_no' => 'drug_license_no'] as $input => $column) {
            if (!empty($clean[$input])) { $sql = "SELECT party_ref FROM parties WHERE franchise_ref = ? AND {$column} = ?" . ($exclude !== '' ? ' AND party_ref <> ?' : '') . ' LIMIT 1'; $params = [$ctx->requireFranchise(), $clean[$input]]; if ($exclude !== '') $params[] = $exclude; $stmt = $this->pdo->prepare($sql); $stmt->execute($params); if ($stmt->fetchColumn()) throw new ValidationException('DUPLICATE_PARTY_IDENTIFIER', "{$input} is already used by another party."); }
        }
        if (!empty($clean['firm_name']) && !empty($clean['city_ref'])) { $sql = 'SELECT party_ref FROM parties WHERE franchise_ref = ? AND firm_name = ? AND city_ref = ?' . ($exclude !== '' ? ' AND party_ref <> ?' : '') . ' LIMIT 1'; $params = [$ctx->requireFranchise(), $clean['firm_name'], $clean['city_ref']]; if ($exclude !== '') $params[] = $exclude; $stmt = $this->pdo->prepare($sql); $stmt->execute($params); if ($stmt->fetchColumn()) throw new ValidationException('DUPLICATE_PARTY', 'A party with the same firm name and city already exists.'); }
        if (!empty($clean['tier_ref'])) { $tier = $this->tiers->findByRef($ctx->requireFranchise(), (string)$clean['tier_ref']); if (!$tier || ($tier['status'] ?? '') !== 'ACTIVE') throw new ValidationException('INVALID_PRICING_TIER', 'tier_ref must reference an active pricing tier.'); }
        if (!empty($clean['sales_user_ref'])) { $stmt = $this->pdo->prepare("SELECT 1 FROM users WHERE franchise_ref = ? AND user_ref = ? AND status = 'ACTIVE' LIMIT 1"); $stmt->execute([$ctx->requireFranchise(), $clean['sales_user_ref']]); if (!$stmt->fetchColumn()) throw new ValidationException('INVALID_SALES_USER', 'sales_user_ref must reference an active user in this franchise.'); }
        foreach (($clean['product_refs'] ?? []) as $productRef) if (!$this->products->findByRef($ctx->requireFranchise(), (string)$productRef)) throw new ValidationException('INVALID_PRODUCT', "Product {$productRef} not found.");
        return $clean;
    }

    private function partyData(array $clean, TenantContext $ctx, bool $create = true): array
    {
        $data = $clean; $data['org_ref'] = $ctx->orgRef; $data['franchise_ref'] = $ctx->requireFranchise(); $data['created_by_ref'] = $ctx->userRef; $data['sales_user_ref'] = $ctx->scopeFor('parties') === 'ALL' ? ($clean['sales_user_ref'] ?? $ctx->userRef) : $ctx->userRef; if ($create) $data['status'] = 'ACTIVE';
        if (!isset($data['payment_terms_days']) && !empty($data['payment_terms']) && preg_match('/\d+/', (string)$data['payment_terms'], $m)) $data['payment_terms_days'] = (int)$m[0];
        return $data;
    }

    private function firstTerritory(string $franchiseRef, string $partyRef): ?string { return $this->parties->findTerritoryRefs($franchiseRef, $partyRef)[0] ?? null; }
    private function territories(string $franchiseRef, string $partyRef): array { return Container::getInstance()->make(\App\Repositories\Contracts\PartyTerritoryRepositoryInterface::class)->listForParty($franchiseRef, $partyRef); }
}
