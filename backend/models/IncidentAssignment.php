<?php
declare(strict_types=1);

namespace CampusSOS\Models;

use CampusSOS\Config\Database;
use PDO;

class IncidentAssignment
{
    public static function assign(int $incidentId, int $responderId, ?string $notes = null): bool
    {
        $pdo = Database::getConnection();
        if (!$pdo) return false;

        $stmt = $pdo->prepare("
            INSERT INTO incident_assignments (incident_id, responder_id, assignment_status, assigned_at, notes)
            VALUES (:incident_id, :responder_id, 'assigned', NOW(), :notes)
            ON DUPLICATE KEY UPDATE
                assignment_status = 'assigned',
                assigned_at = NOW(),
                notes = VALUES(notes)
        ");

        return $stmt->execute([
            ':incident_id' => $incidentId,
            ':responder_id' => $responderId,
            ':notes' => $notes,
        ]);
    }

    public static function acknowledge(int $incidentId, int $responderId): bool
    {
        $pdo = Database::getConnection();
        if (!$pdo) return false;

        $stmt = $pdo->prepare("
            UPDATE incident_assignments
            SET assignment_status = 'in_progress'
            WHERE incident_id = :incident_id AND responder_id = :responder_id
        ");

        return $stmt->execute([
            ':incident_id' => $incidentId,
            ':responder_id' => $responderId,
        ]);
    }

    public static function complete(int $incidentId, int $responderId, ?string $notes = null): bool
    {
        $pdo = Database::getConnection();
        if (!$pdo) return false;

        $stmt = $pdo->prepare("
            UPDATE incident_assignments
            SET assignment_status = 'completed', completed_at = NOW(), notes = COALESCE(:notes, notes)
            WHERE incident_id = :incident_id AND responder_id = :responder_id
        ");

        return $stmt->execute([
            ':incident_id' => $incidentId,
            ':responder_id' => $responderId,
            ':notes' => $notes,
        ]);
    }

    public static function completeAllForIncident(int $incidentId, ?string $notes = null): bool
    {
        $pdo = Database::getConnection();
        if (!$pdo) return false;

        $stmt = $pdo->prepare("
            UPDATE incident_assignments
            SET assignment_status = 'completed', completed_at = NOW(), notes = COALESCE(:notes, notes)
            WHERE incident_id = :incident_id AND assignment_status != 'completed'
        ");

        return $stmt->execute([
            ':incident_id' => $incidentId,
            ':notes' => $notes,
        ]);
    }

    public static function findByIncident(int $incidentId): array
    {
        $pdo = Database::getConnection();
        if (!$pdo) return [];

        $stmt = $pdo->prepare("
            SELECT ia.*, r.category, r.department, r.contact_phone, u.full_name as responder_name, u.email as responder_email
            FROM incident_assignments ia
            JOIN responders r ON ia.responder_id = r.id
            JOIN users u ON r.user_id = u.id
            WHERE ia.incident_id = :incident_id
            ORDER BY ia.assigned_at DESC
        ");

        $stmt->execute([':incident_id' => $incidentId]);
        return $stmt->fetchAll();
    }

    public static function findByResponder(int $responderId, bool $activeOnly = true): array
    {
        $pdo = Database::getConnection();
        if (!$pdo) return [];

        $sql = "
            SELECT ia.*, i.incident_number, i.title, i.category, i.location_name,
                   i.status as incident_status, i.severity, i.occurred_at, i.privacy_level
            FROM incident_assignments ia
            JOIN incidents i ON ia.incident_id = i.id
            WHERE ia.responder_id = :responder_id
        ";
        if ($activeOnly) {
            $sql .= " AND i.status NOT IN ('resolved', 'closed', 'rejected') AND ia.assignment_status != 'completed'";
        }
        $sql .= " ORDER BY ia.assigned_at DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':responder_id' => $responderId]);
        return $stmt->fetchAll();
    }

    public static function isAssignedToResponder(int $incidentId, int $responderId): bool
    {
        $pdo = Database::getConnection();
        if (!$pdo) return false;

        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM incident_assignments
            WHERE incident_id = :incident_id AND responder_id = :responder_id
        ");
        $stmt->execute([':incident_id' => $incidentId, ':responder_id' => $responderId]);
        return ((int) $stmt->fetchColumn()) > 0;
    }
}

