<?php
declare(strict_types=1);

namespace CampusSOS\Config;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $connection = null;

    /**
     * Get or establish the singleton PDO connection.
     * Uses environment variables with sensible XAMPP macOS defaults.
     */
    public static function getConnection(): ?PDO
    {
        if (self::$connection !== null) {
            return self::$connection;
        }

        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $port = getenv('DB_PORT') ?: '3306';
        $database = getenv('DB_NAME') ?: 'campussos';
        $username = getenv('DB_USER') ?: 'root';
        $password = getenv('DB_PASS') !== false ? (string) getenv('DB_PASS') : '';
        $socket = getenv('DB_SOCKET') ?: null;
        $charset = 'utf8mb4';

        if ($socket && file_exists($socket)) {
            $dsn = "mysql:unix_socket={$socket};dbname={$database};charset={$charset}";
        } else {
            $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";
        }

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 3, // 3-second connect timeout
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE utf8mb4_unicode_ci",
        ];

        try {
            self::$connection = new PDO($dsn, $username, $password, $options);
            return self::$connection;
        } catch (PDOException $e) {
            // Log sanitized error message internally (do not print credentials or DSN)
            error_log(sprintf(
                "[CampusSOS Database] Connection failed to database '%s' on host '%s': %s (Code: %d)",
                $database,
                $host,
                $e->getMessage(),
                $e->getCode()
            ));
            return null;
        }
    }

    /**
     * Check database connectivity and retrieve minimal non-sensitive diagnostics.
     * Safe for external health-check endpoint consumption.
     */
    public static function checkHealth(): array
    {
        $start = microtime(true);
        $pdo = self::getConnection();

        if ($pdo === null) {
            return [
                'connected' => false,
                'status' => 'disconnected',
                'message' => 'Unable to establish database connection. Verify MySQL service in XAMPP.',
            ];
        }

        try {
            $stmt = $pdo->query("SELECT 1 AS alive, DATABASE() AS current_db, VERSION() AS server_version");
            $row = $stmt->fetch();
            $latencyMs = round((microtime(true) - $start) * 1000, 2);

            return [
                'connected' => true,
                'status' => 'connected',
                'database' => $row['current_db'] ?? (getenv('DB_NAME') ?: 'campussos'),
                'driver' => 'mysql',
                'server_version' => $row['server_version'] ?? 'unknown',
                'latency_ms' => $latencyMs,
            ];
        } catch (PDOException $e) {
            return [
                'connected' => false,
                'status' => 'error',
                'message' => 'Database query execution failed.',
            ];
        }
    }

    /**
     * Reset the active connection (useful in CLI or long-running workers).
     */
    public static function resetConnection(): void
    {
        self::$connection = null;
    }
}
