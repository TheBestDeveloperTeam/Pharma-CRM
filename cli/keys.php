<?php
declare(strict_types=1);

// Usage:
//   php cli/keys.php generate  — generates fresh k1 key
//   php cli/keys.php rotate    — promotes k1->k0, generates new k1

require __DIR__ . '/../bootstrap/app.php';

$action = $argv[1] ?? 'generate';

function generateKey(): string {
    return bin2hex(random_bytes(32)); // 64 hex chars = 256 bits
}

if ($action === 'generate') {
    $key = generateKey();
    echo "JWT_KEY_K1={$key}\n";
    echo "Add this to your .env file.\n";
} elseif ($action === 'rotate') {
    $currentK1 = env('JWT_KEY_K1', '');
    $newK1     = generateKey();
    echo "JWT_KEY_K0={$currentK1}  (old k1, accepted for 15 min until tokens expire)\n";
    echo "JWT_KEY_K1={$newK1}       (new signing key)\n";
    echo "Update .env and restart PHP-FPM.\n";
}
