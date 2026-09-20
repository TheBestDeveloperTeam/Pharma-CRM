<?php
declare(strict_types=1);
namespace App\Repositories\Sql;

use App\Core\Database;
use App\Repositories\Contracts\LeadRepositoryInterface;

final class SqlLeadRepository implements LeadRepositoryInterface
{
    public function __construct(private Database $db) {}

    public function list(string $franchiseRef, array $filters, int $page, int $perPage, ?string $assignedUserRef = null): array
    {
        $offset = ($page - 1) * $perPage;
        $params = [':f' => $franchiseRef];
        $where = ["franchise_ref = :f"];

        if ($assignedUserRef !== null) {
            $where[] = "assigned_user_ref = :assigned_user";
            $params[':assigned_user'] = $assignedUserRef;
        }

        if (!empty($filters['status'])) {
            $where[] = "status = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $where[] = "(contact_name LIKE :s1 OR firm_name LIKE :s2 OR mobile_norm LIKE :s3)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[':s1'] = $searchTerm;
            $params[':s2'] = $searchTerm;
            $params[':s3'] = $searchTerm;
        }

        $whereSql = implode(' AND ', $where);

        $total = (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM leads WHERE {$whereSql}",
            $params
        );

        $sql = "SELECT * FROM leads WHERE {$whereSql} ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}";
        $items = $this->db->fetchAll($sql, $params);

        return [
            'items'       => $items,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => (int)ceil($total / $perPage),
        ];
    }

