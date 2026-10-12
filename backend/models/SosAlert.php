<?php
declare(strict_types=1);

namespace CampusSOS\Models;

use CampusSOS\Config\Database;
use CampusSOS\Models\IncidentAttachment;
use PDO;

class SosAlert
{
    public static function create(array $data): ?int
    {
        $pdo = Database::getConnection();
        if (!$pdo) return null;

        $stmt = $pdo->prepare("
            INSERT INTO sos_alerts (
                alert_code, user_id, incident_id, status, location_name, room_number,
                emergency_type, emergency_details, assigned_responder_id, assigned_department,
                detected_category, routing_decision, triage_status, coordinates_lat, coordinates_lng,
                triggered_at, created_at
            ) VALUES (
                :alert_code, :user_id, :incident_id, :status, :location_name, :room_number,
                :emergency_type, :emergency_details, :assigned_responder_id, :assigned_department,
                :detected_category, :routing_decision, :triage_status, :coordinates_lat, :coordinates_lng,
                NOW(), NOW()
            )
        ");

        $stmt->execute([
            ':alert_code' => $data['alert_code'],
            ':user_id' => $data['user_id'] ?? null,
            ':incident_id' => $data['incident_id'] ?? null,
            ':status' => $data['status'] ?? 'triggered',
            ':location_name' => $data['location_name'],
            ':room_number' => $data['room_number'] ?? null,
            ':emergency_type' => $data['emergency_type'] ?? null,
            ':emergency_details' => $data['emergency_details'] ?? null,
            ':assigned_responder_id' => $data['assigned_responder_id'] ?? null,
            ':assigned_department' => $data['assigned_department'] ?? null,
            ':detected_category' => $data['detected_category'] ?? null,
            ':routing_decision' => $data['routing_decision'] ?? null,
            ':triage_status' => $data['triage_status'] ?? 'routed',
            ':coordinates_lat' => $data['coordinates_lat'] ?? null,
            ':coordinates_lng' => $data['coordinates_lng'] ?? null,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function findActive(): ?array
    {
        $pdo = Database::getConnection();
        if (!$pdo) return null;

        $stmt = $pdo->prepare("
            SELECT s.*,
                   s.emergency_type as emergencyType,
                   s.emergency_details as emergencyDetails,
                   s.assigned_responder_id as assignedResponderId,
                   s.assigned_department as assignedDepartment,
                   s.detected_category as detectedCategory,
                   s.routing_decision as routingDecision,
                   s.triage_status as triageStatus,
                   s.location_name as locationName,
                   s.room_number as roomNumber,
                   s.alert_code as alertCode,
                   s.triggered_at as triggeredAt,
                   u.full_name as user_name,
                   u.full_name as userName,
                   u.phone_number as user_phone,
                   u.phone_number as userPhone,
                   r_u.full_name as assignedResponderName,
                   r_u.full_name as assigned_responder_name
            FROM sos_alerts s
            LEFT JOIN users u ON s.user_id = u.id
            LEFT JOIN responders r ON s.assigned_responder_id = r.id
            LEFT JOIN users r_u ON r.user_id = r_u.id
            WHERE s.status IN ('triggered', 'acknowledged', 'dispatched')
            ORDER BY s.triggered_at DESC
            LIMIT 1
        ");
        $stmt->execute();
        $alert = $stmt->fetch();

        return $alert ?: null;
    }

    public static function findById(int $id): ?array
    {
        $pdo = Database::getConnection();
        if (!$pdo) return null;

        $stmt = $pdo->prepare("
            SELECT s.*,
                   s.emergency_type as emergencyType,
                   s.emergency_details as emergencyDetails,
                   s.assigned_responder_id as assignedResponderId,
                   s.assigned_department as assignedDepartment,
                   s.detected_category as detectedCategory,
                   s.routing_decision as routingDecision,
                   s.triage_status as triageStatus,
                   s.location_name as locationName,
                   s.room_number as roomNumber,
                   s.alert_code as alertCode,
                   s.triggered_at as triggeredAt,
                   u.full_name as user_name,
                   u.full_name as userName,
                   u.phone_number as user_phone,
                   u.phone_number as userPhone,
                   r_u.full_name as assignedResponderName,
                   r_u.full_name as assigned_responder_name
            FROM sos_alerts s
            LEFT JOIN users u ON s.user_id = u.id
            LEFT JOIN responders r ON s.assigned_responder_id = r.id
            LEFT JOIN users r_u ON r.user_id = r_u.id
            WHERE s.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $alert = $stmt->fetch();

        if ($alert) {
            $alert['attachments'] = IncidentAttachment::findBySosAlertId($id);
        }

        return $alert ?: null;
    }

    public static function acknowledge(int $id): bool
    {
        $pdo = Database::getConnection();
        if (!$pdo) return false;

        $stmt = $pdo->prepare("
            UPDATE sos_alerts
            SET status = 'acknowledged', acknowledged_at = NOW()
            WHERE id = :id AND status IN ('triggered', 'dispatched')
        ");

        return $stmt->execute([':id' => $id]);
    }

    public static function resolve(int $id, string $notes, ?int $resolvedByUserId = null): bool
    {
        $pdo = Database::getConnection();
        if (!$pdo) return false;

        $stmt = $pdo->prepare("
            UPDATE sos_alerts
            SET status = 'resolved',
                resolved_at = NOW(),
                resolution_notes = :notes,
                resolved_by_user_id = :user_id
            WHERE id = :id AND status IN ('triggered', 'acknowledged', 'dispatched')
        ");

        $executed = $stmt->execute([
            ':id' => $id,
            ':notes' => $notes,
            ':user_id' => $resolvedByUserId,
        ]);

        if ($executed && $stmt->rowCount() > 0) {
            // Also update any assignments for this alert to 'cleared'
            $pdo->prepare("
                UPDATE sos_assignments
                SET assignment_status = 'cleared'
                WHERE sos_alert_id = :id AND assignment_status != 'cleared'
            ")->execute([':id' => $id]);

            return true;
        }

        return false;
    }

    public static function findAllActive(?array $user = null): array
    {
        $pdo = Database::getConnection();
        if (!$pdo) return [];

        $role = $user['role'] ?? 'guest';

        $baseSelect = "
            SELECT s.*,
                   s.emergency_type as emergencyType,
                   s.emergency_details as emergencyDetails,
                   s.assigned_responder_id as assignedResponderId,
                   s.assigned_department as assignedDepartment,
                   s.detected_category as detectedCategory,
                   s.routing_decision as routingDecision,
                   s.triage_status as triageStatus,
                   s.location_name as locationName,
                   s.room_number as roomNumber,
                   s.alert_code as alertCode,
                   s.triggered_at as triggeredAt,
                   u.full_name as user_name,
                   u.full_name as userName,
                   u.phone_number as user_phone,
                   u.phone_number as userPhone,
                   r_u.full_name as assignedResponderName,
                   r_u.full_name as assigned_responder_name
            FROM sos_alerts s
            LEFT JOIN users u ON s.user_id = u.id
            LEFT JOIN responders r ON s.assigned_responder_id = r.id
            LEFT JOIN users r_u ON r.user_id = r_u.id
        ";

        $rows = [];
        if ($role === 'admin') {
            // Admin sees ALL active alerts across all categories, including awaiting triage
            $sql = $baseSelect . "
                WHERE s.status IN ('triggered', 'acknowledged', 'dispatched')
                ORDER BY s.triggered_at DESC
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $rows = $stmt->fetchAll();
        } elseif ($role === 'responder') {
            // Responder sees ONLY alerts routed to them or their authorized category
            $responder = $user['responderProfile'] ?? null;
            $responderId = (int) ($responder['id'] ?? 0);
            $category = strtolower((string) ($responder['category'] ?? ''));

            $categoryClause = "1=0";
            if ($category === 'fire') {
                $categoryClause = "(s.assigned_department = 'fire' OR s.emergency_type LIKE '%Fire%' OR s.emergency_type LIKE '%Smoke%' OR s.detected_category LIKE '%Fire%' OR s.detected_category LIKE '%Smoke%')";
            } elseif ($category === 'maintenance') {
                $categoryClause = "(s.assigned_department = 'maintenance' OR s.emergency_type LIKE '%Electrical%' OR s.emergency_type LIKE '%Gas%' OR s.detected_category LIKE '%Electrical%' OR s.detected_category LIKE '%Gas%')";
            } elseif ($category === 'security') {
                $categoryClause = "(s.assigned_department = 'security' OR s.emergency_type LIKE '%Assault%' OR s.emergency_type LIKE '%Harass%' OR s.emergency_type LIKE '%Suspicious%' OR s.emergency_type LIKE '%Theft%' OR s.detected_category LIKE '%Assault%' OR s.detected_category LIKE '%Harass%' OR s.detected_category LIKE '%Suspicious%' OR s.detected_category LIKE '%Theft%')";
            } elseif ($category === 'medical') {
                $categoryClause = "(s.assigned_department = 'medical' OR s.emergency_type LIKE '%Vehicle%' OR s.emergency_type LIKE '%Collision%' OR s.emergency_type LIKE '%Injury%' OR s.emergency_type LIKE '%Accident%' OR s.emergency_type LIKE '%Medical%' OR s.detected_category LIKE '%Vehicle%' OR s.detected_category LIKE '%Collision%' OR s.detected_category LIKE '%Injury%' OR s.detected_category LIKE '%Accident%' OR s.detected_category LIKE '%Medical%')";
            } elseif ($category === 'anti_ragging') {
                $categoryClause = "(s.assigned_department = 'anti_ragging' OR s.emergency_type LIKE '%Ragging%')";
            } elseif ($category === 'sexual_harassment') {
                $categoryClause = "(s.assigned_department = 'sexual_harassment' OR s.emergency_type LIKE '%ICC%')";
            }

            $sql = $baseSelect . "
                WHERE s.status IN ('triggered', 'acknowledged', 'dispatched')
                  AND (s.triage_status IS NULL OR s.triage_status != 'awaiting_triage')
                  AND (
                      s.assigned_responder_id = :resp_id
                      OR s.id IN (SELECT sos_alert_id FROM sos_assignments WHERE responder_id = :resp_id_assign)
                      OR {$categoryClause}
                  )
                ORDER BY s.triggered_at DESC
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':resp_id' => $responderId,
                ':resp_id_assign' => $responderId,
            ]);
            $rows = $stmt->fetchAll();
        } elseif ($role === 'student' && !empty($user['id'])) {
            // Students only see their own active SOS if triggered
            $sql = $baseSelect . "
                WHERE s.status IN ('triggered', 'acknowledged', 'dispatched')
                  AND s.user_id = :user_id
                ORDER BY s.triggered_at DESC
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':user_id' => (int) $user['id']]);
            $rows = $stmt->fetchAll();
        }

        foreach ($rows as &$row) {
            $row['attachments'] = IncidentAttachment::findBySosAlertId((int) $row['id']);
        }

        return $rows;
    }

    public static function assignToResponder(int $sosId, int $responderId, ?string $notes = null): bool
    {
        $pdo = Database::getConnection();
        if (!$pdo) return false;

        // Lookup responder's category and name
        $stmtResp = $pdo->prepare("
            SELECT r.id, r.category, u.full_name
            FROM responders r
            JOIN users u ON r.user_id = u.id
            WHERE r.id = :r_id
            LIMIT 1
        ");
        $stmtResp->execute([':r_id' => $responderId]);
        $resp = $stmtResp->fetch();
        $respCat = $resp['category'] ?? 'security';
        $respName = $resp['full_name'] ?? 'Responder #' . $responderId;

        $stmt = $pdo->prepare("
            INSERT INTO sos_assignments (sos_alert_id, responder_id, assignment_status, dispatched_at, notes)
            VALUES (:sos_id, :resp_id, 'dispatched', NOW(), :notes)
            ON DUPLICATE KEY UPDATE
                assignment_status = 'dispatched',
                dispatched_at = NOW(),
                notes = VALUES(notes)
        ");

        $assigned = $stmt->execute([
            ':sos_id' => $sosId,
            ':resp_id' => $responderId,
            ':notes' => $notes ?: "Dispatched to {$respName}",
        ]);

        if ($assigned) {
            $updateStmt = $pdo->prepare("
                UPDATE sos_alerts
                SET assigned_responder_id = :resp_id,
                    assigned_department = :resp_cat,
                    triage_status = 'routed',
                    status = 'dispatched',
                    routing_decision = :decision
                WHERE id = :id
            ");
            $updateStmt->execute([
                ':resp_id' => $responderId,
                ':resp_cat' => $respCat,
                ':decision' => $notes ?: "Assigned to {$respName} ({$respCat})",
                ':id' => $sosId,
            ]);
        }

        return $assigned;
    }
}

