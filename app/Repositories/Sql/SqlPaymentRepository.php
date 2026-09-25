<?php
declare(strict_types=1);
namespace App\Repositories\Sql;

use App\Core\Database;
use App\Core\RefGenerator;
use App\Core\Pagination;
use App\Repositories\Contracts\PaymentRepositoryInterface;

final class SqlPaymentRepository implements PaymentRepositoryInterface
{
    public function __construct(private Database $db) {}

    public function findByRef(string $franchiseRef, string $paymentRef): ?array
    {
        return $this->db->fetchOne(
            "SELECT p.*, pt.firm_name as party_name
             FROM payments p
             JOIN parties pt ON p.franchise_ref = pt.franchise_ref AND p.party_ref = pt.party_ref
             WHERE p.franchise_ref = :f AND p.payment_ref = :r LIMIT 1",
            [':f' => $franchiseRef, ':r' => $paymentRef]
        );
    }

    public function list(string $franchiseRef, array $filters, int $page, int $perPage, ?string $partyRef = null): array
    {
        $where = ["p.franchise_ref = :f"];
        $params = [':f' => $franchiseRef];

        if ($partyRef !== null) {
            $where[] = "p.party_ref = :party";
            $params[':party'] = $partyRef;
        }

        if (!empty($filters['mode'])) {
            $where[] = "p.mode = :mode";
            $params[':mode'] = $filters['mode'];
        }

        if (!empty($filters['status'])) {
            $where[] = "p.status = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $where[] = "(p.payment_no LIKE :s1 OR p.reference_no LIKE :s2 OR pt.firm_name LIKE :s3)";
            $term = '%' . trim($filters['search']) . '%';
            $params[':s1'] = $term;
            $params[':s2'] = $term;
            $params[':s3'] = $term;
        }

        $whereClause = implode(' AND ', $where);

        $total = (int)$this->db->fetchOne(
            "SELECT COUNT(*) as cnt FROM payments p JOIN parties pt ON p.franchise_ref = pt.franchise_ref AND p.party_ref = pt.party_ref WHERE {$whereClause}",
            $params
        )['cnt'];

        $offset = ($page - 1) * $perPage;
        $sql = "SELECT p.*, pt.firm_name as party_name
                FROM payments p
                JOIN parties pt ON p.franchise_ref = pt.franchise_ref AND p.party_ref = pt.party_ref
                WHERE {$whereClause}
                ORDER BY p.id DESC LIMIT {$perPage} OFFSET {$offset}";

        $rows = $this->db->fetchAll($sql, $params);

        return ['data' => $rows, 'meta' => Pagination::meta($page, $perPage, $total)];
    }

    public function create(array $data): string
    {
        $sql = "INSERT INTO payments (
            payment_ref, payment_no, org_ref, franchise_ref, party_ref,
            payment_date, amount, allocated_amount, mode, reference_no,
            remarks, status, created_by_ref
        ) VALUES (
            :payment_ref, :payment_no, :org_ref, :franchise_ref, :party_ref,
            :payment_date, :amount, :allocated_amount, :mode, :reference_no,
            :remarks, :status, :created_by_ref
        )";

        $this->db->prepare($sql)->execute([
            ':payment_ref'       => $data['payment_ref'],
            ':payment_no'        => $data['payment_no'],
            ':org_ref'           => $data['org_ref'],
            ':franchise_ref'     => $data['franchise_ref'],
            ':party_ref'         => $data['party_ref'],
            ':payment_date'      => $data['payment_date'],
            ':amount'            => $data['amount'],
            ':allocated_amount'  => $data['allocated_amount'] ?? 0.00,
            ':mode'              => $data['mode'],
            ':reference_no'      => $data['reference_no'] ?? null,
            ':remarks'           => $data['remarks'] ?? null,
            ':status'            => $data['status'] ?? 'RECORDED',
            ':created_by_ref'    => $data['created_by_ref'],
        ]);

