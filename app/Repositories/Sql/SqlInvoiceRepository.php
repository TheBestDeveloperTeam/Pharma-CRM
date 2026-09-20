<?php
declare(strict_types=1);
namespace App\Repositories\Sql;

use App\Core\Database;
use App\Repositories\Contracts\InvoiceRepositoryInterface;

final class SqlInvoiceRepository implements InvoiceRepositoryInterface
{
    public function __construct(private Database $db) {}

    public function findByRef(string $franchiseRef, string $invoiceRef): ?array
    {
        return $this->db->fetchOne(
            "SELECT i.*, p.firm_name as party_name, p.gstin as party_gstin
             FROM invoices i
             JOIN parties p ON i.franchise_ref = p.franchise_ref AND i.party_ref = p.party_ref
             WHERE i.franchise_ref = :f AND i.invoice_ref = :r LIMIT 1",
            [':f' => $franchiseRef, ':r' => $invoiceRef]
        );
    }

    public function findByOrderRef(string $franchiseRef, string $orderRef): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM invoices WHERE franchise_ref = :f AND order_ref = :o LIMIT 1",
            [':f' => $franchiseRef, ':o' => $orderRef]
        );
    }

    public function list(string $franchiseRef, array $filters, int $page, int $perPage, ?string $partyRef = null): array
    {
        $where = ["i.franchise_ref = :f"];
        $params = [':f' => $franchiseRef];

        if ($partyRef !== null) {
            $where[] = "i.party_ref = :p";
            $params[':p'] = $partyRef;
        }

        if (!empty($filters['status'])) {
            $where[] = "i.status = :s";
            $params[':s'] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $where[] = "(i.invoice_no LIKE :s1 OR p.firm_name LIKE :s2)";
            $term = '%' . trim($filters['search']) . '%';
            $params[':s1'] = $term;
            $params[':s2'] = $term;
        }

        $whereClause = implode(' AND ', $where);

        $total = (int)$this->db->fetchOne(
            "SELECT COUNT(*) as cnt FROM invoices i JOIN parties p ON i.franchise_ref = p.franchise_ref AND i.party_ref = p.party_ref WHERE {$whereClause}",
            $params
        )['cnt'];

        $offset = ($page - 1) * $perPage;
        $sql = "SELECT i.*, p.firm_name as party_name
                FROM invoices i
                JOIN parties p ON i.franchise_ref = p.franchise_ref AND i.party_ref = p.party_ref
                WHERE {$whereClause}
                ORDER BY i.id DESC LIMIT {$perPage} OFFSET {$offset}";

        $rows = $this->db->fetchAll($sql, $params);

        return [
            'data' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    public function create(array $invoiceData, array $items): string
    {
        $this->db->transaction(function() use ($invoiceData, $items) {
            $sqlInv = "INSERT INTO invoices (
                invoice_ref, invoice_no, org_ref, franchise_ref, order_ref,
                party_ref, invoice_date, due_date, bill_to_snapshot, ship_to_snapshot,
                subtotal, discount_total, gst_total, grand_total, paid_total,
                status, created_by_ref
            ) VALUES (
                :invoice_ref, :invoice_no, :org_ref, :franchise_ref, :order_ref,
                :party_ref, :invoice_date, :due_date, :bill_to_snapshot, :ship_to_snapshot,
                :subtotal, :discount_total, :gst_total, :grand_total, 0.00,
                'POSTED', :created_by_ref
            )";

            $this->db->prepare($sqlInv)->execute([
                ':invoice_ref'       => $invoiceData['invoice_ref'],
                ':invoice_no'        => $invoiceData['invoice_no'],
                ':org_ref'           => $invoiceData['org_ref'],
                ':franchise_ref'     => $invoiceData['franchise_ref'],
                ':order_ref'         => $invoiceData['order_ref'],
                ':party_ref'         => $invoiceData['party_ref'],
                ':invoice_date'      => $invoiceData['invoice_date'],
                ':due_date'          => $invoiceData['due_date'] ?? null,
                ':bill_to_snapshot'  => json_encode($invoiceData['bill_to_snapshot'], JSON_THROW_ON_ERROR),
                ':ship_to_snapshot'  => json_encode($invoiceData['ship_to_snapshot'], JSON_THROW_ON_ERROR),
                ':subtotal'          => $invoiceData['subtotal'],
                ':discount_total'    => $invoiceData['discount_total'],
                ':gst_total'         => $invoiceData['gst_total'],
                ':grand_total'       => $invoiceData['grand_total'],
                ':created_by_ref'    => $invoiceData['created_by_ref'],
            ]);

            $sqlItem = "INSERT INTO invoice_items (
                item_ref, org_ref, franchise_ref, invoice_ref, order_item_ref,
                product_ref, product_name_snapshot, sku_snapshot, hsn_snapshot,
                batch_ref, batch_no_snapshot, expiry_snapshot, paid_qty,
                free_qty, rate, discount, gst_percent, line_total
            ) VALUES (
                :item_ref, :org_ref, :franchise_ref, :invoice_ref, :order_item_ref,
                :product_ref, :product_name_snapshot, :sku_snapshot, :hsn_snapshot,
                :batch_ref, :batch_no_snapshot, :expiry_snapshot, :paid_qty,
                :free_qty, :rate, :discount, :gst_percent, :line_total
            )";

            $stmtItem = $this->db->prepare($sqlItem);
            foreach ($items as $it) {
                $stmtItem->execute([
                    ':item_ref'              => $it['item_ref'],
                    ':org_ref'               => $invoiceData['org_ref'],
                    ':franchise_ref'         => $invoiceData['franchise_ref'],
                    ':invoice_ref'           => $invoiceData['invoice_ref'],
                    ':order_item_ref'        => $it['order_item_ref'],
                    ':product_ref'           => $it['product_ref'],
                    ':product_name_snapshot' => $it['product_name_snapshot'],
                    ':sku_snapshot'          => $it['sku_snapshot'],
                    ':hsn_snapshot'          => $it['hsn_snapshot'] ?? null,
                    ':batch_ref'             => $it['batch_ref'],
                    ':batch_no_snapshot'     => $it['batch_no_snapshot'],
                    ':expiry_snapshot'       => $it['expiry_snapshot'],
                    ':paid_qty'              => $it['paid_qty'],
                    ':free_qty'              => $it['free_qty'] ?? 0,
                    ':rate'                  => $it['rate'],
                    ':discount'              => $it['discount'] ?? 0.00,
                    ':gst_percent'           => $it['gst_percent'] ?? 0.00,
                    ':line_total'            => $it['line_total'],
                ]);
            }
        });

        return $invoiceData['invoice_ref'];
    }

    public function updatePaidTotal(string $franchiseRef, string $invoiceRef, float $paidTotal): bool
    {
        $sql = "UPDATE invoices SET paid_total = :p WHERE franchise_ref = :f AND invoice_ref = :r";
        return $this->db->prepare($sql)->execute([
            ':p' => $paidTotal,
            ':f' => $franchiseRef,
            ':r' => $invoiceRef,
        ]);
    }

    public function cancel(string $franchiseRef, string $invoiceRef, string $reason): bool
    {
        $sql = "UPDATE invoices SET status = 'CANCELLED', cancel_reason = :reason WHERE franchise_ref = :f AND invoice_ref = :r";
        return $this->db->prepare($sql)->execute([
            ':reason' => $reason,
            ':f'      => $franchiseRef,
            ':r'      => $invoiceRef,
        ]);
    }

    public function getItems(string $franchiseRef, string $invoiceRef): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM invoice_items WHERE franchise_ref = :f AND invoice_ref = :r ORDER BY id ASC",
            [':f' => $franchiseRef, ':r' => $invoiceRef]
        );
    }
}
