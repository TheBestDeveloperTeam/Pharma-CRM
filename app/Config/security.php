<?php
declare(strict_types=1);

// Security configuration
return [
    // Content-Security-Policy directives
    'csp' => [
        'default-src' => ["'self'"],
        'script-src'  => ["'self'"],
        'style-src'   => ["'self'", "'unsafe-inline'"],   // inline for dynamic theme vars
        'img-src'     => ["'self'", 'data:'],
        'font-src'    => ["'self'"],
        'connect-src' => ["'self'"],
        'frame-src'   => ["'none'"],
        'object-src'  => ["'none'"],
        'base-uri'    => ["'self'"],
        'form-action' => ["'self'"],
    ],

    // HSTS — Strict Transport Security
    'hsts' => [
        'enabled'            => true,
        'max_age'            => 31536000,   // 1 year
        'include_subdomains' => true,
        'preload'            => false,
    ],

    // Rate limiting defaults
    'rate_limits' => [
        'login'   => ['max' => 5,   'window' => 300],    // 5 attempts / 5 min
        'api'     => ['max' => 120, 'window' => 60],     // 120 requests / min
        'webhook' => ['max' => 60,  'window' => 60],     // 60 requests / min
        'export'  => ['max' => 5,   'window' => 300],    // 5 exports / 5 min
    ],

    // Session / token security
    'lockout_threshold'  => 5,       // consecutive failed logins before lockout
    'lockout_duration'   => 900,     // 15 minutes lockout

    // IP allowlist for Super Admin (empty = allow all)
    'super_admin_ips' => [],

    // Request size limits
    'max_body_size'   => 1048576,    // 1 MB (R05)
    'max_upload_size' => 5242880,    // 5 MB
];
