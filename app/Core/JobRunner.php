<?php
declare(strict_types=1);
namespace App\Core;

use App\Core\Database;
use App\Domain\Webhooks\ProcessWebhookEventJob;

final class JobRunner
{
    private const BACKOFF_SCHEDULE = [30, 120, 600, 1800]; // 30s, 2m, 10m, 30m

    public function __construct(
        private Database $db,
        private Container $container,
    ) {}

    /**
     * Run up to $limit pending jobs
     */
    public function runNext(int $limit = 10): int
    {
        // 1. Recover stale running jobs (locked > 10m)
        $this->db->prepare(
            "UPDATE job_queue SET status = 'PENDING', run_at = NOW()
             WHERE status = 'RUNNING' AND locked_at < DATE_SUB(NOW(), INTERVAL 600 SECOND)"
        )->execute();

        // 2. Lock next available jobs using SKIP LOCKED
        $this->db->beginTransaction();
        try {
            try {
                $jobs = $this->db->fetchAll(
                    "SELECT * FROM job_queue
                     WHERE status IN ('PENDING', 'RETRY_WAIT') AND run_at <= NOW()
                     ORDER BY run_at ASC
                     LIMIT {$limit}
                     FOR UPDATE SKIP LOCKED"
                );
            } catch (\Throwable $e) {
                // MariaDB 10.4 (SKIP LOCKED supported in MariaDB 10.6+ and MySQL 8.0+)
                $jobs = $this->db->fetchAll(
                    "SELECT * FROM job_queue
                     WHERE status IN ('PENDING', 'RETRY_WAIT') AND run_at <= NOW()
                     ORDER BY run_at ASC
                     LIMIT {$limit}
                     FOR UPDATE"
                );
            }

            if (empty($jobs)) {
                $this->db->commit();
                return 0;
            }

            $jobIds = array_column($jobs, 'id');
            $inClause = implode(',', $jobIds);

            $this->db->prepare(
                "UPDATE job_queue SET status = 'RUNNING', locked_at = NOW() WHERE id IN ({$inClause})"
            )->execute();

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        // 3. Process jobs individually outside the batch lock
        $processed = 0;
        foreach ($jobs as $job) {
            $this->executeJob($job);
            $processed++;
        }

        return $processed;
    }

    private function executeJob(array $job): void
    {
        $id = $job['id'];
        $type = $job['job_type'];
        $payload = json_decode($job['payload_json'], true) ?? [];

        try {
            if ($type === 'ProcessWebhookEvent') {
                $handler = $this->container->make(ProcessWebhookEventJob::class);
                $handler->handle($payload);
            }

            // On success
            $this->db->prepare(
                "UPDATE job_queue SET status = 'SUCCESS', updated_at = NOW() WHERE id = :id"
            )->execute([':id' => $id]);
        } catch (\Throwable $e) {
            $attempts = (int)$job['attempts'] + 1;
            $maxAttempts = (int)$job['max_attempts'];

            if ($attempts >= $maxAttempts) {
                $this->db->prepare(
                    "UPDATE job_queue SET status = 'DEAD', attempts = :att, last_error = :err, updated_at = NOW() WHERE id = :id"
                )->execute([
                    ':id'  => $id,
                    ':att' => $attempts,
                    ':err' => substr($e->getMessage(), 0, 250),
                ]);
            } else {
                $delayIndex = min($attempts - 1, count(self::BACKOFF_SCHEDULE) - 1);
                $delaySecs = self::BACKOFF_SCHEDULE[$delayIndex];

                $this->db->prepare(
                    "UPDATE job_queue SET status = 'RETRY_WAIT', attempts = :att, run_at = DATE_ADD(NOW(), INTERVAL :delay SECOND), last_error = :err, updated_at = NOW() WHERE id = :id"
                )->execute([
                    ':id'    => $id,
                    ':att'   => $attempts,
                    ':delay' => $delaySecs,
                    ':err'   => substr($e->getMessage(), 0, 250),
                ]);
            }
        }
    }
}
