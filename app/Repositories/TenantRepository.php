<?php
declare(strict_types=1);
namespace App\Repositories;

use App\Core\{TenantContext, TenantScope};

abstract class TenantRepository
{
    protected TenantScope $scope;

    public function __construct(
        protected \PDO           $pdo,
        protected TenantContext  $ctx,
    ) {
        $this->scope = new TenantScope($ctx);
    }

    /**
     * Execute a SELECT query and return all rows.
     * Always include tenant WHERE clause from $this->scope->where().
     */
    protected function select(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    protected function selectOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    protected function execute(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    protected function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    /**
     * Build a safe sort clause. $sortable = allowed columns.
     */
    protected function safeOrderBy(string $column, string $direction, array $sortable): string
    {
        $dir = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $col = in_array($column, $sortable, true) ? $column : $sortable[0];
        return "ORDER BY $col $dir";
    }

    /**
     * Safe pagination. Cap per_page at 100.
     */
    protected function safePaginate(int $page, int $perPage): array
    {
        $page    = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $offset  = ($page - 1) * $perPage;
        return [$page, $perPage, $offset];
    }
}
