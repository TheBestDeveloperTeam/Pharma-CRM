<?php
declare(strict_types=1);
namespace App\Core\Security;

use App\Core\Exceptions\TooManyRequestsException;

final class RateLimiter
{
    public function __construct(private \PDO $pdo) {}

    /**
     * Increment hit counter and throw TooManyRequestsException if limit is exceeded.
     *
     * @param string $scope  login-ip | login-user | api-user | webhook-source
     * @param string $key    ip address | email/tenant | user_ref | source_slug
     * @param int    $limit  maximum hits in total window
     * @param int    $windowMinutes window length in minutes
     */
    public function hit(string $scope, string $key, int $limit, int $windowMinutes): void
    {
        $bucket = hash('sha256', $scope . ':' . $key);
        $windowStart = (int)floor(time() / 60); // 1-minute window

        $stmt = $this->pdo->prepare(
            "INSERT INTO rate_limits (bucket_key, window_start, hits)
             VALUES (:b, :w, 1)
             ON DUPLICATE KEY UPDATE hits = hits + 1"
        );
        $stmt->execute([':b' => $bucket, ':w' => $windowStart]);

        // Aggregate hits over the past windowMinutes
        $earliest = $windowStart - ($windowMinutes - 1);
        $stmtCheck = $this->pdo->prepare(
            "SELECT SUM(hits) AS total FROM rate_limits WHERE bucket_key = :b AND window_start >= :earliest"
        );
        $stmtCheck->execute([':b' => $bucket, ':earliest' => $earliest]);
        $total = (int)$stmtCheck->fetchColumn();

        if ($total > $limit) {
            $retryAfter = ($windowStart + 1) * 60 - time();
            throw new TooManyRequestsException($retryAfter > 0 ? $retryAfter : 60);
        }
    }

    /**
     * Clear hits (e.g., upon successful login).
     */
    public function clear(string $scope, string $key): void
    {
        $bucket = hash('sha256', $scope . ':' . $key);
        $stmt = $this->pdo->prepare("DELETE FROM rate_limits WHERE bucket_key = :b");
        $stmt->execute([':b' => $bucket]);
    }
}
