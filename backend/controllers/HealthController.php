<?php
declare(strict_types=1);

namespace CampusSOS\Controllers;

use CampusSOS\Config\Database;
use CampusSOS\Services\ResponseService;

class HealthController
{
    /**
     * General API health check endpoint: GET /api/health
     */
    public function apiHealth(): void
    {
        ResponseService::json([
            'status' => 'healthy',
            'app' => 'CampusSOS REST Backend',
            'version' => '1.0.0',
            'php_version' => PHP_VERSION,
        ], 200, 'Service operational.');
    }

    /**
     * Database-specific health check endpoint: GET /api/health/db
     *
     * Returns:
     * - HTTP 200 when MySQL is connected.
     * - HTTP 503 (Service Unavailable) when connection cannot be established.
     *
     * Security requirement: NEVER expose passwords, host paths, or raw DSN in responses.
     */
    public function dbHealth(): void
    {
        $health = Database::checkHealth();

        if ($health['connected']) {
            ResponseService::json([
                'connected' => true,
                'status' => 'healthy',
                'database' => $health['database'],
                'driver' => $health['driver'],
                'latency_ms' => $health['latency_ms'],
            ], 200, 'Database connection verified.');
        } else {
            ResponseService::error(
                $health['message'] ?? 'Database connection unavailable. Verify MySQL service in XAMPP.',
                503,
                null,
                'DATABASE_UNAVAILABLE'
            );
        }
    }
}

