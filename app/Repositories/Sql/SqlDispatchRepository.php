<?php
declare(strict_types=1);
namespace App\Repositories\Sql;

use App\Core\Database;
use App\Repositories\Contracts\DispatchRepositoryInterface;

final class SqlDispatchRepository implements DispatchRepositoryInterface
{
    public function __construct(private Database $db) {}

    public function findByRef(string $franchiseRef, string $dispatchRef): ?array
    {
        return $this->db->fetchOne(
            "SELECT d.*, i.invoice_no, i.party_ref, p.firm_name as party_name, t.transporter_name
             FROM dispatches d
             JOIN invoices i ON d.franchise_ref = i.franchise_ref AND d.invoice_ref = i.invoice_ref
             JOIN parties p ON i.franchise_ref = p.franchise_ref AND i.party_ref = p.party_ref
             LEFT JOIN transporters t ON d.franchise_ref = t.franchise_ref AND d.transporter_ref = t.transporter_ref
             WHERE d.franchise_ref = :f AND d.dispatch_ref = :r LIMIT 1",
            [':f' => $franchiseRef, ':r' => $dispatchRef]
        );
    }

    public function findByInvoiceRef(string $franchiseRef, string $invoiceRef): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM dispatches WHERE franchise_ref = :f AND invoice_ref = :inv LIMIT 1",
            [':f' => $franchiseRef, ':inv' => $invoiceRef]
        );
    }

    public function list(string $franchiseRef, array $filters, int $page, int $perPage): array
    {
        $where = ["d.franchise_ref = :f"];
        $params = [':f' => $franchiseRef];

        if (!empty($filters['status'])) {
            $where[] = "d.status = :s";
            $params[':s'] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $where[] = "(d.dispatch_no LIKE :s1 OR d.lr_number LIKE :s2 OR i.invoice_no LIKE :s3)";
            $term = '%' . trim($filters['search']) . '%';
            $params[':s1'] = $term;
            $params[':s2'] = $term;
            $params[':s3'] = $term;
        }

        $whereClause = implode(' AND ', $where);

        $total = (int)$this->db->fetchOne(
            "SELECT COUNT(*) as cnt FROM dispatches d JOIN invoices i ON d.franchise_ref = i.franchise_ref AND d.invoice_ref = i.invoice_ref WHERE {$whereClause}",
            $params
        )['cnt'];

        $offset = ($page - 1) * $perPage;
        $sql = "SELECT d.*, i.invoice_no, p.firm_name as party_name, t.transporter_name
                FROM dispatches d
                JOIN invoices i ON d.franchise_ref = i.franchise_ref AND d.invoice_ref = i.invoice_ref
                JOIN parties p ON i.franchise_ref = p.franchise_ref AND i.party_ref = p.party_ref
                LEFT JOIN transporters t ON d.franchise_ref = t.franchise_ref AND d.transporter_ref = t.transporter_ref
                WHERE {$whereClause}
                ORDER BY d.id DESC LIMIT {$perPage} OFFSET {$offset}";

        $rows = $this->db->fetchAll($sql, $params);

        return [
            'data' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    public function create(array $data): string
    {
        $sql = "INSERT INTO dispatches (
            dispatch_ref, dispatch_no, org_ref, franchise_ref, invoice_ref,
            transporter_ref, lr_number, tracking_url, dispatch_date, boxes,
            status, remarks, created_by_ref
        ) VALUES (
            :dispatch_ref, :dispatch_no, :org_ref, :franchise_ref, :invoice_ref,
            :transporter_ref, :lr_number, :tracking_url, :dispatch_date, :boxes,
            :status, :remarks, :created_by_ref
        )";

        $this->db->prepare($sql)->execute([
            ':dispatch_ref'    => $data['dispatch_ref'],
            ':dispatch_no'     => $data['dispatch_no'],
            ':org_ref'         => $data['org_ref'],
            ':franchise_ref'   => $data['franchise_ref'],
            ':invoice_ref'     => $data['invoice_ref'],
            ':transporter_ref' => $data['transporter_ref'] ?? null,
            ':lr_number'       => $data['lr_number'] ?? null,
            ':tracking_url'    => $data['tracking_url'] ?? null,
            ':dispatch_date'   => $data['dispatch_date'] ?? null,
            ':boxes'           => $data['boxes'] ?? 1,
            ':status'          => $data['status'] ?? 'PENDING',
            ':remarks'         => $data['remarks'] ?? null,
            ':created_by_ref'  => $data['created_by_ref'],
        ]);

        return $data['dispatch_ref'];
    }

    public function updateStatus(string $franchiseRef, string $dispatchRef, string $status): bool
    {
        $sql = "UPDATE dispatches SET status = :s, updated_at = NOW() WHERE franchise_ref = :f AND dispatch_ref = :r";
        return $this->db->prepare($sql)->execute([
            ':s' => $status,
            ':f' => $franchiseRef,
            ':r' => $dispatchRef,
        ]);
    }
}
