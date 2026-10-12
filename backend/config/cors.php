<?php
declare(strict_types=1);

namespace CampusSOS\Config;

class Cors
{
    /**
     * Handle Cross-Origin Resource Sharing (CORS) with strict origin allowlist.
     * Never reflects arbitrary origins and never uses wildcard '*' with credentials.
     */
    public static function handle(): void
    {
        $allowedOrigins = [
            'http://localhost:5173',
            'http://127.0.0.1:5173',
            'http://localhost:3000',
            'http://127.0.0.1:3000',
        ];

        // Add custom origin from environment if specified
        $configuredOrigin = getenv('FRONTEND_ORIGIN');
        if ($configuredOrigin && !in_array($configuredOrigin, $allowedOrigins, true)) {
            $allowedOrigins[] = rtrim($configuredOrigin, '/');
        }

        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if (!empty($origin) && in_array($origin, $allowedOrigins, true)) {
            header("Access-Control-Allow-Origin: {$origin}");
            header("Access-Control-Allow-Credentials: true");
            header("Vary: Origin");
        } elseif (empty($origin)) {
            // Same-origin or direct local CLI/browser request
            header("Access-Control-Allow-Origin: http://localhost:5173");
            header("Access-Control-Allow-Credentials: true");
        }

        header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, X-CSRF-Token, X-XSRF-Token");
        header("Access-Control-Max-Age: 86400");

        // Fast return for HTTP preflight OPTIONS requests
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            http_response_code(204);
            exit(0);
        }
    }
}