    public function findByRef(string $franchiseRef, string $leadRef): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM leads WHERE franchise_ref = :f AND lead_ref = :r LIMIT 1",
            [':f' => $franchiseRef, ':r' => $leadRef]
        );
    }

    public function findByMobile(string $franchiseRef, string $mobileNorm): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM leads WHERE franchise_ref = :f AND mobile_norm = :m AND status NOT IN ('CONVERTED', 'LOST', 'REJECTED', 'ARCHIVED') ORDER BY id DESC LIMIT 1",
            [':f' => $franchiseRef, ':m' => $mobileNorm]
        );
    }

    public function create(array $data): string
    {
        $sql = "INSERT INTO leads (
            lead_ref, org_ref, franchise_ref, external_source_ref, external_lead_id,
            contact_name, firm_name, mobile, mobile_norm, email,
            state_ref, district_ref, city_ref, pincode,
            lead_source, business_type, interested_products,
            assigned_user_ref, priority, status, initial_remark,
            first_response_at, next_follow_up_at, created_by_ref
        ) VALUES (
            :lead_ref, :org_ref, :franchise_ref, :external_source_ref, :external_lead_id,
            :contact_name, :firm_name, :mobile, :mobile_norm, :email,
            :state_ref, :district_ref, :city_ref, :pincode,
            :lead_source, :business_type, :interested_products,
            :assigned_user_ref, :priority, :status, :initial_remark,
            :first_response_at, :next_follow_up_at, :created_by_ref
        )";

        $this->db->prepare($sql)->execute([
            ':lead_ref'            => $data['lead_ref'],
            ':org_ref'             => $data['org_ref'],
            ':franchise_ref'       => $data['franchise_ref'],
            ':external_source_ref' => $data['external_source_ref'] ?? null,
            ':external_lead_id'    => $data['external_lead_id'] ?? null,
            ':contact_name'        => $data['contact_name'],
            ':firm_name'           => $data['firm_name'] ?? null,
            ':mobile'              => $data['mobile'],
            ':mobile_norm'         => $data['mobile_norm'],
            ':email'               => $data['email'] ?? null,
            ':state_ref'           => $data['state_ref'] ?? null,
            ':district_ref'        => $data['district_ref'] ?? null,
            ':city_ref'            => $data['city_ref'] ?? null,
            ':pincode'             => $data['pincode'] ?? null,
            ':lead_source'         => $data['lead_source'] ?? 'MANUAL',
            ':business_type'       => $data['business_type'] ?? null,
            ':interested_products' => $data['interested_products'] ?? null,
            ':assigned_user_ref'   => $data['assigned_user_ref'] ?? null,
            ':priority'            => $data['priority'] ?? 'NORMAL',
            ':status'              => $data['status'] ?? 'NEW',
            ':initial_remark'      => $data['initial_remark'] ?? null,
            ':first_response_at'   => $data['first_response_at'] ?? null,
            ':next_follow_up_at'   => $data['next_follow_up_at'] ?? null,
            ':created_by_ref'      => $data['created_by_ref'],
        ]);

        return $data['lead_ref'];
    }

    public function update(string $franchiseRef, string $leadRef, array $data): bool
    {
        $allowed = [
            'contact_name', 'firm_name', 'mobile', 'mobile_norm', 'email',
            'state_ref', 'district_ref', 'city_ref', 'pincode',
            'lead_source', 'business_type', 'interested_products',
            'priority', 'initial_remark', 'first_response_at', 'next_follow_up_at', 'updated_by_ref'
        ];

        $sets = [];
        $params = [':f' => $franchiseRef, ':r' => $leadRef];

        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) {
                $sets[] = "{$f} = :{$f}";
                $params[":{$f}"] = $data[$f];
            }
        }

        if (empty($sets)) {
            return false;
        }

        $sql = "UPDATE leads SET " . implode(', ', $sets) . ", updated_at = NOW() WHERE franchise_ref = :f AND lead_ref = :r";
        return $this->db->prepare($sql)->execute($params);
    }

    public function updateStatus(string $franchiseRef, string $leadRef, string $status, ?string $convertedPartyRef = null): bool
    {
        $sql = "UPDATE leads SET status = :s, converted_party_ref = :c, updated_at = NOW() WHERE franchise_ref = :f AND lead_ref = :r";
        return $this->db->prepare($sql)->execute([
            ':s' => $status,
            ':c' => $convertedPartyRef,
            ':f' => $franchiseRef,
            ':r' => $leadRef,
        ]);
    }

    public function assign(string $franchiseRef, string $leadRef, ?string $userRef): bool
    {
        $status = $userRef ? 'ASSIGNED' : 'NEW';
        $sql = "UPDATE leads SET assigned_user_ref = :u, status = IF(status = 'NEW', :s, status), updated_at = NOW() WHERE franchise_ref = :f AND lead_ref = :r";
        return $this->db->prepare($sql)->execute([
            ':u' => $userRef,
            ':s' => $status,
            ':f' => $franchiseRef,
            ':r' => $leadRef,
        ]);
    }

    public function addActivity(string $franchiseRef, array $act): string
    {
        $sql = "INSERT INTO lead_activities (
            activity_ref, org_ref, franchise_ref, lead_ref, user_ref,
            activity_type, from_status, to_status, activity_note
        ) VALUES (
            :activity_ref, :org_ref, :franchise_ref, :lead_ref, :user_ref,
            :activity_type, :from_status, :to_status, :activity_note
        )";

        $this->db->prepare($sql)->execute([
            ':activity_ref' => $act['activity_ref'],
            ':org_ref'      => $act['org_ref'],
            ':franchise_ref' => $franchiseRef,
            ':lead_ref'     => $act['lead_ref'],
            ':user_ref'     => $act['user_ref'],
            ':activity_type' => $act['activity_type'],
            ':from_status'  => $act['from_status'] ?? null,
            ':to_status'    => $act['to_status'] ?? null,
            ':activity_note' => $act['activity_note'] ?? null,
        ]);

        return $act['activity_ref'];
    }

    public function getActivities(string $franchiseRef, string $leadRef): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM lead_activities WHERE franchise_ref = :f AND lead_ref = :r ORDER BY created_at ASC",
            [':f' => $franchiseRef, ':r' => $leadRef]
        );
    }
}
