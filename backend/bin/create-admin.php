<?php
declare(strict_types=1);

/**
 * CampusSOS — Local Administrator Provisioning CLI Script
 *
 * Usage:
 *   php backend/bin/create-admin.php [options]
 *
 * Options:
 *   --name="Admin Name"
 *   --email="admin@example.edu"
 *   --password="YourSecurePassword"
 *
 * Security notice:
 * This script runs exclusively via CLI (Command Line Interface).
 * Web server execution is strictly blocked.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Forbidden: CLI execution only.\n";
    exit(1);
}

require_once __DIR__ . '/../bootstrap.php';

use CampusSOS\Config\Database;
use CampusSOS\Models\User;
use CampusSOS\Models\AuditLog;

echo "======================================================\n";
echo " CampusSOS — Local Administrator Account Provisioning \n";
echo "======================================================\n\n";

$pdo = Database::getConnection();
if (!$pdo) {
    echo "ERROR: Unable to connect to MySQL database.\n";
    echo "Please ensure MySQL is running in XAMPP and database 'campussos' is created.\n";
    exit(1);
}

// Parse CLI options
$options = getopt('', ['name::', 'email::', 'password::']);

$name = $options['name'] ?? null;
$email = $options['email'] ?? null;
$password = $options['password'] ?? null;

// Interactive prompts if not passed as CLI flags
if (empty($name)) {
    echo "Enter Administrator Full Name (e.g., Campus Chief Administrator): ";
    $name = trim((string) fgets(STDIN));
}

if (empty($email)) {
    echo "Enter Administrator Campus Email: ";
    $email = trim((string) fgets(STDIN));
}

$normalizedEmail = strtolower(trim((string) $email));
if (!filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL)) {
    echo "ERROR: Invalid email address format: '{$normalizedEmail}'\n";
    exit(1);
}

if (empty($password)) {
    echo "Enter Password (leave blank to auto-generate a secure 16-character password): ";
    $inputPass = trim((string) fgets(STDIN));
    if ($inputPass !== '') {
        $password = $inputPass;
    } else {
        $password = bin2hex(random_bytes(8)) . '!A1'; // 16-char random password
        $generated = true;
    }
}

if (strlen($password) < 8) {
    echo "ERROR: Administrator password must be at least 8 characters.\n";
    exit(1);
}

// Check if user already exists
$existing = User::findByEmail($normalizedEmail);
$passwordHash = password_hash($password, PASSWORD_DEFAULT, ['cost' => 12]);

if ($existing) {
    echo "\nNOTICE: User with email '{$normalizedEmail}' already exists.\n";
    echo "Promoting existing account to 'admin' role and updating password...\n";

    $stmt = $pdo->prepare("
        UPDATE users
        SET role = 'admin', password_hash = :hash, account_status = 'active', updated_at = NOW()
        WHERE id = :id
    ");
    $stmt->execute([
        ':hash' => $passwordHash,
        ':id' => $existing['id'],
    ]);

    AuditLog::log((int) $existing['id'], 'ADMIN_PROMOTED_CLI', 'user', (int) $existing['id']);
    $adminId = $existing['id'];
} else {
    $stmt = $pdo->prepare("
        INSERT INTO users (full_name, email, password_hash, role, account_status, created_at, updated_at)
        VALUES (:name, :email, :hash, 'admin', 'active', NOW(), NOW())
    ");
    $stmt->execute([
        ':name' => $name,
        ':email' => $normalizedEmail,
        ':hash' => $passwordHash,
    ]);
    $adminId = (int) $pdo->lastInsertId();

    AuditLog::log($adminId, 'ADMIN_PROVISIONED_CLI', 'user', $adminId);
}

echo "\nSUCCESS! Administrator account ready.\n";
echo "------------------------------------------------------\n";
echo " User ID : {$adminId}\n";
echo " Name    : {$name}\n";
echo " Email   : {$normalizedEmail}\n";
echo " Role    : admin\n";
if (!empty($generated)) {
    echo " Password: {$password}\n";
    echo " (Please store this temporary generated password securely!)\n";
} else {
    echo " Password: [Protected / Custom entered]\n";
}
echo "------------------------------------------------------\n";
exit(0);

