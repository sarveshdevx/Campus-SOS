<?php
declare(strict_types=1);

namespace CampusSOS\Models;

use CampusSOS\Config\Database;
use PDO;

class IncidentUpdate
{
    public static function create(
        int $incidentId,
        ?int $actorUserId,
        ?string $prevStatus,
        string $newStatus,
        string $updateText,
        bool $isInternal = false
    ): ?int {
        $pdo = Database::getConnection();
        if (!$pdo) return null;

        $stmt = $pdo->prepare("
            INSERT INTO incident_updates (
                incident_id, actor_user_id, previous_status, new_status, update_text, is_internal, created_at
            ) VALUES (
                :incident_id, :actor_user_id, :previous_status, :new_status, :update_text, :is_internal, NOW()
            )
        ");

        $stmt->execute([
            ':incident_id' => $incidentId,
            ':actor_user_id' => $actorUserId,
            ':previous_status' => $prevStatus,
            ':new_status' => $newStatus,
            ':update_text' => $updateText,
            ':is_internal' => $isInternal ? 1 : 0,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function findByIncident(int $incidentId, bool $includeInternal = false): array
    {
        $pdo = Database::getConnection();
        if (!$pdo) return [];

        $sql = "SELECT iu.id, iu.incident_id,
                       CASE WHEN i.is_anonymous = 1 AND (iu.actor_user_id = i.reporter_user_id OR iu.actor_user_id IS NULL) THEN NULL
                            ELSE iu.actor_user_id END AS actor_user_id,
                       iu.previous_status, iu.new_status,
                       iu.update_text, iu.is_internal, iu.created_at,
                       CASE WHEN i.is_anonymous = 1 AND (iu.actor_user_id = i.reporter_user_id OR iu.actor_user_id IS NULL) THEN 'Anonymous Student'
                            ELSE u.full_name END AS actor_name,
                       u.role AS actor_role
                FROM incident_updates iu
                JOIN incidents i ON iu.incident_id = i.id
                LEFT JOIN users u ON iu.actor_user_id = u.id
                WHERE iu.incident_id = :incident_id";

        if (!$includeInternal) {
            $sql .= " AND iu.is_internal = 0";
        }

        $sql .= " ORDER BY iu.created_at ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':incident_id' => $incidentId]);

        return $stmt->fetchAll();
    }
}

