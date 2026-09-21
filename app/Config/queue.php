<?php
declare(strict_types=1);

// Job queue configuration (DB-backed, SKIP LOCKED — no Redis)
return [
    // Database table for job queue
    'table'          => 'job_queue',

    // Number of jobs to process per worker batch
    'batch_size'     => 10,

    // Maximum retry attempts before marking as failed
    'max_retries'    => 3,

    // Delay between retries in seconds (exponential backoff base)
    'retry_delay'    => 60,

    // Job TTL — max age in seconds before a job is considered stale
    'job_ttl'        => 86400,

    // Worker sleep interval (seconds) when queue is empty
    'sleep_interval' => 5,

    // Maximum execution time per job in seconds
    'job_timeout'    => 300,

    // Named queue priorities
    'queues' => [
        'high'    => 1,
        'default' => 5,
        'low'     => 10,
    ],
];
