<?php
return [
    'name'  => 'agent-lint',
    'scope' => 'quality',
    'group' => 'static-analysis',
    'steps' => [
        function() {
            ob_start();
            $exitCode = 0;
            passthru('"' . PHP_BINARY . '" "' . dirname(__DIR__, 2) . '/cli/lint.php"', $exitCode);
            $output = ob_get_clean();
            \Tests\Support\Assert::eq(0, $exitCode, "Linter reported failures:\n" . $output);
        },
    ],
    'cleanup' => function() {},
];
