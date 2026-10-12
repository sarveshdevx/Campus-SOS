<?php
declare(strict_types=1);

namespace CampusSOS\Models;

use CampusSOS\Config\Database;
use CampusSOS\Models\AuditLog;
use CampusSOS\Models\IncidentUpdate;
use CampusSOS\Models\IncidentAttachment;
use PDO;

class IncidentMatch
{
    /**
     * Create or update a duplicate candidate pair.
     * Supports Incident vs Incident, SOS vs SOS, and Incident vs SOS.
     * Enforces canonical pair normalization, eliminates self-matching,
     * and preserves prior dismissal / confirmation decisions.
     */
    public static function createCandidate(
        string|int $typeAOrIdA,
        int $idAOrIdB,
        string|float $typeBOrScore,
        int|string $idBOrRationale,
        float|array|null $scoreOrBreakdown = null,
        ?string $rationale = null,
        ?array $breakdown = null
    ): bool {
        // Support backward-compatible signature: createCandidate(int $incA, int $incB, float $score, string $rationale)
        if (is_int($typeAOrIdA)) {
            $typeA = 'incident';
            $idA = (int) $typeAOrIdA;
            $typeB = 'incident';
            $idB = (int) $idAOrIdB;
            $score = (float) $typeBOrScore;
            $rat = (string) $idBOrRationale;
            $bd = is_array($scoreOrBreakdown) ? $scoreOrBreakdown : null;
        } else {
            $typeA = (string) $typeAOrIdA;
            $idA = (int) $idAOrIdB;
            $typeB = (string) $typeBOrScore;
            $idB = (int) $idBOrRationale;
            $score = (float) $scoreOrBreakdown;
            $rat = (string) $rationale;
            $bd = $breakdown;
        }

        $pdo = Database::getConnection();
        if (!$pdo) {
            return false;
        }

        // Canonical normalization:
        // 1. Same type: enforce smaller ID in record A
        if ($typeA === $typeB) {
            if ($idA === $idB) {
                return false; // Prevent self-matching
            }
            $firstId = min($idA, $idB);
            $secondId = max($idA, $idB);
            $firstType = $typeA;
            $secondType = $typeB;
        } else {
            // 2. Different types: 'incident' is always first, 'sos' is always second
            if ($typeA === 'incident' && $typeB === 'sos') {
                $firstType = 'incident';
                $firstId = $idA;
                $secondType = 'sos';
                $secondId = $idB;
            } else {
                $firstType = 'incident';
                $firstId = $idB;
                $secondType = 'sos';
                $secondId = $idA;
            }
        }

        // Check if an existing candidate record exists for this pair
        $checkStmt = $pdo->prepare("
            SELECT id, status, similarity_score FROM incident_matches
            WHERE record_a_type = :t1 AND incident_a_id = :id1
              AND record_b_type = :t2 AND incident_b_id = :id2
            LIMIT 1
        ");
        $checkStmt->execute([
            ':t1' => $firstType,
            ':id1' => $firstId,
            ':t2' => $secondType,
            ':id2' => $secondId,
        ]);
        $existing = $checkStmt->fetch();

        $bdJson = $bd !== null ? json_encode($bd, JSON_UNESCAPED_UNICODE) : null;

        if ($existing) {
            // If already reviewed (dismissed or confirmed), do NOT revert to pending_review!
            if ($existing['status'] === 'dismissed' || $existing['status'] === 'confirmed_duplicate') {
                return true;
            }

            // Update pending candidate
            $upStmt = $pdo->prepare("
                UPDATE incident_matches
                SET similarity_score = :score,
                    rationale = :rationale,
                    match_breakdown = :breakdown
                WHERE id = :id
            ");
            return $upStmt->execute([
                ':score' => $score,
                ':rationale' => $rat,
                ':breakdown' => $bdJson,
                ':id' => $existing['id'],
            ]);
        }

        // Insert new candidate pair
        $insStmt = $pdo->prepare("
            INSERT INTO incident_matches (
                record_a_type, record_b_type, incident_a_id, incident_b_id,
                similarity_score, rationale, match_breakdown, status, created_at
            ) VALUES (
                :t1, :t2, :id1, :id2,
                :score, :rationale, :breakdown, 'pending_review', NOW()
            )
        ");

        return $insStmt->execute([
            ':t1' => $firstType,
            ':t2' => $secondType,
            ':id1' => $firstId,
            ':id2' => $secondId,
            ':score' => $score,
            ':rationale' => $rat,
            ':breakdown' => $bdJson,
        ]);
    }

    /**
     * Retrieve all pending candidate matches with details for both records.
     */
    public static function findPending(): array
    {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return [];
        }

        $stmt = $pdo->prepare("
            SELECT m.id, m.record_a_type, m.record_b_type, m.incident_a_id, m.incident_b_id,
                   m.similarity_score, m.rationale, m.match_breakdown, m.status, m.created_at,
                   -- Record A attributes
                   CASE WHEN m.record_a_type = 'sos' THEN sa.alert_code ELSE ia.incident_number END AS a_number,
                   CASE WHEN m.record_a_type = 'sos' THEN CONCAT('EMERGENCY SOS: ', COALESCE(sa.emergency_type, 'Emergency')) ELSE ia.title END AS a_title,
                   CASE WHEN m.record_a_type = 'sos' THEN COALESCE(sa.detected_category, sa.assigned_department, 'other') ELSE ia.category END AS a_category,
                   CASE WHEN m.record_a_type = 'sos' THEN sa.location_name ELSE ia.location_name END AS a_location,
                   CASE WHEN m.record_a_type = 'sos' THEN sa.room_number ELSE ia.room_number END AS a_room,
                   CASE WHEN m.record_a_type = 'sos' THEN COALESCE(sa.emergency_details, sa.emergency_type, 'SOS Alert') ELSE ia.description END AS a_description,
                   CASE WHEN m.record_a_type = 'sos' THEN sa.triggered_at ELSE ia.occurred_at END AS a_occurred_at,
                   CASE WHEN m.record_a_type = 'sos' THEN sa.status ELSE ia.status END AS a_status,
                   -- Record B attributes
                   CASE WHEN m.record_b_type = 'sos' THEN sb.alert_code ELSE ib.incident_number END AS b_number,
                   CASE WHEN m.record_b_type = 'sos' THEN CONCAT('EMERGENCY SOS: ', COALESCE(sb.emergency_type, 'Emergency')) ELSE ib.title END AS b_title,
                   CASE WHEN m.record_b_type = 'sos' THEN COALESCE(sb.detected_category, sb.assigned_department, 'other') ELSE ib.category END AS b_category,
                   CASE WHEN m.record_b_type = 'sos' THEN sb.location_name ELSE ib.location_name END AS b_location,
                   CASE WHEN m.record_b_type = 'sos' THEN sb.room_number ELSE ib.room_number END AS b_room,
                   CASE WHEN m.record_b_type = 'sos' THEN COALESCE(sb.emergency_details, sb.emergency_type, 'SOS Alert') ELSE ib.description END AS b_description,
                   CASE WHEN m.record_b_type = 'sos' THEN sb.triggered_at ELSE ib.occurred_at END AS b_occurred_at,
                   CASE WHEN m.record_b_type = 'sos' THEN sb.status ELSE ib.status END AS b_status
            FROM incident_matches m
            LEFT JOIN incidents ia ON (m.record_a_type = 'incident' AND m.incident_a_id = ia.id)
            LEFT JOIN sos_alerts sa ON (m.record_a_type = 'sos' AND m.incident_a_id = sa.id)
            LEFT JOIN incidents ib ON (m.record_b_type = 'incident' AND m.incident_b_id = ib.id)
            LEFT JOIN sos_alerts sb ON (m.record_b_type = 'sos' AND m.incident_b_id = sb.id)
            WHERE m.status = 'pending_review'
            ORDER BY m.similarity_score DESC, m.created_at DESC
        ");

        $stmt->execute();
        $rows = $stmt->fetchAll();

        // Normalize JSON breakdown and load attachments
        foreach ($rows as &$row) {
            $row['similarity_score'] = (float) $row['similarity_score'];
            if (!empty($row['match_breakdown']) && is_string($row['match_breakdown'])) {
                $row['match_breakdown'] = json_decode($row['match_breakdown'], true);
            }
            $row['a_attachments'] = ($row['record_a_type'] === 'sos')
                ? IncidentAttachment::findBySosAlertId((int) $row['incident_a_id'])
                : IncidentAttachment::findByIncidentId((int) $row['incident_a_id']);
            $row['b_attachments'] = ($row['record_b_type'] === 'sos')
                ? IncidentAttachment::findBySosAlertId((int) $row['incident_b_id'])
                : IncidentAttachment::findByIncidentId((int) $row['incident_b_id']);
        }

        return $rows;
    }

    /**
     * Find single candidate match record by ID.
     */
    public static function findById(int $id): ?array
    {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return null;
        }

        $stmt = $pdo->prepare("
            SELECT m.id, m.record_a_type, m.record_b_type, m.incident_a_id, m.incident_b_id,
                   m.similarity_score, m.rationale, m.match_breakdown, m.status, m.created_at,
                   m.reviewed_by_user_id, m.reviewed_at,
                   -- Record A attributes
                   CASE WHEN m.record_a_type = 'sos' THEN sa.alert_code ELSE ia.incident_number END AS a_number,
                   CASE WHEN m.record_a_type = 'sos' THEN CONCAT('EMERGENCY SOS: ', COALESCE(sa.emergency_type, 'Emergency')) ELSE ia.title END AS a_title,
                   CASE WHEN m.record_a_type = 'sos' THEN COALESCE(sa.detected_category, sa.assigned_department, 'other') ELSE ia.category END AS a_category,
                   CASE WHEN m.record_a_type = 'sos' THEN sa.location_name ELSE ia.location_name END AS a_location,
                   CASE WHEN m.record_a_type = 'sos' THEN sa.room_number ELSE ia.room_number END AS a_room,
                   CASE WHEN m.record_a_type = 'sos' THEN COALESCE(sa.emergency_details, sa.emergency_type, 'SOS Alert') ELSE ia.description END AS a_description,
                   CASE WHEN m.record_a_type = 'sos' THEN sa.triggered_at ELSE ia.occurred_at END AS a_occurred_at,
                   CASE WHEN m.record_a_type = 'sos' THEN sa.status ELSE ia.status END AS a_status,
                   -- Record B attributes
                   CASE WHEN m.record_b_type = 'sos' THEN sb.alert_code ELSE ib.incident_number END AS b_number,
                   CASE WHEN m.record_b_type = 'sos' THEN CONCAT('EMERGENCY SOS: ', COALESCE(sb.emergency_type, 'Emergency')) ELSE ib.title END AS b_title,
                   CASE WHEN m.record_b_type = 'sos' THEN COALESCE(sb.detected_category, sb.assigned_department, 'other') ELSE ib.category END AS b_category,
                   CASE WHEN m.record_b_type = 'sos' THEN sb.location_name ELSE ib.location_name END AS b_location,
                   CASE WHEN m.record_b_type = 'sos' THEN sb.room_number ELSE ib.room_number END AS b_room,
                   CASE WHEN m.record_b_type = 'sos' THEN COALESCE(sb.emergency_details, sb.emergency_type, 'SOS Alert') ELSE ib.description END AS b_description,
                   CASE WHEN m.record_b_type = 'sos' THEN sb.triggered_at ELSE ib.occurred_at END AS b_occurred_at,
                   CASE WHEN m.record_b_type = 'sos' THEN sb.status ELSE ib.status END AS b_status
            FROM incident_matches m
            LEFT JOIN incidents ia ON (m.record_a_type = 'incident' AND m.incident_a_id = ia.id)
            LEFT JOIN sos_alerts sa ON (m.record_a_type = 'sos' AND m.incident_a_id = sa.id)
            LEFT JOIN incidents ib ON (m.record_b_type = 'incident' AND m.incident_b_id = ib.id)
            LEFT JOIN sos_alerts sb ON (m.record_b_type = 'sos' AND m.incident_b_id = sb.id)
            WHERE m.id = :id
            LIMIT 1
        ");

        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $row['similarity_score'] = (float) $row['similarity_score'];
        if (!empty($row['match_breakdown']) && is_string($row['match_breakdown'])) {
            $row['match_breakdown'] = json_decode($row['match_breakdown'], true);
        }

        $row['a_attachments'] = ($row['record_a_type'] === 'sos')
            ? IncidentAttachment::findBySosAlertId((int) $row['incident_a_id'])
            : IncidentAttachment::findByIncidentId((int) $row['incident_a_id']);
        $row['b_attachments'] = ($row['record_b_type'] === 'sos')
            ? IncidentAttachment::findBySosAlertId((int) $row['incident_b_id'])
            : IncidentAttachment::findByIncidentId((int) $row['incident_b_id']);

        return $row;
    }

    /**
     * Review candidate match (merge vs dismiss).
     */
    public static function review(int $matchId, string $decision, int $reviewerUserId, ?string $notes = null): bool
    {
        $match = self::findById($matchId);
        if (!$match) {
            return false;
        }

        // Idempotency check: if already confirmed or dismissed, return true
        $normalizedDecision = ($decision === 'merge' || $decision === 'confirmed_duplicate') ? 'merge' : 'dismiss';

        if ($match['status'] === 'confirmed_duplicate' && $normalizedDecision === 'merge') {
            return true;
        }
        if ($match['status'] === 'dismissed' && $normalizedDecision === 'dismiss') {
            return true;
        }

        if ($normalizedDecision === 'dismiss') {
            return self::dismissCandidate($matchId, $reviewerUserId);
        }

        return self::mergeCandidate($match, $reviewerUserId, $notes);
    }

    /**
     * Dismiss a candidate match without altering original records.
     */
    public static function dismissCandidate(int $matchId, int $reviewerUserId): bool
    {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return false;
        }

        $stmt = $pdo->prepare("
            UPDATE incident_matches
            SET status = 'dismissed',
                reviewed_by_user_id = :reviewer_id,
                reviewed_at = NOW()
            WHERE id = :id
        ");

        $ok = $stmt->execute([
            ':reviewer_id' => $reviewerUserId,
            ':id' => $matchId,
        ]);

        if ($ok) {
            AuditLog::log($reviewerUserId, 'MATCH_DISMISSED', 'incident_match', $matchId, [
                'decision' => 'dismiss',
            ]);
        }

        return $ok;
    }

    /**
     * Execute safe, transactional merge of candidate records.
     * Preserves primary record, links secondary record, preserves audit trail,
     * reconciles notes, and commits only if all operations succeed.
     */
    public static function mergeCandidate(array $match, int $reviewerUserId, ?string $notes = null): bool
    {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return false;
        }

        $matchId = (int) $match['id'];
        $typeA = $match['record_a_type'] ?? 'incident';
        $typeB = $match['record_b_type'] ?? 'incident';
        $idA = (int) $match['incident_a_id'];
        $idB = (int) $match['incident_b_id'];

        $pdo->beginTransaction();

        try {
            $mergeNotes = trim((string) ($notes ?: 'Merged by Administrator after duplicate candidate verification.'));

            if ($typeA === 'incident' && $typeB === 'incident') {
                // Incident vs Incident merge:
                // Primary is Incident A; Secondary is Incident B.
                $incA = Incident::findById($idA);
                $incB = Incident::findById($idB);

                if (!$incA || !$incB) {
                    throw new \RuntimeException('One or both incident records could not be found.');
                }

                // 1. Mark Incident B as closed and linked to Incident A
                $stmtB = $pdo->prepare("
                    UPDATE incidents
                    SET status = 'closed',
                        merged_into_incident_id = :primary_id,
                        merged_at = NOW(),
                        merged_by_user_id = :user_id,
                        merge_notes = :notes
                    WHERE id = :secondary_id
                ");
                $stmtB->execute([
                    ':primary_id' => $idA,
                    ':user_id' => $reviewerUserId,
                    ':notes' => $mergeNotes,
                    ':secondary_id' => $idB,
                ]);

                // 2. Add chronological update record to Incident B
                IncidentUpdate::create(
                    $idB,
                    $reviewerUserId,
                    $incB['status'],
                    'closed',
                    "Closed as duplicate report — merged into primary incident #{$incA['incident_number']}. {$mergeNotes}",
                    true
                );

                // 3. Reconcile details on Incident A: add chronological note referencing Incident B
                IncidentUpdate::create(
                    $idA,
                    $reviewerUserId,
                    $incA['status'],
                    $incA['status'],
                    "Duplicate incident #{$incB['incident_number']} linked and merged into this case. Reporter context: '{$incB['description']}'. Notes: {$mergeNotes}",
                    true
                );

                // 4. Reconcile room number on primary if missing
                if (empty($incA['room_number']) && !empty($incB['room_number'])) {
                    $pdo->prepare("UPDATE incidents SET room_number = :rm WHERE id = :id")
                        ->execute([':rm' => $incB['room_number'], ':id' => $idA]);
                }

            } elseif ($typeA === 'incident' && $typeB === 'sos') {
                // Incident vs SOS merge:
                // Primary is Incident A; SOS B is linked to Incident A.
                $incA = Incident::findById($idA);
                $sosB = SosAlert::findById($idB);

                if (!$incA || !$sosB) {
                    throw new \RuntimeException('Incident or SOS record could not be found.');
                }

                // 1. Link SOS to Incident (DO NOT CANCEL OR SUPPRESS SOS — KEEP DISPATCH ACTIVE)
                $stmtSos = $pdo->prepare("
                    UPDATE sos_alerts
                    SET incident_id = :inc_id,
                        merged_into_incident_id = :merged_inc_id,
                        merged_at = NOW(),
                        merged_by_user_id = :user_id,
                        merge_notes = :notes
                    WHERE id = :sos_id
                ");
                $stmtSos->execute([
                    ':inc_id' => $idA,
                    ':merged_inc_id' => $idA,
                    ':user_id' => $reviewerUserId,
                    ':notes' => $mergeNotes,
                    ':sos_id' => $idB,
                ]);

                // 2. Add chronological update record to Incident A
                $sosCode = $sosB['alert_code'] ?? $sosB['alertCode'] ?? ('#' . $idB);
                $sosType = $sosB['emergency_type'] ?? $sosB['emergencyType'] ?? 'Emergency';
                IncidentUpdate::create(
                    $idA,
                    $reviewerUserId,
                    $incA['status'],
                    $incA['status'],
                    "Emergency SOS alert #{$sosCode} ({$sosType}) linked to this incident. Active field dispatch maintained. Notes: {$mergeNotes}",
                    true
                );

            } elseif ($typeA === 'sos' && $typeB === 'sos') {
                // SOS vs SOS merge:
                // Primary is SOS A; Secondary is SOS B.
                $sosA = SosAlert::findById($idA);
                $sosB = SosAlert::findById($idB);

                if (!$sosA || !$sosB) {
                    throw new \RuntimeException('One or both SOS alerts could not be found.');
                }

                // Link SOS B to SOS A (preserve active safety dispatch)
                $stmtSos = $pdo->prepare("
                    UPDATE sos_alerts
                    SET merged_into_sos_id = :primary_sos_id,
                        merged_at = NOW(),
                        merged_by_user_id = :user_id,
                        merge_notes = :notes
                    WHERE id = :secondary_sos_id
                ");
                $stmtSos->execute([
                    ':primary_sos_id' => $idA,
                    ':user_id' => $reviewerUserId,
                    ':notes' => $mergeNotes,
                    ':secondary_sos_id' => $idB,
                ]);
            }

            // Update candidate status to confirmed_duplicate
            $stmtMatch = $pdo->prepare("
                UPDATE incident_matches
                SET status = 'confirmed_duplicate',
                    reviewed_by_user_id = :user_id,
                    reviewed_at = NOW()
                WHERE id = :id
            ");
            $stmtMatch->execute([
                ':user_id' => $reviewerUserId,
                ':id' => $matchId,
            ]);

            // Audit Log
            AuditLog::log($reviewerUserId, 'RECORDS_MERGED', 'incident_match', $matchId, [
                'record_a_type' => $typeA,
                'record_a_id' => $idA,
                'record_b_type' => $typeB,
                'record_b_id' => $idB,
                'merge_notes' => $mergeNotes,
            ]);

            $pdo->commit();
            return true;

        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('[CampusSOS Merge Error]: ' . $e->getMessage());
            return false;
        }
    }
}
