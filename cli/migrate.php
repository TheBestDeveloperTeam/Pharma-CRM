<?php
declare(strict_types=1);

// Usage: php cli/migrate.php --fresh [--i-understand]
// --fresh: drops all tables and recreates from schema file
// --i-understand: required in production to prevent accidental reset

require __DIR__ . '/../bootstrap/app.php';

$fresh = in_array('--fresh', $argv, true);
$understand = in_array('--i-understand', $argv, true);

if (!$fresh) {
    echo "Usage: php cli/migrate.php --fresh [--i-understand]\n";
    exit(1);
}

$env = \App\Support\Config::get('app.env');
if ($env === 'production' && !$understand) {
    echo "ERROR: Running --fresh on production requires --i-understand flag.\n";
    exit(1);
}

$pdo = \App\Core\Database::connection();
$sql = file_get_contents(__DIR__ . '/../database/schema/001_full_schema.sql');

if (!$sql) {
    echo "ERROR: Could not read schema file.\n";
    exit(1);
}

// Execute multi-statement SQL
$pdo->exec($sql);

echo "Migration complete. Full schema applied successfully.\n";
