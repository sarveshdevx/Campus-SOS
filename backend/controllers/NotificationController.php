<?php
declare(strict_types=1);

namespace CampusSOS\Controllers;

use CampusSOS\Models\Notification;
use CampusSOS\Services\AuthService;
use CampusSOS\Services\ResponseService;
use CampusSOS\Middleware\AuthMiddleware;

class NotificationController
{
    /**
     * GET /api/notifications
     * Retrieve notifications for current user.
     */
    public function index(): void
    {
        AuthMiddleware::authenticate();
        $user = AuthService::getCurrentUser();
        $unreadOnly = !empty($_GET['unread']);

        $notifications = Notification::findByRecipient((int) $user['id'], $unreadOnly);
        ResponseService::json($notifications, 200);
    }

    /**
     * PUT /api/notifications/{id}/read
     * Mark single notification as read.
     */
    public function markRead(int $id): void
    {
        AuthMiddleware::authenticate();
        $user = AuthService::getCurrentUser();

        Notification::markAsRead($id, (int) $user['id']);
        ResponseService::json(['id' => $id, 'isRead' => true], 200);
    }

    /**
     * PUT /api/notifications/read-all
     * Mark all notifications as read.
     */
    public function markAllRead(): void
    {
        AuthMiddleware::authenticate();
        $user = AuthService::getCurrentUser();

        Notification::markAllAsRead((int) $user['id']);
        ResponseService::json(['success' => true], 200, 'All notifications marked as read.');
    }
}

