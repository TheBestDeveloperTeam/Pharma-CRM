<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api\DCR;

use App\Core\{Request, Response, Container, TenantContext, Validation, RefGenerator};
use App\Core\Exceptions\{NotFoundException, ValidationException, ForbiddenException};
use App\Domain\Authorization\AuthorizationService;
use App\Domain\DCR\DcrService;
use App\Domain\Orders\OrderService;

final class DcrController
{
    private \PDO $pdo;
    private OrderService $orderService;

    public function __construct(
        private DcrService $service,
        private AuthorizationService $auth,
        ?\PDO $pdo = null,
        ?OrderService $orderService = null,
    ) {
        $this->pdo = $pdo ?? Container::getInstance()->make(\PDO::class);
        $this->orderService = $orderService ?? Container::getInstance()->make(OrderService::class);
    }

    private function ctx(): TenantContext
    {
        return TenantContext::get();
    }

    private function access(TenantContext $c, Request $r): void
    {
        $this->service->assertAccess($c, (string)$r->param('ref'));
    }

    public function index(Request $r): Response
    {
        $c = $this->ctx();
        $this->auth->requirePermission($c, 'dcr', 'view');
        return Response::json(200, $this->service->list($c, $r->query));
    }

    public function store(Request $r): Response
    {
        $c = $this->ctx();
        $this->auth->requirePermission($c, 'dcr', 'create');
        return Response::json(201, $this->service->create($c, $r->all()));
    }

    public function show(Request $r): Response
    {
        $c = $this->ctx();
        $this->auth->requirePermission($c, 'dcr', 'view');
        $this->access($c, $r);
        return Response::json(200, $this->service->detail($c, (string)$r->param('ref')));
    }

    public function update(Request $r): Response
    {
        $c = $this->ctx();
        $this->auth->requirePermission($c, 'dcr', 'edit');
        $this->access($c, $r);
        return Response::json(200, $this->service->update($c, (string)$r->param('ref'), $r->all()));
    }

    public function status(Request $r): Response
    {
        $c = $this->ctx();
        $s = (string)$r->input('status');
        $action = match ($s) {
            'SUBMITTED' => 'submit',
            'APPROVED'  => 'approve',
            'REJECTED'  => 'reject',
            default     => 'edit'
        };
        $this->auth->requirePermission($c, 'dcr', $action);
        $this->access($c, $r);
        return Response::json(200, $this->service->transition($c, (string)$r->param('ref'), $s, $r->input('remarks')));
    }

    public function submit(Request $r): Response
    {
        $c = $this->ctx();
        $this->auth->requirePermission($c, 'dcr', 'submit');
        $ref = (string)$r->param('ref');
        $this->access($c, $r);
        return Response::json(200, $this->service->transition($c, $ref, 'SUBMITTED', $r->input('remarks')));
    }

    public function approve(Request $r): Response
    {
        $c = $this->ctx();
        $this->auth->requirePermission($c, 'dcr', 'approve');
        $ref = (string)$r->param('ref');
        $this->access($c, $r);
        return Response::json(200, $this->service->transition($c, $ref, 'APPROVED', $r->input('remarks')));
    }

    public function reject(Request $r): Response
    {
        $c = $this->ctx();
        $this->auth->requirePermission($c, 'dcr', 'reject');
        $ref = (string)$r->param('ref');
        $this->access($c, $r);
        return Response::json(200, $this->service->transition($c, $ref, 'REJECTED', (string)$r->input('remarks', 'Rejected')));
    }

    public function reopen(Request $r): Response
    {
        $c = $this->ctx();
        $this->auth->requirePermission($c, 'dcr', 'approve');
        $ref = (string)$r->param('ref');
        $this->access($c, $r);
        return Response::json(200, $this->service->transition($c, $ref, 'DRAFT', (string)$r->input('remarks', 'Reopened by reviewer')));
    }

    public function visits(Request $r): Response
    {
        $c = $this->ctx();
        $this->auth->requirePermission($c, 'dcr', 'view');
        $ref = (string)$r->param('ref');
        $this->access($c, $r);
        $dcr = $this->service->detail($c, $ref);
        return Response::json(200, $dcr['visits'] ?? []);
    }

