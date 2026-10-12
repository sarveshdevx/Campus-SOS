<?php
declare(strict_types=1);

namespace CampusSOS\Models;

use CampusSOS\Config\Database;

class AuditLog
{
    public static function log(
        ?int $userId,
        string $action,
        string $entityType,
        ?int $entityId = null,
        ?array $details = null
    ): void {
        $pdo = Database::getConnection();
        if (!$pdo) return;

        try {
            $stmt = $pdo->prepare("
                INSERT INTO audit_logs (
                    actor_user_id, action, resource_type, resource_id, ip_address, user_agent, details, created_at
                ) VALUES (
                    :actor_user_id, :action, :resource_type, :resource_id, :ip_address, :user_agent, :details, NOW()
                )
            ");

            $stmt->execute([
                ':actor_user_id' => $userId,
                ':action' => $action,
                ':resource_type' => $entityType,
                ':resource_id' => $entityId,
                ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                ':user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                ':details' => $details ? json_encode($details) : null,
            ]);
        } catch (\Exception $e) {
            error_log("[CampusSOS AuditLog Error]: " . $e->getMessage());
        }
    }
}

