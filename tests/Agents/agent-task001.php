<?php

return [
    'name'  => 'agent-task001',
    'scope' => 'authorization',
    'group' => 'security',
    'steps' => [
        function() {
            $ctx = new \App\Core\TenantContext(
                orgRef: 'ORG-TEST',
                franchiseRef: 'FRN-TEST',
                userRef: 'USR-TEST',
                role: 'SALES',
                scope: 'FRANCHISE',
                partyRef: null,
                requestId: 'REQ-TASK001',
                roles: ['sales-team'],
                permissions: ['leads' => ['view', 'create']],
                scopes: ['leads' => 'OWN'],
                teamUserRefs: [],
                territoryRefs: []
            );

            \Tests\Support\Assert::true($ctx->can('leads', 'view'), 'Granted action is available');
            \Tests\Support\Assert::false($ctx->can('leads', 'delete'), 'Ungrantable action is denied');
            \Tests\Support\Assert::eq('OWN', $ctx->scopeFor('leads'), 'Module scope is resolved');
            \Tests\Support\Assert::eq('NONE', $ctx->scopeFor('payments'), 'Unconfigured module defaults to NONE');
        },
        function() {
            $migration = file_get_contents(__DIR__ . '/../../database/migrations/002_task001_authorization.sql');
            foreach (['auth_roles', 'auth_permissions', 'auth_role_permissions', 'auth_role_scopes', 'auth_user_roles', 'auth_user_hierarchy', 'auth_user_territories'] as $table) {
                \Tests\Support\Assert::true(str_contains($migration, "CREATE TABLE IF NOT EXISTS {$table}"), "Migration contains {$table}");
            }
            \Tests\Support\Assert::true(str_contains($migration, "'ALL','TERRITORY','TEAM','OWN','NONE'"), 'Migration contains all approved scope values');
        },
    ],
];
