<?php
return [
    'name'  => 'agent-auth',
    'scope' => 'security',
    'group' => 'authentication',
    'steps' => [
        // 1. Valid password grant -> 200 with tokens
        function() {
            $pdo = \App\Core\Database::connection();
            $pdo->exec("DELETE FROM rate_limits WHERE bucket_key LIKE 'login-%' OR bucket_key LIKE 'test-%'");

            $ctrl = \App\Core\Container::getInstance()->make(\App\Http\Controllers\Api\V1\AuthController::class);
            $_SERVER['REQUEST_METHOD'] = 'POST';
            $_SERVER['REQUEST_URI'] = '/api/v1/oauth/token';
            $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
            $_SERVER['HTTP_USER_AGENT'] = 'AgentAuthBot';

            $req = \App\Core\Request::capture();
            $ref = new \ReflectionProperty($req, 'body');
            $ref->setAccessible(true);
            $ref->setValue($req, [
                'grant_type' => 'password',
                'client_id'  => 'crm-super',
                'email'      => 'super@pharmacrm.local',
                'password'   => 'Password@123',
            ]);

            $res = $ctrl->token($req);
            \Tests\Support\Assert::status($res, 200, 'Valid password grant');
            $data = json_decode($res->body(), true)['data'];
            \Tests\Support\Assert::true(!empty($data['access_token']), 'Has access token');
            \Tests\Support\Assert::true(!empty($data['refresh_token']), 'Has refresh token');
        },
        // 2. Wrong password -> 401 INVALID_CREDENTIALS
        function() {
            $ctrl = \App\Core\Container::getInstance()->make(\App\Http\Controllers\Api\V1\AuthController::class);
            $_SERVER['REMOTE_ADDR'] = '127.0.0.2';
            $req = \App\Core\Request::capture();
            $ref = new \ReflectionProperty($req, 'body');
            $ref->setAccessible(true);
            $ref->setValue($req, [
                'grant_type' => 'password',
                'client_id'  => 'crm-super',
                'email'      => 'super@pharmacrm.local',
                'password'   => 'WrongPassword',
            ]);

            \Tests\Support\Assert::throws(
                fn() => $ctrl->token($req),
                \App\Core\Exceptions\UnauthorizedException::class,
                'Wrong password must fail'
            );
        },
        // 3. Wrong surface role -> 401 INVALID_CREDENTIALS
        function() {
            $limiter = \App\Core\Container::getInstance()->make(\App\Core\Security\RateLimiter::class);
            $limiter->clear('login-ip', '127.0.0.3');
            $limiter->clear('login-user', 'admin@pharmacrm.local:PLATFORM');

            $ctrl = \App\Core\Container::getInstance()->make(\App\Http\Controllers\Api\V1\AuthController::class);
            $_SERVER['REMOTE_ADDR'] = '127.0.0.3';
            $req = \App\Core\Request::capture();
            $ref = new \ReflectionProperty($req, 'body');
            $ref->setAccessible(true);
            $ref->setValue($req, [
                'grant_type' => 'password',
                'client_id'  => 'crm-super',
                'email'      => 'admin@pharmacrm.local',
                'password'   => 'Password@123',
            ]);

            \Tests\Support\Assert::throws(
                fn() => $ctrl->token($req),
                \App\Core\Exceptions\UnauthorizedException::class,
                'Role mismatch must fail'
            );
        },
        // 4. Rate limit check (TooManyRequestsException on repeated failed attempts)
        function() {
            $limiter = \App\Core\Container::getInstance()->make(\App\Core\Security\RateLimiter::class);
            $uniqueKey = 'user_' . bin2hex(random_bytes(6));
            // Simulate 5 hits
            for ($i = 0; $i < 5; $i++) {
                $limiter->hit('test-limiter', $uniqueKey, 5, 15);
            }
            \Tests\Support\Assert::throws(
                fn() => $limiter->hit('test-limiter', $uniqueKey, 5, 15),
                \App\Core\Exceptions\TooManyRequestsException::class,
                '6th attempt must throw 429 TooManyRequestsException'
            );
        },
        // 5. Refresh token -> new pair issued
        function() {
            $tokens = \App\Core\Container::getInstance()->make(\App\Core\Security\TokenService::class);
            $user = [
                'user_ref'      => 'USR-SUPERADMIN0000001',
                'org_ref'       => 'ORG-PLATFORM0000000001',
                'franchise_ref' => null,
                'role'          => 'SUPER_ADMIN',
                'party_ref'     => null,
            ];
            $issued = $tokens->issue($user, 'crm-super', 'super', '127.0.0.1', 'Bot');
            $refreshed = $tokens->refresh($issued['refresh_token'], 'super', '127.0.0.1', 'Bot');

            \Tests\Support\Assert::true(!empty($refreshed['access_token']), 'Refreshed access token');
            \Tests\Support\Assert::true(!empty($refreshed['refresh_token']), 'Refreshed new refresh token');
            \Tests\Support\Assert::true($issued['refresh_token'] !== $refreshed['refresh_token'], 'Rotated refresh token');
        },
        // 6. USED refresh token -> 401 REFRESH_TOKEN_REUSED + family revoked
        function() {
            $tokens = \App\Core\Container::getInstance()->make(\App\Core\Security\TokenService::class);
            $user = [
                'user_ref'      => 'USR-SUPERADMIN0000001',
                'org_ref'       => 'ORG-PLATFORM0000000001',
                'franchise_ref' => null,
                'role'          => 'SUPER_ADMIN',
                'party_ref'     => null,
            ];
            $issued = $tokens->issue($user, 'crm-super', 'super', '127.0.0.1', 'Bot');
            $refreshed = $tokens->refresh($issued['refresh_token'], 'super', '127.0.0.1', 'Bot');

            // Now reuse the old issued refresh token
            \Tests\Support\Assert::throws(
                fn() => $tokens->refresh($issued['refresh_token'], 'super', '127.0.0.1', 'Bot'),
                \App\Core\Exceptions\UnauthorizedException::class,
                'Reusing used refresh token must be rejected'
            );
        },
        // 7. Expired access token -> 401 TOKEN_EXPIRED
        function() {
            $jwt = \App\Core\Container::getInstance()->make(\App\Core\Security\Jwt::class);
            $now = time() - 3600; // 1 hour ago
            $claims = [
                'sub' => 'USR-SUPERADMIN0000001',
                'aud' => 'super',
                'iss' => 'pharma-crm',
                'typ' => 'access',
                'exp' => $now,
                'nbf' => $now - 60,
                'iat' => $now - 60,
            ];
            $token = $jwt->sign($claims);
            \Tests\Support\Assert::throws(
                fn() => $jwt->verify($token, 'super'),
                \App\Core\Exceptions\UnauthorizedException::class,
                'Expired access token must throw UnauthorizedException'
            );
        },
        // 8. alg=none -> 401 TOKEN_ALG
        function() {
            $jwt = \App\Core\Container::getInstance()->make(\App\Core\Security\Jwt::class);
            $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'none', 'typ' => 'JWT'])), '+/', '-_'), '=');
            $payload = rtrim(strtr(base64_encode(json_encode(['sub' => '123', 'typ' => 'access', 'iss' => 'pharma-crm', 'aud' => 'super', 'exp' => time() + 300])), '+/', '-_'), '=');
            $token = $header . '.' . $payload . '.';

            \Tests\Support\Assert::throws(
                fn() => $jwt->verify($token, 'super'),
                \App\Core\Exceptions\UnauthorizedException::class,
                'alg=none must throw UnauthorizedException'
            );
        },
        // 9. Tampered signature -> 401 TOKEN_SIGNATURE
        function() {
            $jwt = \App\Core\Container::getInstance()->make(\App\Core\Security\Jwt::class);
            $token = $jwt->sign([
                'sub' => 'USR-SUPERADMIN0000001',
                'aud' => 'super',
                'iss' => 'pharma-crm',
                'typ' => 'access',
                'exp' => time() + 300,
            ]);
            $tampered = $token . 'tampered';
            \Tests\Support\Assert::throws(
                fn() => $jwt->verify($tampered, 'super'),
                \App\Core\Exceptions\UnauthorizedException::class,
                'Tampered token must throw UnauthorizedException'
            );
        },
        // 10. Wrong aud -> 401 TOKEN_AUD
        function() {
            $jwt = \App\Core\Container::getInstance()->make(\App\Core\Security\Jwt::class);
            $token = $jwt->sign([
                'sub' => 'USR-SUPERADMIN0000001',
                'aud' => 'super',
                'iss' => 'pharma-crm',
                'typ' => 'access',
                'exp' => time() + 300,
            ]);
            \Tests\Support\Assert::throws(
                fn() => $jwt->verify($token, 'admin'),
                \App\Core\Exceptions\UnauthorizedException::class,
                'Wrong audience must throw UnauthorizedException'
            );
        },
        // 11. Revoked session -> 401
        function() {
            $tokens = \App\Core\Container::getInstance()->make(\App\Core\Security\TokenService::class);
            $user = [
                'user_ref'      => 'USR-SUPERADMIN0000001',
                'org_ref'       => 'ORG-PLATFORM0000000001',
                'franchise_ref' => null,
                'role'          => 'SUPER_ADMIN',
                'party_ref'     => null,
            ];
            $issued = $tokens->issue($user, 'crm-super', 'super', '127.0.0.1', 'Bot');
            $parts = explode('.', $issued['access_token']);
            $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);

            $tokens->revokeSession($payload['sid']);

            // Checking session in user_sessions table
            $pdo = \App\Core\Database::connection();
            $stmt = $pdo->prepare("SELECT revoked_at FROM user_sessions WHERE session_ref = ?");
            $stmt->execute([$payload['sid']]);
            $revokedAt = $stmt->fetchColumn();
            \Tests\Support\Assert::true(!empty($revokedAt), 'Session marked as revoked in database');
        },
    ],
    'cleanup' => function() {
        $pdo = \App\Core\Database::connection();
        $pdo->exec("DELETE FROM rate_limits WHERE bucket_key LIKE 'login-%' OR bucket_key LIKE 'test-%'");
    },
];
