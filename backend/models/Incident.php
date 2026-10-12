<?php
declare(strict_types=1);

namespace CampusSOS\Models;

use CampusSOS\Config\Database;
use CampusSOS\Models\IncidentAttachment;
use PDO;

class Incident
{
    public static function create(array $data): ?int
    {
        $pdo = Database::getConnection();
        if (!$pdo) return null;

        $stmt = $pdo->prepare("
            INSERT INTO incidents (
                incident_number, reporter_user_id, location_id, location_name, room_number,
                coordinates_lat, coordinates_lng, category, title, description,
                occurred_at, is_anonymous, is_confidential, privacy_level, status,
                severity, ai_classified, ai_confidence, created_at, updated_at
            ) VALUES (
                :incident_number, :reporter_user_id, :location_id, :location_name, :room_number,
                :coordinates_lat, :coordinates_lng, :category, :title, :description,
                :occurred_at, :is_anonymous, :is_confidential, :privacy_level, :status,
                :severity, :ai_classified, :ai_confidence, NOW(), NOW()
            )
        ");

        $stmt->execute([
            ':incident_number' => $data['incident_number'],
            ':reporter_user_id' => !empty($data['is_anonymous']) ? null : ($data['reporter_user_id'] ?? null),
            ':location_id' => $data['location_id'] ?? null,
            ':location_name' => $data['location_name'],
            ':room_number' => $data['room_number'] ?? null,
            ':coordinates_lat' => $data['coordinates_lat'] ?? null,
            ':coordinates_lng' => $data['coordinates_lng'] ?? null,
            ':category' => $data['category'],
            ':title' => $data['title'],
            ':description' => $data['description'],
            ':occurred_at' => $data['occurred_at'] ?? date('Y-m-d H:i:s'),
            ':is_anonymous' => !empty($data['is_anonymous']) ? 1 : 0,
            ':is_confidential' => !empty($data['is_confidential']) ? 1 : 0,
            ':privacy_level' => $data['privacy_level'] ?? 'standard',
            ':status' => $data['status'] ?? 'reported',
            ':severity' => (function() use ($data) {
                $raw = strtolower((string)($data['severity'] ?? ($data['priority'] ?? 'medium')));
                if ($raw === 'urgent') return 'high';
                if ($raw === 'emergency') return 'critical';
                return in_array($raw, ['low', 'medium', 'high', 'critical'], true) ? $raw : 'medium';
            })(),
            ':ai_classified' => !empty($data['ai_classified']) ? 1 : 0,
            ':ai_confidence' => $data['ai_confidence'] ?? null,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function findAll(array $filters = []): array
    {
        $pdo = Database::getConnection();
        if (!$pdo) return [];

        $sql = "SELECT i.id, i.incident_number, i.location_id, i.location_name, i.room_number, i.coordinates_lat, i.coordinates_lng,
                       i.category, i.title, i.description, i.occurred_at, i.is_anonymous, i.is_confidential,
                       i.privacy_level, i.status, i.severity, i.ai_classified, i.ai_confidence, i.created_at, i.updated_at,
                       CASE WHEN i.is_anonymous = 1 THEN NULL ELSE i.reporter_user_id END AS reporter_user_id,
                       CASE WHEN i.is_anonymous = 1 THEN 'Anonymous' ELSE u.full_name END AS reporter_name,
                       CASE WHEN i.is_anonymous = 1 THEN NULL ELSE u.student_id END AS reporter_student_id,
                       CASE WHEN i.is_anonymous = 1 THEN NULL ELSE u.email END AS reporter_email,
                       CASE WHEN i.is_anonymous = 1 THEN NULL ELSE u.phone_number END AS reporter_phone
                FROM incidents i
                LEFT JOIN users u ON i.reporter_user_id = u.id
                WHERE 1=1";
        $params = [];

        if (!empty($filters['category']) && $filters['category'] !== 'all') {
            $sql .= " AND i.category = :category";
            $params[':category'] = $filters['category'];
        }

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $sql .= " AND i.status = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['privacy_level'])) {
            $sql .= " AND i.privacy_level = :privacy_level";
            $params[':privacy_level'] = $filters['privacy_level'];
        }

        $sql .= " ORDER BY i.created_at DESC LIMIT 50";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $r['attachments'] = IncidentAttachment::findByIncidentId((int) $r['id']);
        }
        return $rows;
    }

    public static function findAllForUser(?array $user, array $filters = []): array
    {
        $pdo = Database::getConnection();
        if (!$pdo) return [];

        $role = $user['role'] ?? 'guest';
        $userId = $user['id'] ?? null;

        $sql = "SELECT i.id, i.incident_number, i.location_id, i.location_name, i.room_number, i.coordinates_lat, i.coordinates_lng,
                       i.category, i.title, i.description, i.occurred_at, i.is_anonymous, i.is_confidential,
                       i.privacy_level, i.status, i.severity, i.ai_classified, i.ai_confidence, i.created_at, i.updated_at,
                       CASE WHEN i.is_anonymous = 1 THEN NULL ELSE i.reporter_user_id END AS reporter_user_id,
                       CASE WHEN i.is_anonymous = 1 THEN 'Anonymous' ELSE u.full_name END AS reporter_name,
                       CASE WHEN i.is_anonymous = 1 THEN NULL ELSE u.student_id END AS reporter_student_id,
                       CASE WHEN i.is_anonymous = 1 THEN NULL ELSE u.email END AS reporter_email,
                       CASE WHEN i.is_anonymous = 1 THEN NULL ELSE u.phone_number END AS reporter_phone
                FROM incidents i
                LEFT JOIN users u ON i.reporter_user_id = u.id
                WHERE 1=1";
        $params = [];

        // Role-based visibility scoping:
        if ($role === 'admin') {
            // Admins can see non-confidential reports, or confidential reports if authorized
            // Standard expo admin sees all triageable campus reports
        } elseif ($role === 'responder') {
            // Responders can see reports matching their category OR explicitly assigned to them
            $responderProfile = $user['responderProfile'] ?? null;
            $category = $responderProfile['category'] ?? '';
            $responderId = $responderProfile['id'] ?? 0;

            $sql .= " AND (
                (i.category = :responder_cat AND (i.privacy_level != 'confidential' OR i.category = 'sexual_harassment' OR i.category = 'anti_ragging'))
                OR i.id IN (SELECT incident_id FROM incident_assignments WHERE responder_id = :responder_id)
            )";
            $params[':responder_cat'] = $category;
            $params[':responder_id'] = $responderId;
        } elseif ($role === 'student') {
            // Students can only see non-confidential public reports or their own submitted reports
            $sql .= " AND (
                (i.privacy_level = 'standard')
                OR (i.reporter_user_id = :student_user_id)
            )";
            $params[':student_user_id'] = $userId;
        } else {
            // Unauthenticated: only public standard reports
            $sql .= " AND i.privacy_level = 'standard'";
        }

        if (!empty($filters['category']) && $filters['category'] !== 'all') {
            $sql .= " AND i.category = :category";
            $params[':category'] = $filters['category'];
        }

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'active') {
                $sql .= " AND i.status NOT IN ('resolved', 'closed', 'rejected')";
            } elseif ($filters['status'] !== 'all') {
                $sql .= " AND i.status = :status";
                $params[':status'] = $filters['status'];
            }
        } elseif ($role === 'responder' || !empty($filters['active_only'])) {
            $sql .= " AND i.status NOT IN ('resolved', 'closed', 'rejected')";
        }

        if (!empty($filters['unassigned_only'])) {
            $sql .= " AND i.id NOT IN (SELECT incident_id FROM incident_assignments)";
        }

        $sql .= " ORDER BY i.created_at DESC LIMIT 100";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $r['attachments'] = IncidentAttachment::findByIncidentId((int) $r['id']);
        }
        return $rows;
    }

    public static function findById(int $id): ?array
    {
        $pdo = Database::getConnection();
        if (!$pdo) return null;

        $stmt = $pdo->prepare("
            SELECT i.id, i.incident_number, i.location_id, i.location_name, i.room_number, i.coordinates_lat, i.coordinates_lng,
                   i.category, i.title, i.description, i.occurred_at, i.is_anonymous, i.is_confidential,
                   i.privacy_level, i.status, i.severity, i.ai_classified, i.ai_confidence, i.created_at, i.updated_at,
                   CASE WHEN i.is_anonymous = 1 THEN NULL ELSE i.reporter_user_id END AS reporter_user_id,
                   CASE WHEN i.is_anonymous = 1 THEN 'Anonymous' ELSE u.full_name END AS reporter_name,
                   CASE WHEN i.is_anonymous = 1 THEN NULL ELSE u.student_id END AS reporter_student_id,
                   CASE WHEN i.is_anonymous = 1 THEN NULL ELSE u.email END AS reporter_email,
                   CASE WHEN i.is_anonymous = 1 THEN NULL ELSE u.phone_number END AS reporter_phone
            FROM incidents i
            LEFT JOIN users u ON i.reporter_user_id = u.id
            WHERE i.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $incident = $stmt->fetch();

        if ($incident) {
            $incident['attachments'] = IncidentAttachment::findByIncidentId($id);
        }

        return $incident ?: null;
    }

    public static function updateStatus(int $id, string $status): bool
    {
        $pdo = Database::getConnection();
        if (!$pdo) return false;

        $stmt = $pdo->prepare("
            UPDATE incidents
            SET status = :status, updated_at = NOW()
            WHERE id = :id
        ");

        return $stmt->execute([
            ':status' => $status,
            ':id' => $id,
        ]);
    }
}
