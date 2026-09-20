<?php
declare(strict_types=1);
namespace App\Repositories\Sql;

use App\Core\Database;
use App\Repositories\Contracts\InventoryMovementRepositoryInterface;

final class SqlInventoryMovementRepository implements InventoryMovementRepositoryInterface
{
    public function __construct(private Database $db) {}

    public function record(array $data): string
    {
        $sql = "INSERT INTO inventory_movements (
            movement_ref, org_ref, franchise_ref, batch_ref, movement_type,
            qty, reference_type, reference_ref, remarks, created_by_ref
        ) VALUES (
            :movement_ref, :org_ref, :franchise_ref, :batch_ref, :movement_type,
            :qty, :reference_type, :reference_ref, :remarks, :created_by_ref
        )";

        $this->db->prepare($sql)->execute([
            ':movement_ref'    => $data['movement_ref'],
            ':org_ref'         => $data['org_ref'],
            ':franchise_ref'   => $data['franchise_ref'],
            ':batch_ref'       => $data['batch_ref'],
            ':movement_type'   => $data['movement_type'],
            ':qty'             => $data['qty'],
            ':reference_type'  => $data['reference_type'] ?? null,
            ':reference_ref'   => $data['reference_ref'] ?? null,
            ':remarks'         => $data['remarks'] ?? null,
            ':created_by_ref'  => $data['created_by_ref'],
        ]);

        return $data['movement_ref'];
    }

    public function listByBatch(string $franchiseRef, string $batchRef): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM inventory_movements WHERE franchise_ref = :f AND batch_ref = :b ORDER BY id DESC",
            [':f' => $franchiseRef, ':b' => $batchRef]
        );
    }
}
