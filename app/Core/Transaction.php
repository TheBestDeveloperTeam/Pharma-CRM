<?php
declare(strict_types=1);
namespace App\Core;

final class Transaction
{
    private int $depth = 0;

    public function __construct(private \PDO $pdo) {}

    public function begin(): void
    {
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $this->depth = 1;
        } else {
            $this->depth++;
            $this->pdo->exec("SAVEPOINT sp_{$this->depth}");
        }
    }

    public function commit(): void
    {
        if ($this->depth <= 1) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
            $this->depth = 0;
        } else {
            $this->pdo->exec("RELEASE SAVEPOINT sp_{$this->depth}");
            $this->depth--;
        }
    }

    public function rollback(): void
    {
        if ($this->depth <= 1) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->depth = 0;
        } else {
            $this->pdo->exec("ROLLBACK TO SAVEPOINT sp_{$this->depth}");
            $this->depth--;
        }
    }

    /**
     * Run a callable inside a transaction. Auto-commits or rolls back.
     */
    public function run(callable $fn): mixed
    {
        $this->begin();
        try {
            $result = $fn();
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    public function depth(): int { return $this->depth; }
}