        return $data['payment_ref'];
    }

    public function allocate(string $franchiseRef, string $paymentRef, string $invoiceRef, float $amount): string
    {
        $payment = $this->findByRef($franchiseRef, $paymentRef);
        $allocRef = RefGenerator::generate('pal');

        $this->db->transaction(function() use ($franchiseRef, $payment, $paymentRef, $invoiceRef, $amount, $allocRef) {
            // 1. Insert allocation record
            $sqlAlloc = "INSERT INTO payment_allocations (
                allocation_ref, org_ref, franchise_ref, payment_ref, invoice_ref, allocated_amount, status, created_by_ref
            ) VALUES (
                :allocation_ref, :org_ref, :franchise_ref, :payment_ref, :invoice_ref, :allocated_amount, 'ACTIVE', :created_by_ref
            )";
            $this->db->prepare($sqlAlloc)->execute([
                ':allocation_ref'   => $allocRef,
                ':org_ref'          => $payment['org_ref'],
                ':franchise_ref'    => $franchiseRef,
                ':payment_ref'      => $paymentRef,
                ':invoice_ref'      => $invoiceRef,
                ':allocated_amount' => $amount,
                ':created_by_ref'   => $payment['created_by_ref'],
            ]);

            // 2. Update payment allocated_amount & status
            $sqlPay = "UPDATE payments SET
                allocated_amount = allocated_amount + :amt,
                status = IF(allocated_amount + :amt2 >= amount, 'ALLOCATED', 'PARTIALLY_ALLOCATED')
                WHERE franchise_ref = :f AND payment_ref = :p";
            $this->db->prepare($sqlPay)->execute([
                ':amt'  => $amount,
                ':amt2' => $amount,
                ':f'    => $franchiseRef,
                ':p'    => $paymentRef,
            ]);

            // 3. Update invoice paid_total
            $sqlInv = "UPDATE invoices SET paid_total = paid_total + :amt WHERE franchise_ref = :f AND invoice_ref = :i";
            $this->db->prepare($sqlInv)->execute([
                ':amt' => $amount,
                ':f'   => $franchiseRef,
                ':i'   => $invoiceRef,
            ]);
        });

        return $allocRef;
    }

    public function reverseAllocation(string $franchiseRef, string $allocationRef): bool
    {
        $alloc = $this->db->fetchOne(
            "SELECT * FROM payment_allocations WHERE franchise_ref = :f AND allocation_ref = :a AND status = 'ACTIVE' LIMIT 1",
            [':f' => $franchiseRef, ':a' => $allocationRef]
        );

        if (!$alloc) {
            return false;
        }

        $amt = (float)$alloc['allocated_amount'];

        return $this->db->transaction(function() use ($franchiseRef, $allocationRef, $alloc, $amt) {
            // 1. Mark allocation reversed
            $this->db->prepare("UPDATE payment_allocations SET status = 'REVERSED' WHERE franchise_ref = :f AND allocation_ref = :a")
                ->execute([':f' => $franchiseRef, ':a' => $allocationRef]);

            // 2. Decrement payment allocated_amount
            $this->db->prepare("UPDATE payments SET allocated_amount = allocated_amount - :amt, status = IF(allocated_amount - :amt2 <= 0, 'RECORDED', 'PARTIALLY_ALLOCATED') WHERE franchise_ref = :f AND payment_ref = :p")
                ->execute([':amt' => $amt, ':amt2' => $amt, ':f' => $franchiseRef, ':p' => $alloc['payment_ref']]);

            // 3. Decrement invoice paid_total
            $this->db->prepare("UPDATE invoices SET paid_total = paid_total - :amt WHERE franchise_ref = :f AND invoice_ref = :i")
                ->execute([':amt' => $amt, ':f' => $franchiseRef, ':i' => $alloc['invoice_ref']]);

            return true;
        });
    }

    public function getOpenInvoicesForParty(string $franchiseRef, string $partyRef): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM invoices
             WHERE franchise_ref = :f AND party_ref = :p AND status = 'POSTED' AND (grand_total - paid_total) > 0
             ORDER BY invoice_date ASC, id ASC",
            [':f' => $franchiseRef, ':p' => $partyRef]
        );
    }
}
