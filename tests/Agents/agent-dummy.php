<?php
return [
    'name'  => 'agent-dummy',
    'scope' => 'foundation',
    'group' => 'core',
    'steps' => [
        function() {
            \Tests\Support\Assert::true(true, 'Dummy always passes');
        },
        function() {
            \Tests\Support\Assert::eq(2, 1 + 1, 'Math works');
        },
    ],
    'cleanup' => function() {},
];
