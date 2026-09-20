<?php
return [
    'name'  => 'agent-health',
    'scope' => 'core',
    'group' => 'infrastructure',
    'steps' => [
        function() {
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $_SERVER['REQUEST_URI'] = '/health';
            $req = \App\Core\Request::capture();
            $controller = new \App\Http\Controllers\Api\V1\HealthController();
            $res = $controller->health($req);
            \Tests\Support\Assert::status($res, 200, 'Health endpoint status 200');
            \Tests\Support\Assert::jsonPath($res, 'data.status', 'ok');
        },
        function() {
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $_SERVER['REQUEST_URI'] = '/ready';
            $req = \App\Core\Request::capture();
            $controller = new \App\Http\Controllers\Api\V1\HealthController();
            $res = $controller->ready($req);
            \Tests\Support\Assert::status($res, 200, 'Ready endpoint status 200');
            \Tests\Support\Assert::jsonPath($res, 'data.ready', true);
            \Tests\Support\Assert::jsonPath($res, 'data.checks.database.ok', true);
            \Tests\Support\Assert::jsonPath($res, 'data.checks.storage.ok', true);
        },
    ],
    'cleanup' => function() {},
];
