<?php
declare(strict_types=1);

/**
 * Production Installation & Verification Utility
 * Usage:
 *   php cli/install.php
 */

echo "================================================\n";
echo "  Pharma CRM Production Installation & Health  \n";
echo "================================================\n\n";

// 1. Check PHP Version
$phpVersion = PHP_VERSION;
echo "1. Checking PHP Version ($phpVersion)... ";
if (version_compare($phpVersion, '8.1.0', '>=')) {
    echo "✅ OK\n";
} else {
    echo "❌ FAILED (Requires PHP 8.1+)\n";
    exit(1);
}

// 2. Check Extensions
echo "2. Checking Required Extensions:\n";
$extensions = ['pdo', 'pdo_mysql', 'json', 'zlib', 'openssl'];
foreach ($extensions as $ext) {
    echo "   - $ext: ";
    if (extension_loaded($ext)) {
        echo "✅ OK\n";
    } else {
        echo "❌ MISSING\n";
        exit(1);
    }
}

// 3. Storage Directory Permissions
echo "3. Checking Storage Directories:\n";
$dirs = [
    dirname(__DIR__) . '/storage/logs',
    dirname(__DIR__) . '/storage/cache',
    dirname(__DIR__) . '/storage/backups',
    dirname(__DIR__) . '/storage/exports',
];
foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    echo "   - " . basename($dir) . ": ";
    if (is_writable($dir)) {
        echo "✅ Writable\n";
    } else {
        echo "❌ NOT Writable\n";
    }
}

// 4. Test Database Connection
echo "4. Testing Database Connection... ";
try {
    require_once __DIR__ . '/../bootstrap/app.php';
    $db = \App\Core\Container::getInstance()->make(\App\Core\Database::class);
    $pdo = $db->pdo();
    $dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();
    echo "✅ Connected to `{$dbName}`\n";
} catch (\Throwable $e) {
    echo "❌ FAILED: " . $e->getMessage() . "\n";
    exit(1);
}

// 5. Check Core Tables
echo "5. Verifying Database Schema Tables... ";
$tables = $pdo->query("SHOW TABLES")->fetchAll(\PDO::FETCH_COLUMN);
$expected = ['organizations', 'franchises', 'users', 'products', 'orders', 'invoices', 'audit_logs', 'job_queue'];
$missing = array_diff($expected, $tables);
if (empty($missing)) {
    echo "✅ Found " . count($tables) . " tables\n";
} else {
    echo "❌ Missing tables: " . implode(', ', $missing) . "\n";
    exit(1);
}

echo "\nAll installation and environment checks passed!\n";
exit(0);
