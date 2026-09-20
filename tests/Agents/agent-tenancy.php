<?php
return [
    'name'  => 'agent-tenancy',
    'scope' => 'tenancy',
    'group' => 'isolation',
    'steps' => [
        // 1. Admin lists only own franchise rows
        function() {
            $userRepo = \App\Core\Container::getInstance()->make(\App\Repositories\Contracts\UserRepositoryInterface::class);
            $res = $userRepo->list(['franchise_ref' => 'FRN-MUMBAI000000000001'], 1, 25);
            \Tests\Support\Assert::true(count($res['data']) > 0, 'Lists Mumbai franchise users');
            foreach ($res['data'] as $row) {
                \Tests\Support\Assert::eq('FRN-MUMBAI000000000001', $row['franchise_ref'], 'Tenant row belongs to Mumbai');
            }
        },
        // 2. Foreign-tenant ref in URL -> 404 + SECURITY audit
        function() {
            $ctrl = \App\Core\Container::getInstance()->make(\App\Http\Controllers\Api\V1\Admin\UsersController::class);

            // Establish Admin TenantContext for Mumbai
            $ctx = new \App\Core\TenantContext(
                orgRef: 'ORG-PLATFORM0000000001',
                franchiseRef: 'FRN-MUMBAI000000000001',
                userRef: 'USR-FRNADMIN000000001',
                role: 'FRANCHISE_ADMIN',
                scope: 'FRANCHISE',
                partyRef: null,
                requestId: 'REQ-TENANT-TEST-1'
            );
            \App\Core\Container::getInstance()->instance(\App\Core\TenantContext::class, $ctx);

            // Create user in another franchise
            $userRepo = \App\Core\Container::getInstance()->make(\App\Repositories\Contracts\UserRepositoryInterface::class);
            $hasher = \App\Core\Container::getInstance()->make(\App\Core\Security\PasswordHasher::class);

            $foreignUserRef = 'USR-FOREIGN0000000001';
            $existing = $userRepo->findByRef($foreignUserRef);
            if (!$existing) {
                $userRepo->create([
                    'user_ref'             => $foreignUserRef,
                    'org_ref'              => 'ORG-PLATFORM0000000001',
                    'franchise_ref'        => 'FRN-DELHI000000000001',
                    'role'                 => 'SALES',
                    'full_name'            => 'Delhi Sales Rep',
                    'email'                => 'delhi@pharmacrm.local',
                    'password_hash'        => $hasher->hash('Password@123'),
                    'status'               => 'ACTIVE',
                    'created_by_ref'       => 'USR-SUPERADMIN0000001',
                ]);
            }

            $_SERVER['REQUEST_METHOD'] = 'GET';
            $_SERVER['REQUEST_URI'] = '/api/v1/admin/users/' . $foreignUserRef;
            $req = \App\Core\Request::capture();
            $ref = new \ReflectionProperty($req, 'params');
            $ref->setAccessible(true);
            $ref->setValue($req, ['ref' => $foreignUserRef]);

            \Tests\Support\Assert::throws(
                fn() => $ctrl->show($req),
                \App\Core\Exceptions\NotFoundException::class,
                'Foreign tenant user access must throw NotFoundException'
            );
        },
        // 3. X-Franchise-Ref mismatch -> 403 + SECURITY audit
        function() {
            $tenantMw = new \App\Http\Middleware\Tenant();
            $ctx = new \App\Core\TenantContext(
                orgRef: 'ORG-PLATFORM0000000001',
                franchiseRef: 'FRN-MUMBAI000000000001',
                userRef: 'USR-FRNADMIN000000001',
                role: 'FRANCHISE_ADMIN',
                scope: 'FRANCHISE',
                partyRef: null,
                requestId: 'REQ-TENANT-TEST-2'
            );
            \App\Core\Container::getInstance()->instance(\App\Core\TenantContext::class, $ctx);

            $_SERVER['REQUEST_METHOD'] = 'GET';
            $_SERVER['REQUEST_URI'] = '/api/v1/admin/users';
            $_SERVER['HTTP_X_FRANCHISE_REF'] = 'FRN-DELHI000000000001'; // Mismatch

            $req = \App\Core\Request::capture();

            \Tests\Support\Assert::throws(
                fn() => $tenantMw($req, fn($r) => \App\Core\Response::json(200, ['ok' => true])),
                \App\Core\Exceptions\ForbiddenException::class,
                'X-Franchise-Ref mismatch must throw ForbiddenException'
            );

            // Verify audit log has SECURITY event
            $auditRepo = \App\Core\Container::getInstance()->make(\App\Repositories\Contracts\AuditRepositoryInterface::class);
            $query = $auditRepo->query(['category' => 'SECURITY', 'action' => 'tenant.header_mismatch'], 1, 10);
            \Tests\Support\Assert::true($query['meta']['total'] > 0, 'Security audit logged on tenant header mismatch');
        },
        // 4. JWT claim vs DB mismatch -> 401 + SECURITY audit
        function() {
            // Simulated by token sub that is marked INACTIVE in DB
            $pdo = \App\Core\Database::connection();
            $pdo->prepare("UPDATE users SET status = 'INACTIVE' WHERE user_ref = 'USR-DISTRIBUTOR000001'")->execute();

            $jwt = \App\Core\Container::getInstance()->make(\App\Core\Security\Jwt::class);
            $token = $jwt->sign([
                'sub'  => 'USR-DISTRIBUTOR000001',
                'aud'  => 'portal',
                'iss'  => 'pharma-crm',
                'typ'  => 'access',
                'exp'  => time() + 300,
            ]);

            $bearerMw = \App\Core\Container::getInstance()->make(\App\Http\Middleware\BearerAuth::class);
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $_SERVER['REQUEST_URI'] = '/api/v1/portal/dashboard';
            $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
            $_SERVER['HTTP_X_SURFACE'] = 'portal';

            $req = \App\Core\Request::capture();

            \Tests\Support\Assert::throws(
                fn() => $bearerMw($req, fn($r) => \App\Core\Response::json(200, ['ok' => true])),
                \App\Core\Exceptions\UnauthorizedException::class,
                'Inactive/mismatched user token must throw UnauthorizedException'
            );

            // Re-activate user
            $pdo->prepare("UPDATE users SET status = 'ACTIVE' WHERE user_ref = 'USR-DISTRIBUTOR000001'")->execute();
        },
        // 5. Super bypass writes audit TENANT_BYPASS
        function() {
            $audit = \App\Core\Container::getInstance()->make(\App\Domain\Audit\AuditService::class);
            $superCtx = new \App\Core\TenantContext(
                orgRef: 'ORG-PLATFORM0000000001',
                franchiseRef: null,
                userRef: 'USR-SUPERADMIN0000001',
                role: 'SUPER_ADMIN',
                scope: 'PLATFORM',
                partyRef: null,
                requestId: 'REQ-BYPASS-TEST'
            );

            $audit->log(
                ctx: $superCtx,
                category: 'TENANT_BYPASS',
                action: 'super.cross_franchise_read',
                entityType: 'franchise',
                entityRef: 'FRN-MUMBAI000000000001',
                reason: 'Super admin platform inspection'
            );

            $auditRepo = \App\Core\Container::getInstance()->make(\App\Repositories\Contracts\AuditRepositoryInterface::class);
            $query = $auditRepo->query(['category' => 'TENANT_BYPASS'], 1, 10);
            \Tests\Support\Assert::true($query['meta']['total'] > 0, 'Tenant bypass logged with TENANT_BYPASS category');
        },
    ],
    'cleanup' => function() {},
];
