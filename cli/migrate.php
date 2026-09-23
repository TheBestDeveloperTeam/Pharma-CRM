<?php
/**
 * Simple Migration Runner
 * Usage: php cli/migrate.php
 */

require_once __DIR__ . '/../public/index.php'; // Bootstraps autoloader and env

use App\Repositories\DB;

echo "Starting migrations...\n";

$migrationsDir = __DIR__ . '/../database/migrations';
if (!is_dir($migrationsDir)) {
    echo "Migrations directory not found.\n";
    exit(1);
}

// Create migrations table if not exists
DB::query("
    CREATE TABLE IF NOT EXISTS migrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        migration VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$ranMigrations = DB::query("SELECT migration FROM migrations")->fetchAll(PDO::FETCH_COLUMN);

$files = scandir($migrationsDir);
$files = array_filter($files, function($f) { return pathinfo($f, PATHINFO_EXTENSION) === 'sql'; });
sort($files);

$executed = 0;
foreach ($files as $file) {
    if (!in_array($file, $ranMigrations)) {
        echo "Running $file...\n";
        $sql = file_get_contents($migrationsDir . '/' . $file);
        
        try {
            DB::query($sql);
            DB::query("INSERT INTO migrations (migration) VALUES (?)", [$file]);
            echo "Successfully ran $file.\n";
            $executed++;
        } catch (Exception $e) {
            echo "Error running $file: " . $e->getMessage() . "\n";
            exit(1);
        }
    }
}

if ($executed === 0) {
    echo "Nothing to migrate.\n";
} else {
    echo "Completed $executed migrations.\n";
}
