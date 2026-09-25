<?php
declare(strict_types=1);
namespace App\Repositories\Sql;

use App\Core\Database;
use App\Repositories\Contracts\StockReservationRepositoryInterface;

final class SqlStockReservationRepository implements StockReservationRepositoryInterface
{
    public function __construct(private Database $db) {}

    public function create(array $data): string
    {
        $sql = "INSERT INTO stock_reservations (
            reservation_ref, org_ref, franchise_ref, batch_ref, order_ref,
            order_item_ref, reserved_qty, status, expires_at
        ) VALUES (
            :reservation_ref, :org_ref, :franchise_ref, :batch_ref, :order_ref,
            :order_item_ref, :reserved_qty, 'ACTIVE', :expires_at
        )";

        $this->db->prepare($sql)->execute([
            ':reservation_ref' => $data['reservation_ref'],
            ':org_ref'         => $data['org_ref'],
            ':franchise_ref'   => $data['franchise_ref'],
            ':batch_ref'       => $data['batch_ref'],
            ':order_ref'       => $data['order_ref'],
            ':order_item_ref'  => $data['order_item_ref'],
            ':reserved_qty'    => $data['reserved_qty'],
            ':expires_at'      => $data['expires_at'] ?? null,
        ]);

        return $data['reservation_ref'];
    }

    public function release(string $franchiseRef, string $reservationRef): bool
    {
        $sql = "UPDATE stock_reservations SET status = 'RELEASED', updated_at = NOW()
                WHERE franchise_ref = :f AND reservation_ref = :r AND status = 'ACTIVE'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':f' => $franchiseRef, ':r' => $reservationRef]);
        return $stmt->rowCount() > 0;
    }

    public function consume(string $franchiseRef, string $reservationRef): bool
    {
        $sql = "UPDATE stock_reservations SET status = 'CONSUMED', updated_at = NOW()
                WHERE franchise_ref = :f AND reservation_ref = :r AND status = 'ACTIVE'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':f' => $franchiseRef, ':r' => $reservationRef]);
        return $stmt->rowCount() > 0;
    }

    public function getActiveForOrder(string $franchiseRef, string $orderRef): array
    {
        return $this->db->fetchAll(
            "SELECT sr.*, ib.batch_no, ib.expiry_date, ib.product_ref
             FROM stock_reservations sr
             JOIN inventory_batches ib ON sr.franchise_ref = ib.franchise_ref AND sr.batch_ref = ib.batch_ref
             WHERE sr.franchise_ref = :f AND sr.order_ref = :o AND sr.status = 'ACTIVE'",
            [':f' => $franchiseRef, ':o' => $orderRef]
        );
    }

    public function listForOrder(string $franchiseRef, string $orderRef): array
    {
        return $this->db->fetchAll('SELECT sr.*, ib.batch_no, ib.expiry_date, ib.product_ref FROM stock_reservations sr JOIN inventory_batches ib ON sr.franchise_ref = ib.franchise_ref AND sr.batch_ref = ib.batch_ref WHERE sr.franchise_ref = ? AND sr.order_ref = ? ORDER BY sr.created_at ASC, sr.id ASC', [$franchiseRef, $orderRef]);
    }
}
