<?php
declare(strict_types=1);

// Global middleware applied to every request (in order)
return [
    \App\Http\Middleware\RequestId::class,
    \App\Http\Middleware\SecurityHeaders::class,
    \App\Http\Middleware\RateLimit::class,
    \App\Http\Middleware\BearerAuth::class,
    \App\Http\Middleware\Tenant::class,
    \App\Http\Middleware\Idempotency::class,
];
