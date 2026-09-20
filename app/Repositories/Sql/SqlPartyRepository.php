<?php
declare(strict_types=1);
namespace App\Repositories\Sql;

use App\Core\Database;
use App\Repositories\Contracts\PartyRepositoryInterface;

final class SqlPartyRepository implements PartyRepositoryInterface
{
    public function __construct(private Database $db) {}

    public function list(string $franchiseRef, array $filters, int $page, int $perPage, ?string $salesUserRef = null): array
    {
        $offset = ($page - 1) * $perPage;
        $params = [':f' => $franchiseRef];
        $where = ["franchise_ref = :f"];

        if ($salesUserRef !== null) {
            $where[] = "sales_user_ref = :sales_user";
            $params[':sales_user'] = $salesUserRef;
        }

        if (!empty($filters['status'])) {
            $where[] = "status = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $where[] = "(firm_name LIKE :s1 OR party_code LIKE :s2 OR contact_name LIKE :s3 OR mobile LIKE :s4)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[':s1'] = $searchTerm;
            $params[':s2'] = $searchTerm;
            $params[':s3'] = $searchTerm;
            $params[':s4'] = $searchTerm;
        }

        $whereSql = implode(' AND ', $where);

        $total = (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM parties WHERE {$whereSql}",
            $params
        );

        $sql = "SELECT * FROM parties WHERE {$whereSql} ORDER BY firm_name ASC LIMIT {$perPage} OFFSET {$offset}";
        $items = $this->db->fetchAll($sql, $params);

        return [
            'items'       => $items,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => (int)ceil($total / $perPage),
        ];
    }

    public function findByRef(string $franchiseRef, string $partyRef): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM parties WHERE franchise_ref = :f AND party_ref = :r LIMIT 1",
            [':f' => $franchiseRef, ':r' => $partyRef]
        );
    }

    public function findByCode(string $franchiseRef, string $partyCode): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM parties WHERE franchise_ref = :f AND party_code = :c LIMIT 1",
            [':f' => $franchiseRef, ':c' => $partyCode]
        );
    }

    public function create(array $data): string
    {
        $sql = "INSERT INTO parties (
            party_ref, org_ref, franchise_ref, party_code, firm_name, contact_name,
            mobile, email, gstin, drug_license_no,
            billing_address, shipping_address,
            state_ref, district_ref, city_ref, pincode,
            tier_ref, sales_user_ref, agreement_from, agreement_to,
            credit_limit, payment_terms_days, opening_outstanding,
            converted_from_lead_ref, status, created_by_ref
        ) VALUES (
            :party_ref, :org_ref, :franchise_ref, :party_code, :firm_name, :contact_name,
            :mobile, :email, :gstin, :drug_license_no,
            :billing_address, :shipping_address,
            :state_ref, :district_ref, :city_ref, :pincode,
            :tier_ref, :sales_user_ref, :agreement_from, :agreement_to,
            :credit_limit, :payment_terms_days, :opening_outstanding,
            :converted_from_lead_ref, :status, :created_by_ref
        )";

        $this->db->prepare($sql)->execute([
            ':party_ref'                => $data['party_ref'],
            ':org_ref'                  => $data['org_ref'],
            ':franchise_ref'            => $data['franchise_ref'],
            ':party_code'               => $data['party_code'],
            ':firm_name'                => $data['firm_name'],
            ':contact_name'             => $data['contact_name'] ?? null,
            ':mobile'                   => $data['mobile'] ?? null,
            ':email'                    => $data['email'] ?? null,
            ':gstin'                    => $data['gstin'] ?? null,
            ':drug_license_no'          => $data['drug_license_no'] ?? null,
            ':billing_address'          => $data['billing_address'] ?? null,
            ':shipping_address'         => $data['shipping_address'] ?? null,
            ':state_ref'                => $data['state_ref'] ?? null,
            ':district_ref'             => $data['district_ref'] ?? null,
            ':city_ref'                 => $data['city_ref'] ?? null,
            ':pincode'                  => $data['pincode'] ?? null,
            ':tier_ref'                 => $data['tier_ref'] ?? null,
            ':sales_user_ref'           => $data['sales_user_ref'] ?? null,
            ':agreement_from'           => $data['agreement_from'] ?? null,
            ':agreement_to'             => $data['agreement_to'] ?? null,
            ':credit_limit'             => $data['credit_limit'] ?? 0.00,
            ':payment_terms_days'       => $data['payment_terms_days'] ?? 0,
            ':opening_outstanding'      => $data['opening_outstanding'] ?? 0.00,
            ':converted_from_lead_ref'  => $data['converted_from_lead_ref'] ?? null,
            ':status'                   => $data['status'] ?? 'ACTIVE',
            ':created_by_ref'           => $data['created_by_ref'],
        ]);

        return $data['party_ref'];
    }

    public function update(string $franchiseRef, string $partyRef, array $data): bool
    {
        $allowed = [
            'firm_name', 'contact_name', 'mobile', 'email', 'gstin', 'drug_license_no',
            'billing_address', 'shipping_address', 'state_ref', 'district_ref', 'city_ref', 'pincode',
            'tier_ref', 'sales_user_ref', 'agreement_from', 'agreement_to',
            'credit_limit', 'payment_terms_days', 'opening_outstanding', 'updated_by_ref'
        ];

        $sets = [];
        $params = [':f' => $franchiseRef, ':r' => $partyRef];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = "{$field} = :{$field}";
                $params[":{$field}"] = $data[$field];
            }
        }

        if (empty($sets)) {
            return false;
        }

        $sql = "UPDATE parties SET " . implode(', ', $sets) . ", updated_at = NOW() WHERE franchise_ref = :f AND party_ref = :r";
        return $this->db->prepare($sql)->execute($params);
    }

    public function setStatus(string $franchiseRef, string $partyRef, string $status): bool
    {
        $sql = "UPDATE parties SET status = :s, updated_at = NOW() WHERE franchise_ref = :f AND party_ref = :r";
        return $this->db->prepare($sql)->execute([
            ':s' => $status,
            ':f' => $franchiseRef,
            ':r' => $partyRef,
        ]);
    }

    public function getLedgerSummary(string $franchiseRef, string $partyRef): array
    {
        $party = $this->findByRef($franchiseRef, $partyRef);
        $opening = (float)($party['opening_outstanding'] ?? 0.00);

        // Invoices total (posted)
        $invoicedTotal = (float)$this->db->fetchColumn(
            "SELECT IFNULL(SUM(grand_total), 0.00) FROM invoices WHERE franchise_ref = :f AND party_ref = :r AND status = 'POSTED'",
            [':f' => $franchiseRef, ':r' => $partyRef]
        );

        // Payments total (allocated/recorded)
        $paidTotal = (float)$this->db->fetchColumn(
            "SELECT IFNULL(SUM(amount), 0.00) FROM payments WHERE franchise_ref = :f AND party_ref = :r AND status IN ('RECORDED', 'ALLOCATED', 'PARTIALLY_ALLOCATED')",
            [':f' => $franchiseRef, ':r' => $partyRef]
        );

        $currentOutstanding = $opening + $invoicedTotal - $paidTotal;

        return [
            'party_ref'           => $partyRef,
            'opening_outstanding' => number_format($opening, 2, '.', ''),
            'invoiced_total'      => number_format($invoicedTotal, 2, '.', ''),
            'paid_total'          => number_format($paidTotal, 2, '.', ''),
            'current_outstanding' => number_format($currentOutstanding, 2, '.', ''),
            'credit_limit'        => $party['credit_limit'] ?? '0.00',
        ];
    }
}
