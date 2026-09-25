<?php
declare(strict_types=1);
namespace App\Repositories\Sql;

use App\Core\{Database, Pagination};
use App\Repositories\Contracts\CatalogMasterRepositoryInterface;

final class SqlCatalogMasterRepository implements CatalogMasterRepositoryInterface
{
    public function __construct(private Database $db) {}

    public function list(string $franchiseRef, string $masterKey, array $filters, int $page, int $perPage): array
    {
        $where = ['franchise_ref = ?', 'master_key = ?'];
        $params = [$franchiseRef, $masterKey];
        if (!empty($filters['status'])) { $where[] = 'status = ?'; $params[] = $filters['status']; }
        if (!empty($filters['search'])) { $where[] = 'name LIKE ?'; $params[] = '%' . $filters['search'] . '%'; }
        $clause = implode(' AND ', $where);
        $total = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM catalog_master_values WHERE {$clause}", $params);
        $offset = ($page - 1) * $perPage;
        $rows = $this->db->fetchAll(
            "SELECT * FROM catalog_master_values WHERE {$clause} ORDER BY name ASC LIMIT ? OFFSET ?",
            array_merge($params, [$perPage, $offset])
        );
        return ['data' => $rows, 'meta' => Pagination::meta($page, $perPage, $total)];
    }

    public function findByRef(string $franchiseRef, string $masterRef): ?array
    {
        return $this->db->fetchOne('SELECT * FROM catalog_master_values WHERE franchise_ref = ? AND master_ref = ? LIMIT 1', [$franchiseRef, $masterRef]);
    }

    public function findByName(string $franchiseRef, string $masterKey, string $name): ?array
    {
        return $this->db->fetchOne('SELECT * FROM catalog_master_values WHERE franchise_ref = ? AND master_key = ? AND name = ? LIMIT 1', [$franchiseRef, $masterKey, $name]);
    }

    public function create(array $data): string
    {
        $this->db->insert('catalog_master_values', $data);
        return (string)$data['master_ref'];
    }

    public function update(string $franchiseRef, string $masterRef, array $data): bool
    {
        return $this->db->update('catalog_master_values', $data, 'franchise_ref = ? AND master_ref = ?', [$franchiseRef, $masterRef]) > 0;
    }
}
