<?php
declare(strict_types=1);

namespace CampusSOS\Middleware;

use CampusSOS\Services\ResponseService;

class RoleMiddleware
{
    /**
     * Enforce that the authenticated user possesses one of the allowed roles.
     * Never authorizes based on browser-submitted role flags.
     *
     * @param array<string> $allowedRoles
     * @return array Authenticated user record
     */
    public static function requireRole(array $allowedRoles): array
    {
        $user = AuthMiddleware::authenticate();
        $userRole = $user['role'] ?? 'student';

        if (!in_array($userRole, $allowedRoles, true)) {
            ResponseService::error(
                'Access denied. Your account role lacks permission for this resource.',
                403,
                null,
                'FORBIDDEN'
            );
        }

        return $user;
    }

    /**
     * Shortcut for administrative access only.
     */
    public static function requireAdmin(): array
    {
        return self::requireRole(['admin']);
    }

    /**
     * Shortcut for responder or administrator access.
     */
    public static function requireResponder(): array
    {
        return self::requireRole(['responder', 'admin']);
    }
}

