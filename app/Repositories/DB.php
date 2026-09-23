<?php
namespace App\Repositories;

use PDO;
use PDOException;
use Exception;

class DB
{
    private static ?PDO $instance = null;

    private function __construct() {}
    private function __clone() {}

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
            $db   = $_ENV['DB_NAME'] ?? 'pharma_crm';
            $user = $_ENV['DB_USER'] ?? 'root';
            $pass = $_ENV['DB_PASS'] ?? '';
            $charset = 'utf8mb4';

            $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$instance = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                // In production, log this instead of throwing directly
                throw new Exception("Database connection failed: " . $e->getMessage());
            }
        }

        return self::$instance;
    }

    /**
     * Helper for executing prepared statements easily
     */
    public static function query(string $sql, array $params = [])
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Audit logger for mutations
     */
    public static function logAudit(string $action, string $entityType, ?int $entityId, ?array $oldValues, ?array $newValues): void
    {
        $tenantId = $_ENV['current_tenant_id'] ?? 0;
        $userId = $_ENV['current_user_id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $oldJson = $oldValues ? json_encode($oldValues) : null;
        $newJson = $newValues ? json_encode($newValues) : null;

        $sql = "INSERT INTO audit_logs (tenant_id, user_id, action, entity_type, entity_id, old_values, new_values, ip_address) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        // Use standard prepare to avoid recursive logging if query() was overloaded
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute([$tenantId, $userId, $action, $entityType, $entityId, $oldJson, $newJson, $ip]);
    }
}
