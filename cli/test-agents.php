<?php
declare(strict_types=1);

// Usage:
//   php cli/test-agents.php --all
//   php cli/test-agents.php --agent=agent-dummy
//   php cli/test-agents.php --list
//   php cli/test-agents.php --group=core

$args = getopt('', ['all', 'agent:', 'list', 'group:', 'fail-fast', 'report:']);

$agentDir = __DIR__ . '/../tests/Agents';
$agentFiles = glob($agentDir . '/*.php') ?: [];

if (isset($args['list'])) {
    foreach ($agentFiles as $file) {
        $agent = require $file;
        echo sprintf("  %-40s %s\n", $agent['name'], $agent['scope'] ?? '');
    }
    exit(0);
}

require_once __DIR__ . '/../tests/Support/Harness.php';
\Tests\Support\Harness::boot();

$results  = [];
$failures = 0;
$failFast = isset($args['fail-fast']);

// Filter agents
$toRun = [];
foreach ($agentFiles as $file) {
    $agent = require $file;

    if (isset($args['agent']) && $agent['name'] !== $args['agent']) continue;
    if (isset($args['group']) && ($agent['group'] ?? '') !== $args['group']) continue;

    $toRun[] = [$file, $agent];
}

foreach ($toRun as [$file, $agent]) {
    echo "\n> Running: {$agent['name']}\n";
    $agentPassed = true;
    $stepCount   = 0;

    foreach ($agent['steps'] as $step) {
        $stepCount++;
        try {
            $step();
            echo "  [OK] Step $stepCount\n";
        } catch (\Throwable $e) {
            echo "  [FAIL] Step $stepCount: " . $e->getMessage() . "\n";
            $agentPassed = false;
            $failures++;
            if ($failFast) {
                echo "\nFAIL-FAST: stopping.\n";
                exit(1);
            }
        }
    }

    // Cleanup
    if (isset($agent['cleanup'])) {
        ($agent['cleanup'])();
    }

    $results[] = ['name' => $agent['name'], 'passed' => $agentPassed, 'steps' => $stepCount];
    echo $agentPassed ? "  PASSED\n" : "  FAILED\n";
}

echo "\n=== Results ===\n";
foreach ($results as $r) {
    $status = $r['passed'] ? '[PASS]' : '[FAIL]';
    echo "$status {$r['name']} ({$r['steps']} steps)\n";
}

echo "\n" . count($results) . " agents, $failures failures.\n";
exit($failures > 0 ? 1 : 0);
