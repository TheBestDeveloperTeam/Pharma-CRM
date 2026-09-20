<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api\V1;

use App\Core\{Request, Response};

final class HealthController
{
    public function health(Request $r): Response
    {
        return Response::json(200, ['status' => 'ok', 'time' => date('c')]);
    }

    public function ready(Request $r): Response
    {
        $checks = [];
        $ready  = true;

        // DB check
        try {
            $pdo     = \App\Core\Database::connection();
            $version = (string)$pdo->query("SELECT VERSION() AS v")->fetchColumn();
            $isMaria = str_contains(strtolower($version), 'mariadb');
            preg_match('/^(\d+\.\d+\.\d+)/', $version, $m);
            $parts   = array_map('intval', explode('.', $m[1] ?? '0.0.0'));
            
            // MariaDB 10.4+ or MySQL 8.0.16+
            if ($isMaria) {
                $ok = ($parts[0] > 10) || ($parts[0] === 10 && $parts[1] >= 4);
            } else {
                $ok = ($parts[0] > 8) || ($parts[0] === 8 && $parts[1] === 0 && $parts[2] >= 16);
            }

            $checks['database'] = ['ok' => $ok, 'version' => $version];
            if (!$ok) $ready = false;
        } catch (\Throwable $e) {
            $checks['database'] = ['ok' => false, 'error' => 'Connection failed'];
            $ready = false;
        }

        // Storage writable check
        $storagePath = (string)\App\Support\Config::get('app.storage');
        $storageOk   = is_dir($storagePath) && is_writable($storagePath . '/logs');
        $checks['storage'] = ['ok' => $storageOk];
        if (!$storageOk) $ready = false;

        // JWT key check
        $jwtKey = (string)(\App\Support\Config::get('auth.keys.k1') ?? env('JWT_KEY_K1', ''));
        $keyOk  = $jwtKey !== '' && strlen($jwtKey) >= 16;
        $checks['jwt_key'] = ['ok' => $keyOk];
        if (!$keyOk) $ready = false;

        $status = $ready ? 200 : 503;
        return Response::json($status, ['ready' => $ready, 'checks' => $checks]);
    }
}
