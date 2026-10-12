<?php
declare(strict_types=1);

namespace CampusSOS\Middleware;

use CampusSOS\Models\User;
use CampusSOS\Services\ResponseService;

class AuthMiddleware
{
    /**
     * Ensure the incoming request has a valid authenticated session.
     * Always verifies active account status against MySQL.
     *
     * @return array Authenticated user record
     */
    public static function authenticate(): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            \CampusSOS\Config\App::initSession();
        }

        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            ResponseService::error(
                'Authentication required. Please log in to proceed.',
                401,
                null,
                'UNAUTHENTICATED'
            );
        }

        $user = User::findById((int) $userId);
        if (!$user) {
            // Check session fallback only if DB is unreachable
            $user = $_SESSION['cached_user'] ?? null;
        }

        if (!$user) {
            // User no longer exists in database
            $_SESSION = [];
            session_destroy();
            ResponseService::error(
                'Session user not found. Please log in again.',
                401,
                null,
                'USER_NOT_FOUND'
            );
        }

        // Check account status
        $status = $user['account_status'] ?? 'active';
        if ($status !== 'active') {
            $_SESSION = [];
            session_destroy();
            ResponseService::error(
                "Your account is {$status}. Please contact campus administration.",
                403,
                null,
                'ACCOUNT_INACTIVE'
            );
        }

        return $user;
    }
}

