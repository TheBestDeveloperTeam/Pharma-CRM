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
            "SELECT i.*, p.firm_name as party_name, p.gstin as party_gstin, o.sales_user_ref
             FROM invoices i
             JOIN parties p ON i.franchise_ref = p.franchise_ref AND i.party_ref = p.party_ref
             JOIN orders o ON i.franchise_ref = o.franchise_ref AND i.order_ref = o.order_ref
             WHERE i.franchise_ref = :f AND i.invoice_ref = :r LIMIT 1",
            [':f' => $franchiseRef, ':r' => $invoiceRef]
        );
    }

    public function findByRefForUpdate(string $franchiseRef, string $invoiceRef): ?array
    {
        return $this->db->fetchOne('SELECT * FROM invoices WHERE franchise_ref = ? AND invoice_ref = ? LIMIT 1 FOR UPDATE', [$franchiseRef, $invoiceRef]);
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
        $sql = "SELECT i.*, p.firm_name as party_name, o.sales_user_ref
                FROM invoices i
                JOIN parties p ON i.franchise_ref = p.franchise_ref AND i.party_ref = p.party_ref
                JOIN orders o ON i.franchise_ref = o.franchise_ref AND i.order_ref = o.order_ref
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
                subtotal, discount_total, taxable_total, cgst_total, sgst_total, igst_total, gst_total,
                rounding_adjustment, grand_total, tax_policy_code, paid_total, status, created_by_ref
            ) VALUES (
                :invoice_ref, :invoice_no, :org_ref, :franchise_ref, :order_ref,
                :party_ref, :invoice_date, :due_date, :bill_to_snapshot, :ship_to_snapshot,
                :subtotal, :discount_total, :taxable_total, :cgst_total, :sgst_total, :igst_total, :gst_total,
                :rounding_adjustment, :grand_total, :tax_policy_code, 0.00, 'POSTED', :created_by_ref
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
                ':taxable_total'     => $invoiceData['taxable_total'],
                ':cgst_total'        => $invoiceData['cgst_total'],
                ':sgst_total'        => $invoiceData['sgst_total'],
                ':igst_total'        => $invoiceData['igst_total'],
                ':gst_total'         => $invoiceData['gst_total'],
                ':rounding_adjustment' => $invoiceData['rounding_adjustment'],
                ':grand_total'       => $invoiceData['grand_total'],
                ':tax_policy_code'   => $invoiceData['tax_policy_code'],
                ':created_by_ref'    => $invoiceData['created_by_ref'],
            ]);

            $sqlItem = "INSERT INTO invoice_items (
                item_ref, org_ref, franchise_ref, invoice_ref, order_item_ref,
                product_ref, product_name_snapshot, sku_snapshot, hsn_snapshot,
                batch_ref, batch_no_snapshot, expiry_snapshot, paid_qty,
                free_qty, rate, discount, taxable_amount, gst_percent, cgst_amount, sgst_amount, igst_amount, total_tax, line_total
            ) VALUES (
                :item_ref, :org_ref, :franchise_ref, :invoice_ref, :order_item_ref,
                :product_ref, :product_name_snapshot, :sku_snapshot, :hsn_snapshot,
                :batch_ref, :batch_no_snapshot, :expiry_snapshot, :paid_qty,
                :free_qty, :rate, :discount, :taxable_amount, :gst_percent, :cgst_amount, :sgst_amount, :igst_amount, :total_tax, :line_total
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
                    ':taxable_amount'        => $it['taxable_amount'] ?? $it['line_total'],
                    ':gst_percent'           => $it['gst_percent'] ?? 0.00,
                    ':cgst_amount'           => $it['cgst_amount'] ?? 0.00,
                    ':sgst_amount'           => $it['sgst_amount'] ?? 0.00,
                    ':igst_amount'           => $it['igst_amount'] ?? 0.00,
                    ':total_tax'             => $it['total_tax'] ?? 0.00,
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

    public function cancelPosted(string $franchiseRef, string $invoiceRef, string $actorRef, string $reason): bool
    {
        $sql = "UPDATE invoices SET status = 'CANCELLED', cancel_reason = :reason, cancelled_by_ref = :actor, cancelled_at = NOW() WHERE franchise_ref = :f AND invoice_ref = :r AND status = 'POSTED'";
        $statement = $this->db->prepare($sql);
        $statement->execute([
            ':reason' => $reason,
            ':actor'  => $actorRef,
            ':f'      => $franchiseRef,
            ':r'      => $invoiceRef,
        ]);
        return $statement->rowCount() === 1;
    }

    public function getItems(string $franchiseRef, string $invoiceRef): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM invoice_items WHERE franchise_ref = :f AND invoice_ref = :r ORDER BY id ASC",
            [':f' => $franchiseRef, ':r' => $invoiceRef]
        );
    }
}
