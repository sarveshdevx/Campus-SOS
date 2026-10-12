<?php
declare(strict_types=1);

/**
 * CampusSOS — Migration Runner for department_contacts Table
 */

require_once __DIR__ . '/../bootstrap.php';

use CampusSOS\Config\Database;

$pdo = Database::getConnection();
if (!$pdo) {
    fwrite(STDERR, "ERROR: Unable to connect to database 'campussos'.\n");
    exit(1);
}

echo "-> Running migration for department_contacts...\n";

$sqlFile = __DIR__ . '/../../database/migrations/2026_10_add_department_contacts.sql';
if (!file_exists($sqlFile)) {
    fwrite(STDERR, "ERROR: Migration file not found: {$sqlFile}\n");
    exit(1);
}

$sql = file_get_contents($sqlFile);
$statements = array_filter(array_map('trim', explode(';', $sql)));

foreach ($statements as $stmt) {
    if (empty($stmt)) continue;
    try {
        $pdo->exec($stmt);
    } catch (\PDOException $e) {
        fwrite(STDERR, "Migration statement error: " . $e->getMessage() . "\n");
        exit(1);
    }
}

$count = $pdo->query("SELECT COUNT(*) FROM department_contacts")->fetchColumn();
echo "-> department_contacts table created & populated successfully ({$count} departments configured).\n";
exit(0);

