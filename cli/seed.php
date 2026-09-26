<?php
declare(strict_types=1);

// Usage: php cli/seed.php [seed_file.sql]

require __DIR__ . '/../bootstrap/app.php';

$pdo = \App\Core\Database::connection();
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);

$seedFiles = [
    '001_admin_seed.sql',
    '002_masters_seed.sql',
    '004_inventory_seed.sql',
    '005_additional_seed.sql',
];

$specificSeed = $argv[1] ?? null;
if ($specificSeed !== null) {
    $seedFiles = [$specificSeed];
}

$seedsDir = __DIR__ . '/../database/seeds/';

foreach ($seedFiles as $file) {
    $path = $seedsDir . basename($file);
    if (!file_exists($path)) {
        echo "[SKIP] Seed file not found: {$file}\n";
        continue;
    }

    echo "Running seed: {$file}... ";
    $sql = file_get_contents($path);
    if (!$sql) {
        echo "FAILED (empty file)\n";
        exit(1);
    }

    try {
        $pdo->exec($sql);
        echo "OK\n";
    } catch (\PDOException $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        exit(1);
    }
}

echo "All database seeds executed successfully.\n";
