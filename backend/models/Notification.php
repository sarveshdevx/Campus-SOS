<?php
declare(strict_types=1);

namespace CampusSOS\Models;

use CampusSOS\Config\Database;
use PDO;

class Notification
{
    /**
     * Create a database-backed notification.
     */
    public static function create(int $recipientUserId, string $type, string $message, ?string $linkUrl = null): ?int
    {
        $pdo = Database::getConnection();
        if (!$pdo) return null;

        $stmt = $pdo->prepare("
            INSERT INTO notifications (recipient_user_id, notification_type, message, is_read, link_url, created_at)
            VALUES (:recipient_user_id, :type, :message, 0, :link_url, NOW())
        ");

        $stmt->execute([
            ':recipient_user_id' => $recipientUserId,
            ':type' => $type,
            ':message' => substr($message, 0, 255),
            ':link_url' => $linkUrl ? substr($linkUrl, 0, 255) : null,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * Retrieve notifications for a given user.
     */
    public static function findByRecipient(int $recipientUserId, bool $unreadOnly = false): array
    {
        $pdo = Database::getConnection();
        if (!$pdo) return [];

        $sql = "SELECT id, notification_type, message, is_read, link_url, created_at
                FROM notifications
                WHERE recipient_user_id = :user_id";

        if ($unreadOnly) {
            $sql .= " AND is_read = 0";
        }

        $sql .= " ORDER BY created_at DESC LIMIT 30";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':user_id' => $recipientUserId]);

        return $stmt->fetchAll();
    }

    /**
     * Mark a notification as read.
     */
    public static function markAsRead(int $notificationId, int $recipientUserId): bool
    {
        $pdo = Database::getConnection();
        if (!$pdo) return false;

        $stmt = $pdo->prepare("
            UPDATE notifications
            SET is_read = 1
            WHERE id = :id AND recipient_user_id = :user_id
        ");

        return $stmt->execute([
            ':id' => $notificationId,
            ':user_id' => $recipientUserId,
        ]);
    }

    /**
     * Mark all notifications as read for a given user.
     */
    public static function markAllAsRead(int $recipientUserId): bool
    {
        $pdo = Database::getConnection();
        if (!$pdo) return false;

        $stmt = $pdo->prepare("
            UPDATE notifications
            SET is_read = 1
            WHERE recipient_user_id = :user_id AND is_read = 0
        ");

        return $stmt->execute([':user_id' => $recipientUserId]);
    }
}

