<?php
declare(strict_types=1);
namespace App\Domain\Reports;

use App\Core\Database;

final class ReportService
{
    public function __construct(private Database $db) {}

    public function generate(string $franchiseRef, string $type, array $filters = []): array
    {
        return match ($type) {
            'sales-summary'      => $this->salesSummary($franchiseRef, $filters),
            'orders-register'    => $this->ordersRegister($franchiseRef, $filters),
            'invoices-register'  => $this->invoicesRegister($franchiseRef, $filters),
            'payments-register'  => $this->paymentsRegister($franchiseRef, $filters),
            'outstanding'        => $this->outstandingStatement($franchiseRef, $filters),
            'party-ledger'       => $this->partyLedger($franchiseRef, $filters),
            'inventory-status'   => $this->inventoryStatus($franchiseRef, $filters),
            'near-expiry'        => $this->nearExpiry($franchiseRef, $filters),
            'lead-funnel'        => $this->leadFunnel($franchiseRef, $filters),
            'scheme-utilization' => $this->schemeUtilization($franchiseRef, $filters),
            'audit-log'          => $this->auditLogExport($franchiseRef, $filters),
            'tenant-activity'    => $this->tenantActivity($franchiseRef, $filters),
            default              => throw new \InvalidArgumentException("Invalid report type: {$type}")
        };
    }

    private function salesSummary(string $franchiseRef, array $filters): array
    {
        $sql = "SELECT DATE_FORMAT(invoice_date, '%Y-%m') AS month,
                       COUNT(id) AS total_invoices,
                       SUM(subtotal) AS gross_sales,
                       SUM(discount_total) AS total_discounts,
                       SUM(gst_total) AS total_gst,
                       SUM(grand_total) AS net_revenue
                FROM invoices
                WHERE franchise_ref = :f AND status = 'POSTED'
                GROUP BY DATE_FORMAT(invoice_date, '%Y-%m')
                ORDER BY month DESC";
        return $this->db->fetchAll($sql, [':f' => $franchiseRef]);
    }

    private function ordersRegister(string $franchiseRef, array $filters): array
    {
        $sql = "SELECT o.order_ref, o.order_no, o.client_order_ref, o.status, o.subtotal, o.gst_total, o.grand_total,
                       o.created_at, p.firm_name, p.party_code
                FROM orders o
                JOIN parties p ON o.franchise_ref = p.franchise_ref AND o.party_ref = p.party_ref
                WHERE o.franchise_ref = :f
                ORDER BY o.created_at DESC LIMIT 500";
        return $this->db->fetchAll($sql, [':f' => $franchiseRef]);
    }

    private function invoicesRegister(string $franchiseRef, array $filters): array
    {
        $sql = "SELECT i.invoice_ref, i.invoice_no, i.order_ref, i.invoice_date, i.subtotal, i.discount_total,
                       i.gst_total, i.grand_total, i.paid_total, i.status, p.firm_name, p.party_code
                FROM invoices i
                JOIN parties p ON i.franchise_ref = p.franchise_ref AND i.party_ref = p.party_ref
                WHERE i.franchise_ref = :f
                ORDER BY i.invoice_date DESC LIMIT 500";
        return $this->db->fetchAll($sql, [':f' => $franchiseRef]);
    }

    private function paymentsRegister(string $franchiseRef, array $filters): array
    {
        $sql = "SELECT py.payment_ref, py.payment_no, py.amount, py.payment_mode, py.payment_date, py.status,
                       p.firm_name, p.party_code
                FROM payments py
                JOIN parties p ON py.franchise_ref = p.franchise_ref AND py.party_ref = p.party_ref
                WHERE py.franchise_ref = :f
                ORDER BY py.payment_date DESC LIMIT 500";
        return $this->db->fetchAll($sql, [':f' => $franchiseRef]);
    }

    private function outstandingStatement(string $franchiseRef, array $filters): array
    {
        $sql = "SELECT p.party_ref, p.party_code, p.firm_name, p.credit_limit,
                       COALESCE(SUM(i.grand_total - i.paid_total), 0) AS current_outstanding
                FROM parties p
                LEFT JOIN invoices i ON p.franchise_ref = i.franchise_ref AND p.party_ref = i.party_ref AND i.status = 'POSTED'
                WHERE p.franchise_ref = :f AND p.status = 'ACTIVE'
                GROUP BY p.party_ref, p.party_code, p.firm_name, p.credit_limit
                ORDER BY current_outstanding DESC";
        return $this->db->fetchAll($sql, [':f' => $franchiseRef]);
    }

