<?php
declare(strict_types=1);

// Cache configuration (file-based, no Redis — ADR-003)
return [
    // Root directory for cache files
    'path'  => dirname(__DIR__, 2) . '/storage/cache',

    // Default TTL in seconds
    'ttl'   => 3600,

    // Named TTLs for specific cache contexts
    'ttls' => [
        'idempotency'  => 86400,     // 24 hours — idempotency key retention
        'rate_limit'   => 300,       // 5 minutes — rate limit window
        'session'      => 1800,      // 30 minutes — session data
        'config'       => 3600,      // 1 hour — config caching
        'geo'          => 604800,    // 7 days — pincode/state lookups
    ],

    // Garbage collection probability (1/N chance per request)
    'gc_probability' => 100,
];
