<?php
declare(strict_types=1);
namespace App\Domain\Jobs;

use App\Core\Database;
use App\Core\RefGenerator;

final class JobDispatcher
{
    public function __construct(private Database $db) {}

    /**
     * Dispatch an asynchronous job
     */
    public function dispatch(
        string $jobType,
        array $payload,
        ?string $orgRef = null,
        ?string $franchiseRef = null,
        ?string $dedupeKey = null,
        int $delaySeconds = 0
    ): ?string {
        $runAt = date('Y-m-d H:i:s', time() + $delaySeconds);
        $jobRef = RefGenerator::generate('JOB');

        if ($dedupeKey !== null) {
            $existing = $this->db->fetchColumn(
                "SELECT id FROM job_queue WHERE job_type = :t AND dedupe_key = :k LIMIT 1",
                [':t' => $jobType, ':k' => $dedupeKey]
            );
            if ($existing) {
                return null;
            }
        }

        $sql = "INSERT INTO job_queue (
            job_ref, org_ref, franchise_ref, job_type, payload_json, dedupe_key, status, run_at
        ) VALUES (
            :job_ref, :org_ref, :franchise_ref, :job_type, :payload_json, :dedupe_key, 'PENDING', :run_at
        )";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':job_ref'       => $jobRef,
            ':org_ref'       => $orgRef,
            ':franchise_ref' => $franchiseRef,
            ':job_type'      => $jobType,
            ':payload_json'  => json_encode($payload, JSON_THROW_ON_ERROR),
            ':dedupe_key'    => $dedupeKey,
            ':run_at'        => $runAt,
        ]);

        return $jobRef;
    }
}