    public function addVisit(Request $r): Response
    {
        $c = $this->ctx();
        $this->auth->requirePermission($c, 'dcr', 'edit');
        $ref = (string)$r->param('ref');
        $this->access($c, $r);
        $dcr = $this->service->detail($c, $ref);
        if (!in_array($dcr['status'], ['DRAFT', 'REJECTED'], true)) {
            throw new ValidationException('DCR_LOCKED', 'Visits can only be added to DRAFT or REJECTED DCR.');
        }

        $clean = Validation::validate($r->all(), [
            'visit_time'    => 'required|string',
            'visit_purpose' => 'required|string',
        ]);

        $party = $r->input('party_ref');
        $lead = $r->input('lead_ref');
        if ((bool)$party === (bool)$lead) {
            throw new ValidationException('INVALID_DCR_RELATION', 'Each visit needs exactly one party_ref or lead_ref.');
        }

        $visitRef = RefGenerator::generate('DCV');
        $products = $r->input('products_promoted', []);

        $stmt = $this->pdo->prepare("INSERT INTO dcr_visits_v2 (visit_ref, dcr_ref, org_ref, franchise_ref, party_ref, lead_ref, customer_type, visit_time, visit_purpose, products_promoted_json, samples_given_json, pob_product_ref, pob_quantity, pob_value, feedback, next_visit_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $visitRef, $ref, $c->orgRef, $c->requireFranchise(), $party, $lead,
            $r->input('customer_type', 'OTHER'),
            $clean['visit_time'], $clean['visit_purpose'],
            json_encode($products),
            $r->input('samples_given') ? json_encode($r->input('samples_given')) : null,
            $r->input('pob_product_ref'),
            $r->input('pob_quantity'),
            $r->input('pob_value'),
            $r->input('feedback'),
            $r->input('next_visit_date'),
        ]);

