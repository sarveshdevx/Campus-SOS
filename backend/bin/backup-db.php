<?php
declare(strict_types=1);

/**
 * CampusSOS — Database Backup CLI Tool
 *
 * Generates an idempotent, clean SQL dump of all schema and data tables
 * into the database/backups/ directory before applying updates.
 *
 * Usage:
 *   php backend/bin/backup-db.php [--output=path/to/backup.sql]
 */

require_once __DIR__ . '/../bootstrap.php';

use CampusSOS\Config\Database;

$pdo = Database::getConnection();
if (!$pdo) {
    fwrite(STDERR, "ERROR: Unable to connect to MySQL database 'campussos'. Please verify XAMPP MySQL is running.\n");
    exit(1);
}

$options = getopt('', ['output::']);
$backupDir = __DIR__ . '/../../database/backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

$defaultFilename = 'campussos_backup_' . date('Y-m-d_His') . '.sql';
$outputPath = $options['output'] ?? ($backupDir . '/' . $defaultFilename);

echo "=================================================================\n";
echo " CampusSOS — Database Backup Utility\n";
echo " Target File: {$outputPath}\n";
echo "=================================================================\n\n";

$tables = [];
$stmt = $pdo->query("SHOW TABLES");
while ($row = $stmt->fetch(\PDO::FETCH_NUM)) {
    $tables[] = $row[0];
}

if (empty($tables)) {
    echo "No tables found in database 'campussos'. Nothing to backup.\n";
    exit(0);
}

$dump = "-- =============================================================================\n";
$dump .= "-- CampusSOS Database Backup Dump\n";
$dump .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
$dump .= "-- =============================================================================\n\n";
$dump .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

foreach ($tables as $table) {
    echo "-> Backing up table: `{$table}`...\n";
    
    // Structure
    $stmtCreate = $pdo->query("SHOW CREATE TABLE `{$table}`");
    $createRow = $stmtCreate->fetch(\PDO::FETCH_NUM);
    $createTableSql = $createRow[1] ?? '';

    $dump .= "-- Table structure for `{$table}`\n";
    $dump .= "DROP TABLE IF EXISTS `{$table}`;\n";
    $dump .= $createTableSql . ";\n\n";

    // Data
    $stmtData = $pdo->query("SELECT * FROM `{$table}`");
    $rows = $stmtData->fetchAll(\PDO::FETCH_ASSOC);

    if (!empty($rows)) {
        $dump .= "-- Dumping data for `{$table}`\n";
        $columns = array_keys($rows[0]);
        $escapedColumns = array_map(fn($col) => "`{$col}`", $columns);
        $colList = implode(', ', $escapedColumns);

        $dump .= "INSERT INTO `{$table}` ({$colList}) VALUES\n";
        $rowInserts = [];
        foreach ($rows as $row) {
            $values = [];
            foreach ($row as $val) {
                if ($val === null) {
                    $values[] = "NULL";
                } elseif (is_numeric($val) && !is_string($val)) {
                    $values[] = $val;
                } else {
                    $values[] = $pdo->quote((string) $val);
                }
            }
            $rowInserts[] = "(" . implode(', ', $values) . ")";
        }
        $dump .= implode(",\n", $rowInserts) . ";\n\n";
    }
}

$dump .= "SET FOREIGN_KEY_CHECKS = 1;\n";

file_put_contents($outputPath, $dump);

echo "\n[OK] Backup completed successfully. File size: " . round(filesize($outputPath) / 1024, 2) . " KB\n";
echo "Saved to: {$outputPath}\n";