    private function partyLedger(string $franchiseRef, array $filters): array
    {
        $partyRef = $filters['party_ref'] ?? null;
        if (!$partyRef) {
            return [];
        }

        $invoices = $this->db->fetchAll(
            "SELECT invoice_date AS trans_date, invoice_no AS doc_no, 'INVOICE' AS type, grand_total AS debit, 0.00 AS credit
             FROM invoices
             WHERE franchise_ref = :f AND party_ref = :p AND status = 'POSTED'",
            [':f' => $franchiseRef, ':p' => $partyRef]
        );

        $payments = $this->db->fetchAll(
            "SELECT payment_date AS trans_date, payment_no AS doc_no, 'PAYMENT' AS type, 0.00 AS debit, amount AS credit
             FROM payments
             WHERE franchise_ref = :f AND party_ref = :p AND status = 'COLLECTED'",
            [':f' => $franchiseRef, ':p' => $partyRef]
        );

        $entries = array_merge($invoices, $payments);
        usort($entries, fn($a, $b) => strcmp($a['trans_date'], $b['trans_date']));

        $balance = 0.0;
        foreach ($entries as &$e) {
            $balance += ((float)$e['debit'] - (float)$e['credit']);
            $e['running_balance'] = round($balance, 2);
        }

        return $entries;
    }

    private function inventoryStatus(string $franchiseRef, array $filters): array
    {
        $sql = "SELECT p.product_name, p.sku, b.batch_no, b.expiry_date, b.status,
                       b.on_hand_qty, b.reserved_qty, (b.on_hand_qty - b.reserved_qty) AS available_qty
                FROM inventory_batches b
                JOIN products p ON b.franchise_ref = p.franchise_ref AND b.product_ref = p.product_ref
                WHERE b.franchise_ref = :f
                ORDER BY p.product_name ASC, b.expiry_date ASC";
        return $this->db->fetchAll($sql, [':f' => $franchiseRef]);
    }

    private function nearExpiry(string $franchiseRef, array $filters): array
    {
        $days = (int)($filters['days'] ?? 180);
        $sql = "SELECT p.product_name, p.sku, b.batch_no, b.expiry_date,
                       (b.on_hand_qty - b.reserved_qty) AS available_qty,
                       DATEDIFF(b.expiry_date, CURDATE()) AS days_left
                FROM inventory_batches b
                JOIN products p ON b.franchise_ref = p.franchise_ref AND b.product_ref = p.product_ref
                WHERE b.franchise_ref = :f 
                  AND b.status = 'SALEABLE'
                  AND (b.on_hand_qty - b.reserved_qty) > 0
                  AND b.expiry_date <= DATE_ADD(CURDATE(), INTERVAL {$days} DAY)
                ORDER BY b.expiry_date ASC";
        return $this->db->fetchAll($sql, [':f' => $franchiseRef]);
    }

    private function leadFunnel(string $franchiseRef, array $filters): array
    {
        $sql = "SELECT status, COUNT(*) as count
                FROM leads
                WHERE franchise_ref = :f
                GROUP BY status";
        return $this->db->fetchAll($sql, [':f' => $franchiseRef]);
    }

    private function schemeUtilization(string $franchiseRef, array $filters): array
    {
        $sql = "SELECT s.scheme_name, COUNT(oi.id) AS order_lines_count, SUM(oi.free_qty) AS total_free_units_given
                FROM schemes s
                JOIN order_items oi ON s.franchise_ref = oi.franchise_ref AND s.scheme_ref = oi.scheme_ref
                WHERE s.franchise_ref = :f
                GROUP BY s.scheme_ref, s.scheme_name";
        return $this->db->fetchAll($sql, [':f' => $franchiseRef]);
    }

    private function auditLogExport(string $franchiseRef, array $filters): array
    {
        $sql = "SELECT audit_ref, actor_ref, actor_role, category, action, entity_type, entity_ref, created_at
                FROM audit_logs
                WHERE franchise_ref = :f
                ORDER BY created_at DESC LIMIT 1000";
        return $this->db->fetchAll($sql, [':f' => $franchiseRef]);
    }

    private function tenantActivity(string $franchiseRef, array $filters): array
    {
        $ordersCount = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM orders WHERE franchise_ref = :f", [':f' => $franchiseRef]);
        $invCount    = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM invoices WHERE franchise_ref = :f", [':f' => $franchiseRef]);
        $payTotal    = (float)$this->db->fetchColumn("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE franchise_ref = :f", [':f' => $franchiseRef]);

        return [
            ['metric' => 'Total Orders Placed', 'value' => $ordersCount],
            ['metric' => 'Total Invoices Issued', 'value' => $invCount],
            ['metric' => 'Total Collections (INR)', 'value' => $payTotal],
        ];
    }
}
