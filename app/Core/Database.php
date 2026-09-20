<?php
declare(strict_types=1);
namespace App\Core;

final class Database
{
    private static ?\PDO $pdo = null;

    public static function connection(): \PDO
    {
        if (self::$pdo === null) {
            $host   = \App\Support\Config::get('database.host', 'localhost');
            $port   = \App\Support\Config::get('database.port', '3306');
            $name   = \App\Support\Config::get('database.name');
            $user   = \App\Support\Config::get('database.user');
            $pass   = \App\Support\Config::get('database.password', '');

            $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

            self::$pdo = new \PDO($dsn, $user, $pass, [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_EMULATE_PREPARES   => false,      // MUST be false — real prepared statements
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_STRINGIFY_FETCHES  => false,
                \PDO::MYSQL_ATTR_FOUND_ROWS   => true,       // UPDATE returns matched rows, not changed
            ]);

            // Enforce strict mode per session (belt + suspenders on top of server config)
            self::$pdo->exec("SET sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
            self::$pdo->exec("SET time_zone = '+00:00'"); // Always UTC at DB level
        }

        return self::$pdo;
    }

    private ?\PDO $instancePdo = null;

    public function __construct(?\PDO $pdo = null)
    {
        $this->instancePdo = $pdo ?: self::connection();
    }

    public function pdo(): \PDO
    {
        return $this->instancePdo ?: self::connection();
    }

    public function prepare(string $query, array $options = []): \PDOStatement
    {
        return $this->pdo()->prepare($query, $options);
    }

    public function beginTransaction(): bool
    {
        return $this->pdo()->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->pdo()->commit();
    }

    public function rollBack(): bool
    {
        return $this->pdo()->rollBack();
    }

    public function transaction(callable $fn): mixed
    {
        $tx = new Transaction($this->pdo());
        return $tx->run($fn);
    }

    public function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function fetchColumn(string $sql, array $params = [], int $columnNumber = 0): mixed
    {
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn($columnNumber);
    }

    public function insert(string $table, array $data): int|string
    {
        $cols = array_keys($data);
        $placeholders = array_fill(0, count($cols), '?');
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $cols),
            implode(', ', $placeholders)
        );

        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute(array_values($data));
        return $this->pdo()->lastInsertId();
    }

    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $set = [];
        $values = [];
        foreach ($data as $col => $val) {
            $set[] = "{$col} = ?";
            $values[] = $val;
        }

        $sql = sprintf('UPDATE %s SET %s WHERE %s', $table, implode(', ', $set), $where);
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute(array_merge($values, $whereParams));
        return $stmt->rowCount();
    }

    /** Reset connection (for testing). */
    public static function reset(): void
    {
        self::$pdo = null;
    }
}