        return Response::json(201, ['visit_ref' => $visitRef, 'dcr_ref' => $ref, 'status' => 'CREATED']);
    }

    public function updateVisit(Request $r): Response
    {
        $c = $this->ctx();
        $this->auth->requirePermission($c, 'dcr', 'edit');
        $ref = (string)$r->param('ref');
        $vRef = (string)$r->param('visit_ref');
        $this->access($c, $r);
        $dcr = $this->service->detail($c, $ref);
        if (!in_array($dcr['status'], ['DRAFT', 'REJECTED'], true)) {
            throw new ValidationException('DCR_LOCKED', 'Visits can only be modified in DRAFT or REJECTED DCR.');
        }

        $stmt = $this->pdo->prepare("UPDATE dcr_visits_v2 SET visit_time = COALESCE(:time, visit_time), visit_purpose = COALESCE(:purpose, visit_purpose), feedback = COALESCE(:feedback, feedback), pob_quantity = COALESCE(:pob_q, pob_quantity), pob_value = COALESCE(:pob_v, pob_value), next_visit_date = COALESCE(:nvd, next_visit_date) WHERE franchise_ref = :f AND dcr_ref = :d AND visit_ref = :v");
        $stmt->execute([
            ':time'     => $r->input('visit_time'),
            ':purpose'  => $r->input('visit_purpose'),
            ':feedback' => $r->input('feedback'),
            ':pob_q'    => $r->input('pob_quantity'),
            ':pob_v'    => $r->input('pob_value'),
            ':nvd'      => $r->input('next_visit_date'),
            ':f'        => $c->requireFranchise(),
            ':d'        => $ref,
            ':v'        => $vRef,
        ]);

        return Response::json(200, ['visit_ref' => $vRef, 'updated' => true]);
    }

    public function deleteVisit(Request $r): Response
    {
        $c = $this->ctx();
        $this->auth->requirePermission($c, 'dcr', 'edit');
        $ref = (string)$r->param('ref');
        $vRef = (string)$r->param('visit_ref');
        $this->access($c, $r);
        $dcr = $this->service->detail($c, $ref);
        if (!in_array($dcr['status'], ['DRAFT', 'REJECTED'], true)) {
            throw new ValidationException('DCR_LOCKED', 'Visits can only be deleted in DRAFT or REJECTED DCR.');
        }

        $stmt = $this->pdo->prepare("DELETE FROM dcr_visits_v2 WHERE franchise_ref = :f AND dcr_ref = :d AND visit_ref = :v");
        $stmt->execute([':f' => $c->requireFranchise(), ':d' => $ref, ':v' => $vRef]);

        return Response::json(200, ['visit_ref' => $vRef, 'deleted' => true]);
    }

    public function listFieldCustomers(Request $r): Response
    {
        $c = $this->ctx();
        $f = $c->requireFranchise();
        $stmt = $this->pdo->prepare("SELECT master_value_ref as customer_ref, code as customer_name, label as customer_data, status, created_at FROM catalog_master_values WHERE franchise_ref = :f AND master_key = 'field_customers' AND status != 'DELETED' ORDER BY code ASC");
        $stmt->execute([':f' => $f]);
        $rows = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $data = json_decode($row['customer_data'] ?? '{}', true) ?: [];
            $rows[] = array_merge([
                'customer_ref'  => $row['customer_ref'],
                'customer_name' => $row['customer_name'],
                'status'        => $row['status'],
                'created_at'    => $row['created_at'],
            ], $data);
        }
        return Response::json(200, $rows);
    }

    public function createFieldCustomer(Request $r): Response
    {
        $c = $this->ctx();
        $f = $c->requireFranchise();
        $clean = Validation::validate($r->all(), [
            'customer_name' => 'required|string',
            'customer_type' => 'required|string',
        ]);
        $ref = RefGenerator::generate('CST');
        $extra = json_encode([
            'customer_type' => $clean['customer_type'],
            'specialty'     => $r->input('specialty'),
            'qualification' => $r->input('qualification'),
            'mobile'        => $r->input('mobile'),
            'address'       => $r->input('address'),
            'city'          => $r->input('city'),
            'beat'          => $r->input('beat'),
        ]);
        $stmt = $this->pdo->prepare("INSERT INTO catalog_master_values (master_value_ref, org_ref, franchise_ref, master_key, code, label, sort_order, status, created_by_ref) VALUES (:ref, :org, :f, 'field_customers', :code, :label, 0, 'ACTIVE', :creator)");
        $stmt->execute([
            ':ref'     => $ref,
            ':org'     => $c->orgRef,
            ':f'       => $f,
            ':code'    => $clean['customer_name'],
            ':label'   => $extra,
            ':creator' => $c->userRef,
        ]);
        return Response::json(201, ['customer_ref' => $ref, 'customer_name' => $clean['customer_name'], 'status' => 'ACTIVE']);
    }

    public function showFieldCustomer(Request $r, ?string $ref = null): Response
    {
        $c = $this->ctx();
        $f = $c->requireFranchise();
        $ref = $ref ?? (string)$r->param('ref');
        $stmt = $this->pdo->prepare("SELECT master_value_ref as customer_ref, code as customer_name, label as customer_data, status, created_at FROM catalog_master_values WHERE franchise_ref = :f AND master_value_ref = :r AND master_key = 'field_customers' LIMIT 1");
        $stmt->execute([':f' => $f, ':r' => $ref]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) throw new NotFoundException('CUSTOMER_NOT_FOUND', 'Field customer not found.');
        $data = json_decode($row['customer_data'] ?? '{}', true) ?: [];
        return Response::json(200, array_merge(['customer_ref' => $row['customer_ref'], 'customer_name' => $row['customer_name'], 'status' => $row['status']], $data));
    }

    public function updateFieldCustomer(Request $r, ?string $ref = null): Response
    {
        $c = $this->ctx();
        $f = $c->requireFranchise();
        $ref = $ref ?? (string)$r->param('ref');
        $extra = json_encode([
            'customer_type' => $r->input('customer_type', 'DOCTOR'),
            'specialty'     => $r->input('specialty'),
            'qualification' => $r->input('qualification'),
            'mobile'        => $r->input('mobile'),
            'address'       => $r->input('address'),
            'city'          => $r->input('city'),
            'beat'          => $r->input('beat'),
        ]);
        $stmt = $this->pdo->prepare("UPDATE catalog_master_values SET code = COALESCE(:code, code), label = :label WHERE franchise_ref = :f AND master_value_ref = :r AND master_key = 'field_customers'");
        $stmt->execute([':code' => $r->input('customer_name'), ':label' => $extra, ':f' => $f, ':r' => $ref]);
        return Response::json(200, ['customer_ref' => $ref, 'updated' => true]);
    }

    public function statusFieldCustomer(Request $r, ?string $ref = null): Response
    {
        $c = $this->ctx();
        $f = $c->requireFranchise();
        $ref = $ref ?? (string)$r->param('ref');
        $status = $r->input('status', 'ACTIVE');
        $stmt = $this->pdo->prepare("UPDATE catalog_master_values SET status = :s WHERE franchise_ref = :f AND master_value_ref = :r AND master_key = 'field_customers'");
        $stmt->execute([':s' => $status, ':f' => $f, ':r' => $ref]);
        return Response::json(200, ['customer_ref' => $ref, 'status' => $status]);
    }

    public function listBeats(Request $r): Response
    {
        $c = $this->ctx();
        $f = $c->requireFranchise();
        $stmt = $this->pdo->prepare("SELECT master_value_ref as beat_ref, code as beat_name, label as description, status FROM catalog_master_values WHERE franchise_ref = :f AND master_key = 'beats' AND status = 'ACTIVE' ORDER BY code ASC");
        $stmt->execute([':f' => $f]);
        return Response::json(200, $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function createBeat(Request $r): Response
    {
        $c = $this->ctx();
        $f = $c->requireFranchise();
        $clean = Validation::validate($r->all(), [
            'beat_name' => 'required|string',
        ]);
        $ref = RefGenerator::generate('BET');
        $stmt = $this->pdo->prepare("INSERT INTO catalog_master_values (master_value_ref, org_ref, franchise_ref, master_key, code, label, sort_order, status, created_by_ref) VALUES (:ref, :org, :f, 'beats', :code, :label, 0, 'ACTIVE', :creator)");
        $stmt->execute([
            ':ref'     => $ref,
            ':org'     => $c->orgRef,
            ':f'       => $f,
            ':code'    => $clean['beat_name'],
            ':label'   => $r->input('description') ?: $clean['beat_name'],
            ':creator' => $c->userRef,
        ]);
        return Response::json(201, ['beat_ref' => $ref, 'beat_name' => $clean['beat_name'], 'status' => 'ACTIVE']);
    }

    public function updateBeat(Request $r, ?string $ref = null): Response
    {
        $c = $this->ctx();
        $f = $c->requireFranchise();
        $ref = $ref ?? (string)$r->param('ref');
        $stmt = $this->pdo->prepare("UPDATE catalog_master_values SET code = COALESCE(:code, code), label = COALESCE(:label, label) WHERE franchise_ref = :f AND master_value_ref = :r AND master_key = 'beats'");
        $stmt->execute([':code' => $r->input('beat_name'), ':label' => $r->input('description'), ':f' => $f, ':r' => $ref]);
        return Response::json(200, ['beat_ref' => $ref, 'updated' => true]);
    }

    public function listTourPlans(Request $r): Response
    {
        $c = $this->ctx();
        $f = $c->requireFranchise();
        $stmt = $this->pdo->prepare("SELECT master_value_ref as plan_ref, code as plan_month, label as plan_data, status, created_at FROM catalog_master_values WHERE franchise_ref = :f AND master_key = 'tour_plans' AND status = 'ACTIVE' ORDER BY code DESC");
        $stmt->execute([':f' => $f]);
        $rows = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $data = json_decode($row['plan_data'] ?? '{}', true) ?: [];
            $rows[] = array_merge([
                'plan_ref'   => $row['plan_ref'],
                'plan_month' => $row['plan_month'],
                'status'     => $row['status'],
            ], $data);
        }
        return Response::json(200, $rows);
    }

    public function createTourPlan(Request $r): Response
    {
        $c = $this->ctx();
        $f = $c->requireFranchise();
        $clean = Validation::validate($r->all(), [
            'plan_month' => 'required|string',
            'days'       => 'required|array',
        ]);
        $ref = RefGenerator::generate('TPN');
        $stmt = $this->pdo->prepare("INSERT INTO catalog_master_values (master_value_ref, org_ref, franchise_ref, master_key, code, label, sort_order, status, created_by_ref) VALUES (:ref, :org, :f, 'tour_plans', :code, :label, 0, 'ACTIVE', :creator)");
        $stmt->execute([
            ':ref'     => $ref,
            ':org'     => $c->orgRef,
            ':f'       => $f,
            ':code'    => $clean['plan_month'],
            ':label'   => json_encode(['days' => $clean['days'], 'remarks' => $r->input('remarks')]),
            ':creator' => $c->userRef,
        ]);
        return Response::json(201, ['plan_ref' => $ref, 'plan_month' => $clean['plan_month'], 'status' => 'ACTIVE']);
    }

    public function updateTourPlan(Request $r, ?string $ref = null): Response
    {
        $c = $this->ctx();
        $f = $c->requireFranchise();
        $ref = $ref ?? (string)$r->param('ref');
        $stmt = $this->pdo->prepare("UPDATE catalog_master_values SET label = :data WHERE franchise_ref = :f AND master_value_ref = :r AND master_key = 'tour_plans'");
        $stmt->execute([':data' => json_encode(['days' => $r->input('days', []), 'remarks' => $r->input('remarks')]), ':f' => $f, ':r' => $ref]);
        return Response::json(200, ['plan_ref' => $ref, 'updated' => true]);
    }

    public function tourPlanComparison(Request $r, ?string $ref = null): Response
    {
        $c = $this->ctx();
        $f = $c->requireFranchise();
        $ref = $ref ?? (string)$r->param('ref');
        $stmt = $this->pdo->prepare("SELECT master_value_ref as plan_ref, code as plan_month, label as plan_data FROM catalog_master_values WHERE franchise_ref = :f AND master_value_ref = :r AND master_key = 'tour_plans' LIMIT 1");
        $stmt->execute([':f' => $f, ':r' => $ref]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) throw new NotFoundException('PLAN_NOT_FOUND', 'Tour plan not found.');
        $data = json_decode($row['plan_data'] ?? '{}', true) ?: [];

        $stmtDcr = $this->pdo->prepare("SELECT r.report_date, r.work_type, r.beat, r.status, COUNT(v.id) as visits_count FROM dcr_reports_v2 r LEFT JOIN dcr_visits_v2 v ON v.dcr_ref = r.dcr_ref WHERE r.franchise_ref = :f AND DATE_FORMAT(r.report_date, '%Y-%m') = :m GROUP BY r.dcr_ref");
        $stmtDcr->execute([':f' => $f, ':m' => $row['plan_month']]);
        $actuals = $stmtDcr->fetchAll(\PDO::FETCH_ASSOC);

        return Response::json(200, [
            'plan_ref'   => $ref,
            'plan_month' => $row['plan_month'],
            'planned'    => $data['days'] ?? [],
            'actual'     => $actuals,
        ]);
    }

    public function listPob(Request $r): Response
    {
        $c = $this->ctx();
        $f = $c->requireFranchise();
        $stmt = $this->pdo->prepare("SELECT v.visit_ref, v.dcr_ref, r.report_date, r.distributor_party_ref, v.party_ref, v.lead_ref, v.customer_type, v.pob_product_ref, p.product_name, v.pob_quantity, v.pob_value, v.feedback FROM dcr_visits_v2 v JOIN dcr_reports_v2 r ON r.franchise_ref = v.franchise_ref AND r.dcr_ref = v.dcr_ref LEFT JOIN products p ON p.franchise_ref = v.franchise_ref AND p.product_ref = v.pob_product_ref WHERE v.franchise_ref = :f AND v.pob_quantity > 0 ORDER BY r.report_date DESC");
        $stmt->execute([':f' => $f]);
        return Response::json(200, $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function convertToOrder(Request $r): Response
    {
        $c = $this->ctx();
        $f = $c->requireFranchise();
        $clean = Validation::validate($r->all(), [
            'visit_ref' => 'required|string',
        ]);
        $stmt = $this->pdo->prepare("SELECT v.*, r.distributor_party_ref, r.owner_user_ref FROM dcr_visits_v2 v JOIN dcr_reports_v2 r ON r.dcr_ref = v.dcr_ref WHERE v.franchise_ref = :f AND v.visit_ref = :v LIMIT 1");
        $stmt->execute([':f' => $f, ':v' => $clean['visit_ref']]);
        $visit = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$visit) throw new NotFoundException('VISIT_NOT_FOUND', 'Visit record not found.');
        if (empty($visit['pob_product_ref']) || empty($visit['pob_quantity'])) {
            throw new ValidationException('NO_POB_DATA', 'This visit has no POB product/quantity.');
        }

        $partyRef = $visit['party_ref'] ?? $visit['distributor_party_ref'];
        $order = $this->orderService->createOrder(
            orgRef: $c->orgRef,
            franchiseRef: $f,
            partyRef: $partyRef,
            clientOrderRef: 'POB-' . $visit['visit_ref'],
            channel: 'SALES',
            rawItems: [
                ['product_ref' => $visit['pob_product_ref'], 'paid_qty' => (int)$visit['pob_quantity']]
            ],
            salesUserRef: $visit['owner_user_ref'],
            remarks: 'Converted from POB Visit ' . $clean['visit_ref'],
            actorRef: $c->userRef,
        );

        return Response::json(201, [
            'order'     => $order,
            'visit_ref' => $clean['visit_ref'],
            'status'    => 'CONVERTED',
        ]);
    }

    public function dailySummary(Request $r): Response
    {
        $c = $this->ctx();
        $f = $c->requireFranchise();
        $date = $r->query('date', date('Y-m-d'));
        $stmt = $this->pdo->prepare("SELECT r.dcr_ref, r.report_date, r.owner_user_ref, u.full_name as user_name, r.work_type, r.beat, r.status, COUNT(v.id) as total_visits, COALESCE(SUM(v.pob_value), 0) as total_pob_value FROM dcr_reports_v2 r LEFT JOIN users u ON u.user_ref = r.owner_user_ref LEFT JOIN dcr_visits_v2 v ON v.dcr_ref = r.dcr_ref WHERE r.franchise_ref = :f AND r.report_date = :d GROUP BY r.dcr_ref");
        $stmt->execute([':f' => $f, ':d' => $date]);
        return Response::json(200, $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function monthlySummary(Request $r): Response
    {
        $c = $this->ctx();
        $f = $c->requireFranchise();
        $month = $r->query('month', date('Y-m'));
        $stmt = $this->pdo->prepare("SELECT r.owner_user_ref, u.full_name as user_name, COUNT(DISTINCT r.report_date) as working_days, SUM(r.work_type = 'FIELD_WORK') as field_days, SUM(r.work_type = 'LEAVE') as leave_days, COUNT(v.id) as total_visits, COALESCE(SUM(v.pob_value), 0) as total_pob_value FROM dcr_reports_v2 r LEFT JOIN users u ON u.user_ref = r.owner_user_ref LEFT JOIN dcr_visits_v2 v ON v.dcr_ref = r.dcr_ref WHERE r.franchise_ref = :f AND DATE_FORMAT(r.report_date, '%Y-%m') = :m GROUP BY r.owner_user_ref");
        $stmt->execute([':f' => $f, ':m' => $month]);
        return Response::json(200, $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function coverageReport(Request $r): Response
    {
        $c = $this->ctx();
        $f = $c->requireFranchise();
        $month = $r->query('month', date('Y-m'));
        $stmt = $this->pdo->prepare("SELECT r.beat, COUNT(DISTINCT v.party_ref) as parties_covered, COUNT(DISTINCT v.lead_ref) as leads_covered, COUNT(v.id) as total_calls FROM dcr_reports_v2 r JOIN dcr_visits_v2 v ON v.dcr_ref = r.dcr_ref WHERE r.franchise_ref = :f AND DATE_FORMAT(r.report_date, '%Y-%m') = :m GROUP BY r.beat");
        $stmt->execute([':f' => $f, ':m' => $month]);
        return Response::json(200, $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function productPromotionReport(Request $r): Response
    {
        $c = $this->ctx();
        $f = $c->requireFranchise();
        $stmt = $this->pdo->prepare("SELECT p.product_ref, p.product_name, COUNT(v.id) as times_promoted, COALESCE(SUM(v.pob_quantity), 0) as pob_qty, COALESCE(SUM(v.pob_value), 0) as pob_value FROM products p LEFT JOIN dcr_visits_v2 v ON v.franchise_ref = p.franchise_ref AND (v.pob_product_ref = p.product_ref OR v.products_promoted_json LIKE CONCAT('%', p.product_ref, '%')) WHERE p.franchise_ref = :f GROUP BY p.product_ref ORDER BY times_promoted DESC LIMIT 100");
        $stmt->execute([':f' => $f]);
        return Response::json(200, $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function missedDcrReport(Request $r): Response
    {
        $c = $this->ctx();
        $f = $c->requireFranchise();
        $month = $r->query('month', date('Y-m'));
        $stmt = $this->pdo->prepare("SELECT u.user_ref, u.full_name, COUNT(r.id) as dcr_filed_count, (26 - COUNT(r.id)) as estimated_missed FROM users u LEFT JOIN dcr_reports_v2 r ON r.franchise_ref = u.franchise_ref AND r.owner_user_ref = u.user_ref AND DATE_FORMAT(r.report_date, '%Y-%m') = :m WHERE u.franchise_ref = :f AND u.role IN ('SALES', 'DISTRIBUTOR') AND u.status = 'ACTIVE' GROUP BY u.user_ref");
        $stmt->execute([':f' => $f, ':m' => $month]);
        return Response::json(200, $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function sampleGiftReport(Request $r): Response
    {
        $c = $this->ctx();
        $f = $c->requireFranchise();
        $stmt = $this->pdo->prepare("SELECT v.visit_ref, r.report_date, u.full_name as user_name, v.customer_type, v.samples_given_json FROM dcr_visits_v2 v JOIN dcr_reports_v2 r ON r.dcr_ref = v.dcr_ref LEFT JOIN users u ON u.user_ref = r.owner_user_ref WHERE v.franchise_ref = :f AND v.samples_given_json IS NOT NULL AND v.samples_given_json != '[]' ORDER BY r.report_date DESC LIMIT 100");
        $stmt->execute([':f' => $f]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['samples_given'] = json_decode($r['samples_given_json'] ?? '[]', true);
            unset($r['samples_given_json']);
        }
        return Response::json(200, $rows);
    }

    public function expenseReport(Request $r): Response
    {
        $c = $this->ctx();
        $f = $c->requireFranchise();
        $month = $r->query('month', date('Y-m'));
        $stmt = $this->pdo->prepare("SELECT r.owner_user_ref, u.full_name as user_name, COUNT(DISTINCT r.report_date) as days_worked, COUNT(v.id) as total_visits, (COUNT(DISTINCT r.report_date) * 250.00) as estimated_allowance FROM dcr_reports_v2 r LEFT JOIN users u ON u.user_ref = r.owner_user_ref LEFT JOIN dcr_visits_v2 v ON v.dcr_ref = r.dcr_ref WHERE r.franchise_ref = :f AND DATE_FORMAT(r.report_date, '%Y-%m') = :m GROUP BY r.owner_user_ref");
        $stmt->execute([':f' => $f, ':m' => $month]);
        return Response::json(200, $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function pobSummaryReport(Request $r): Response
    {
        $c = $this->ctx();
        $f = $c->requireFranchise();
        $stmt = $this->pdo->prepare("SELECT p.product_ref, p.product_name, SUM(v.pob_quantity) as total_quantity, SUM(v.pob_value) as total_value, COUNT(DISTINCT v.visit_ref) as total_bookings FROM dcr_visits_v2 v JOIN products p ON p.franchise_ref = v.franchise_ref AND p.product_ref = v.pob_product_ref WHERE v.franchise_ref = :f AND v.pob_quantity > 0 GROUP BY p.product_ref ORDER BY total_value DESC");
        $stmt->execute([':f' => $f]);
        return Response::json(200, $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }
}
