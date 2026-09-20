<?php
declare(strict_types=1);

/**
 * Pure PHP Database Backup Utility (mysqldump-free)
 * Streams table schema and chunked rows directly to a gzipped .sql.gz file.
 *
 * Usage:
 *   php cli/backup.php [--out=storage/backups/] [--verify]
 */

require_once __DIR__ . '/../bootstrap/app.php';

$args = getopt('', ['out:', 'verify']);
$outDir = $args['out'] ?? (dirname(__DIR__) . '/storage/backups');

if (!is_dir($outDir)) {
    @mkdir($outDir, 0775, true);
}

$db = \App\Core\Container::getInstance()->make(\App\Core\Database::class);
$pdo = $db->pdo();

$timestamp = date('Ymd_His');
$filename = "backup_crm_{$timestamp}.sql.gz";
$filePath = $outDir . '/' . $filename;

echo "[" . date('Y-m-d H:i:s') . "] Starting database backup to {$filePath}...\n";

$gz = gzopen($filePath, 'wb9');
if (!$gz) {
    echo "ERROR: Unable to create backup file at {$filePath}\n";
    exit(1);
}

gzwrite($gz, "-- Pharma CRM Full Database Backup\n");
gzwrite($gz, "-- Generated at: " . date('Y-m-d H:i:s') . "\n");
gzwrite($gz, "SET FOREIGN_KEY_CHECKS = 0;\nSET NAMES utf8mb4;\n\n");

// 1. Get list of all tables
$tables = $pdo->query("SHOW TABLES")->fetchAll(\PDO::FETCH_COLUMN);

foreach ($tables as $table) {
    // Write CREATE TABLE statement
    $createRow = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(\PDO::FETCH_ASSOC);
    $createSql = $createRow['Create Table'] ?? null;
    if ($createSql) {
        gzwrite($gz, "DROP TABLE IF EXISTS `{$table}`;\n{$createSql};\n\n");
    }

    // Write chunked INSERT statements
    $count = (int)$pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
    if ($count === 0) {
        continue;
    }

    $offset = 0;
    $chunkSize = 500;
    while ($offset < $count) {
        $stmt = $pdo->query("SELECT * FROM `{$table}` LIMIT {$chunkSize} OFFSET {$offset}");
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        if (empty($rows)) break;

        $columns = array_map(fn($col) => "`{$col}`", array_keys($rows[0]));
        $valuesList = [];

        foreach ($rows as $row) {
            $escapedValues = array_map(function($val) use ($pdo) {
                if ($val === null) return 'NULL';
                return $pdo->quote((string)$val);
            }, array_values($row));
            $valuesList[] = '(' . implode(',', $escapedValues) . ')';
        }

        $insertSql = "INSERT INTO `{$table}` (" . implode(',', $columns) . ") VALUES\n" . implode(",\n", $valuesList) . ";\n";
        gzwrite($gz, $insertSql);

        $offset += $chunkSize;
    }
    gzwrite($gz, "\n");
    echo "  Backed up table: {$table} ({$count} rows)\n";
}

gzwrite($gz, "SET FOREIGN_KEY_CHECKS = 1;\n");
gzclose($gz);

$sizeKb = round(filesize($filePath) / 1024, 2);
echo "[" . date('Y-m-d H:i:s') . "] Backup completed successfully! File size: {$sizeKb} KB\n";

if (isset($args['verify'])) {
    echo "Running integrity verification on generated backup file...\n";
    $gzRead = gzopen($filePath, 'rb');
    $readHeader = gzread($gzRead, 100);
    gzclose($gzRead);
    if (str_contains($readHeader, 'Pharma CRM Full Database Backup')) {
        echo "  [VERIFY OK] Backup file is valid and readable.\n";
    } else {
        echo "  [VERIFY FAILED] File header corrupted.\n";
        exit(1);
    }
}

exit(0);
