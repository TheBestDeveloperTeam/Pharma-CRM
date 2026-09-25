<?php
declare(strict_types=1);
namespace App\Repositories\Sql;

use App\Core\Database;
use App\Repositories\Contracts\InventoryBatchRepositoryInterface;

final class SqlInventoryBatchRepository implements InventoryBatchRepositoryInterface
{
    public function __construct(private Database $db) {}

    public function findByRef(string $franchiseRef, string $batchRef): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM inventory_batches WHERE franchise_ref = :f AND batch_ref = :r LIMIT 1",
            [':f' => $franchiseRef, ':r' => $batchRef]
        );
    }

    public function findByNo(string $franchiseRef, string $productRef, string $batchNo): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM inventory_batches WHERE franchise_ref = :f AND product_ref = :p AND batch_no = :n LIMIT 1",
            [':f' => $franchiseRef, ':p' => $productRef, ':n' => $batchNo]
        );
    }

    public function getSaleableBatches(string $franchiseRef, string $productRef, int $minShelfDays = 0): array
    {
        $minShelfDays = max(0, min(3650, $minShelfDays));
        return $this->db->fetchAll(
            "SELECT * FROM inventory_batches
             WHERE franchise_ref = :f
               AND product_ref = :p
               AND status = 'SALEABLE'
               AND expiry_date >= DATE_ADD(CURDATE(), INTERVAL {$minShelfDays} DAY)
               AND (on_hand_qty - reserved_qty) > 0
             ORDER BY expiry_date ASC, manufacturing_date ASC, id ASC
             FOR UPDATE",
            [':f' => $franchiseRef, ':p' => $productRef]
        );
    }

    public function create(array $data): string
    {
        $sql = "INSERT INTO inventory_batches (
            batch_ref, org_ref, franchise_ref, product_ref, batch_no,
            manufacturing_date, expiry_date, received_qty, on_hand_qty, reserved_qty,
            damaged_qty, location_code, status, version, created_by_ref
        ) VALUES (
            :batch_ref, :org_ref, :franchise_ref, :product_ref, :batch_no,
            :manufacturing_date, :expiry_date, :received_qty, :on_hand_qty, 0,
            0, :location_code, :status, 1, :created_by_ref
        )";

        $this->db->prepare($sql)->execute([
            ':batch_ref'           => $data['batch_ref'],
            ':org_ref'             => $data['org_ref'],
            ':franchise_ref'       => $data['franchise_ref'],
            ':product_ref'         => $data['product_ref'],
            ':batch_no'            => $data['batch_no'],
            ':manufacturing_date'  => $data['manufacturing_date'] ?? null,
            ':expiry_date'         => $data['expiry_date'],
            ':received_qty'        => $data['received_qty'],
            ':on_hand_qty'         => $data['received_qty'],
            ':location_code'       => $data['location_code'] ?? null,
            ':status'              => $data['status'] ?? 'SALEABLE',
            ':created_by_ref'      => $data['created_by_ref'],
        ]);

        return $data['batch_ref'];
    }

    public function updateQty(string $franchiseRef, string $batchRef, int $onHandDelta, int $reservedDelta, int $expectedVersion): bool
    {
        $sql = "UPDATE inventory_batches SET
            on_hand_qty = on_hand_qty + :oh,
            reserved_qty = reserved_qty + :res,
            version = version + 1,
            updated_at = NOW()
        WHERE franchise_ref = :f AND batch_ref = :r AND version = :v
          AND (on_hand_qty + :oh2) >= 0
          AND (reserved_qty + :res2) >= 0
          AND (on_hand_qty + :oh3) >= (reserved_qty + :res3)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':oh'   => $onHandDelta,
            ':res'  => $reservedDelta,
            ':f'    => $franchiseRef,
            ':r'    => $batchRef,
            ':v'    => $expectedVersion,
            ':oh2'  => $onHandDelta,
            ':res2' => $reservedDelta,
            ':oh3'  => $onHandDelta,
            ':res3' => $reservedDelta,
        ]);

        return $stmt->rowCount() === 1;
    }

    public function setStatus(string $franchiseRef, string $batchRef, string $status): bool
    {
        $sql = "UPDATE inventory_batches SET status = :s, updated_at = NOW() WHERE franchise_ref = :f AND batch_ref = :r";
        return $this->db->prepare($sql)->execute([':s' => $status, ':f' => $franchiseRef, ':r' => $batchRef]);
    }

    public function listNearExpiry(string $franchiseRef, int $days): array
    {
        return $this->db->fetchAll(
            "SELECT ib.*, p.product_name, p.sku
             FROM inventory_batches ib
             JOIN products p ON ib.franchise_ref = p.franchise_ref AND ib.product_ref = p.product_ref
             WHERE ib.franchise_ref = :f
               AND ib.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :d DAY)
               AND ib.on_hand_qty > 0
             ORDER BY ib.expiry_date ASC",
            [':f' => $franchiseRef, ':d' => $days]
        );
    }

    public function list(string $franchiseRef, array $filters, int $page, int $perPage): array
    {
        $where = ['ib.franchise_ref = ?']; $params = [$franchiseRef];
        if (!empty($filters['product_ref'])) { $where[] = 'ib.product_ref = ?'; $params[] = $filters['product_ref']; }
        if (!empty($filters['status'])) { $where[] = 'ib.status = ?'; $params[] = $filters['status']; }
        if (!empty($filters['search'])) { $where[] = '(ib.batch_no LIKE ? OR p.product_name LIKE ? OR p.sku LIKE ?)'; $term = '%' . $filters['search'] . '%'; array_push($params, $term, $term, $term); }
        $clause = implode(' AND ', $where); $total = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM inventory_batches ib JOIN products p ON p.franchise_ref = ib.franchise_ref AND p.product_ref = ib.product_ref WHERE {$clause}", $params); $offset = ($page - 1) * $perPage;
        $rows = $this->db->fetchAll("SELECT ib.*, p.product_name, p.sku, (ib.on_hand_qty - ib.reserved_qty) AS available_qty FROM inventory_batches ib JOIN products p ON p.franchise_ref = ib.franchise_ref AND p.product_ref = ib.product_ref WHERE {$clause} ORDER BY ib.expiry_date ASC, ib.id ASC LIMIT {$perPage} OFFSET {$offset}", $params);
        return ['items' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'total_pages' => (int)ceil($total / $perPage)];
    }
}
