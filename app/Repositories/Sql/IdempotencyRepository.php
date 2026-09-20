<?php
declare(strict_types=1);
namespace App\Repositories\Sql;

use App\Core\Database;
use App\Repositories\Contracts\IdempotencyRepositoryInterface;

final class IdempotencyRepository implements IdempotencyRepositoryInterface
{
    public function __construct(private Database $db) {}

    public function find(string $franchiseRef, string $key): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM api_idempotency_keys WHERE franchise_ref = :f AND idempotency_key = :k LIMIT 1",
            [':f' => $franchiseRef, ':k' => $key]
        );
    }

    public function lock(string $orgRef, string $franchiseRef, string $key, string $method, string $path, string $requestHash): bool
    {
        $expiresAt = date('Y-m-d H:i:s', time() + 86400); // 24 hours expiry
        $sql = "INSERT INTO api_idempotency_keys (
            org_ref, franchise_ref, idempotency_key, method, path, request_hash, status, expires_at
        ) VALUES (
            :org_ref, :franchise_ref, :key, :method, :path, :request_hash, 'IN_PROGRESS', :expires_at
        )";

        try {
            $this->db->prepare($sql)->execute([
                ':org_ref'        => $orgRef,
                ':franchise_ref'  => $franchiseRef,
                ':key'            => $key,
                ':method'         => $method,
                ':path'           => $path,
                ':request_hash'   => $requestHash,
                ':expires_at'     => $expiresAt,
            ]);
            return true;
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'Duplicate')) {
                return false;
            }
            throw $e;
        }
    }

    public function complete(string $franchiseRef, string $key, int $status, array $responseJson): bool
    {
        $sql = "UPDATE api_idempotency_keys SET
            status = 'COMPLETED',
            response_status = :status,
            response_json = :json,
            completed_at = NOW()
        WHERE franchise_ref = :f AND idempotency_key = :k";

        return $this->db->prepare($sql)->execute([
            ':status' => $status,
            ':json'   => json_encode($responseJson, JSON_THROW_ON_ERROR),
            ':f'      => $franchiseRef,
            ':k'      => $key,
        ]);
    }

    public function fail(string $franchiseRef, string $key): bool
    {
        $sql = "UPDATE api_idempotency_keys SET status = 'FAILED' WHERE franchise_ref = :f AND idempotency_key = :k";
        return $this->db->prepare($sql)->execute([
            ':f' => $franchiseRef,
            ':k' => $key,
        ]);
    }
}
