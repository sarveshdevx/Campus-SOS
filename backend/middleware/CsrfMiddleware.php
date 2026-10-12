<?php
declare(strict_types=1);

namespace CampusSOS\Middleware;

use CampusSOS\Services\ResponseService;

class CsrfMiddleware
{
    private const SESSION_KEY = 'csrf_token';
    private const HEADER_KEY = 'HTTP_X_CSRF_TOKEN';

    /**
     * Retrieve or generate the current session CSRF token.
     */
    public static function getToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            \CampusSOS\Config\App::initSession();
        }

        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION[self::SESSION_KEY];
    }

    /**
     * Regenerate CSRF token (called upon login or logout).
     */
    public static function regenerateToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            \CampusSOS\Config\App::initSession();
        }

        $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        return (string) $_SESSION[self::SESSION_KEY];
    }

    /**
     * Compare a submitted token against the session token with hash_equals.
     */
    public static function validateToken(string $token): bool
    {
        $stored = self::getToken();
        if (empty($token) || empty($stored)) {
            return false;
        }
        return hash_equals($stored, $token);
    }

    /**
     * Validate CSRF token for state-changing HTTP requests (POST, PUT, PATCH, DELETE).
     *
     * @param array<string> $exemptRoutes Routes exempted from CSRF (e.g., initial auth handshake if unauthenticated)
     */
    public static function validate(array $exemptRoutes = []): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        // Safe HTTP methods do not require CSRF validation
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }

        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

        // Check if route is explicitly exempted
        foreach ($exemptRoutes as $exempt) {
            if ($uri === $exempt || str_starts_with($uri, $exempt)) {
                return;
            }
        }

        $submittedToken = self::extractSubmittedToken();
        $storedToken = $_SESSION[self::SESSION_KEY] ?? '';

        if (empty($submittedToken) || empty($storedToken) || !hash_equals($storedToken, $submittedToken)) {
            ResponseService::error(
                'Invalid or missing CSRF token. Please obtain a fresh token and retry.',
                403,
                null,
                'CSRF_TOKEN_MISMATCH'
            );
        }
    }

    private static function extractSubmittedToken(): ?string
    {
        // 1. Check custom X-CSRF-Token header (case variations in PHP $_SERVER)
        if (!empty($_SERVER[self::HEADER_KEY])) {
            return trim((string) $_SERVER[self::HEADER_KEY]);
        }

        if (!empty($_SERVER['HTTP_X_XSRF_TOKEN'])) {
            return trim((string) $_SERVER['HTTP_X_XSRF_TOKEN']);
        }

        // 2. Check JSON payload if Content-Type is application/json
        $rawInput = file_get_contents('php://input');
        if (!empty($rawInput)) {
            $data = json_decode($rawInput, true);
            if (is_array($data) && !empty($data['_csrf'])) {
                return trim((string) $data['_csrf']);
            }
        }

        // 3. Check $_POST field
        if (!empty($_POST['_csrf'])) {
            return trim((string) $_POST['_csrf']);
        }

        return null;
    }
}
