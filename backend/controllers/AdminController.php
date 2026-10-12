<?php
declare(strict_types=1);

namespace CampusSOS\Controllers;

use CampusSOS\Config\Database;
use CampusSOS\Models\IncidentMatch;
use CampusSOS\Models\AuditLog;
use CampusSOS\Services\AuthService;
use CampusSOS\Services\ResponseService;
use CampusSOS\Middleware\RoleMiddleware;

class AdminController
{
    /**
     * GET /api/admin/metrics
     * Real database counts by status and category for mission control dashboard.
     */
    public function dashboardMetrics(): void
    {
        RoleMiddleware::requireAdmin();

        $pdo = Database::getConnection();

        $metrics = [
            'activeSosCount' => 0,
            'openIncidentsCount' => 0,
            'totalRespondersCount' => 0,
            'resolvedTodayCount' => 0,
            'unassignedCount' => 0,
            'byCategory' => [],
            'byStatus' => [],
        ];

        if ($pdo) {
            // 1. Active SOS
            $stmt = $pdo->query("SELECT COUNT(*) FROM sos_alerts WHERE status IN ('triggered', 'acknowledged', 'dispatched')");
            $metrics['activeSosCount'] = (int) $stmt->fetchColumn();

            // 2. Open Incidents
            $stmt = $pdo->query("SELECT COUNT(*) FROM incidents WHERE status IN ('reported', 'under_review', 'dispatched', 'in_progress')");
            $metrics['openIncidentsCount'] = (int) $stmt->fetchColumn();

            // 3. Responders Count
            $stmt = $pdo->query("SELECT COUNT(*) FROM responders");
            $metrics['totalRespondersCount'] = (int) $stmt->fetchColumn();

            // 4. Resolved Today
            $stmt = $pdo->query("SELECT COUNT(*) FROM incidents WHERE status = 'resolved' AND DATE(updated_at) = CURDATE()");
            $metrics['resolvedTodayCount'] = (int) $stmt->fetchColumn();

            // 5. Unassigned Incidents
            $stmt = $pdo->query("SELECT COUNT(*) FROM incidents WHERE id NOT IN (SELECT incident_id FROM incident_assignments) AND status NOT IN ('resolved', 'closed', 'rejected')");
            $metrics['unassignedCount'] = (int) $stmt->fetchColumn();

            // 6. Counts by category
            $stmt = $pdo->query("SELECT category, COUNT(*) as count FROM incidents GROUP BY category");
            while ($row = $stmt->fetch()) {
                $metrics['byCategory'][$row['category']] = (int) $row['count'];
            }

            // 7. Counts by status
            $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM incidents GROUP BY status");
            while ($row = $stmt->fetch()) {
                $metrics['byStatus'][$row['status']] = (int) $row['count'];
            }
        }

        ResponseService::json($metrics, 200, 'Operational metrics retrieved.');
    }

    /**
     * GET /api/admin/audit-logs
     * Retrieve recent system audit trail.
     */
    public function auditLogs(): void
    {
        RoleMiddleware::requireAdmin();

        $pdo = Database::getConnection();
        if (!$pdo) {
            ResponseService::json([], 200);
        }

        $stmt = $pdo->query("
            SELECT al.id, al.actor_user_id, al.action, al.resource_type, al.resource_id,
                   al.ip_address, al.details, al.created_at,
                   u.full_name as actor_name, u.role as actor_role
            FROM audit_logs al
            LEFT JOIN users u ON al.actor_user_id = u.id
            ORDER BY al.created_at DESC
            LIMIT 50
        ");

        $logs = $stmt->fetchAll();
        ResponseService::json($logs, 200);
    }

    /**
     * GET /api/admin/matches
     * Retrieve pending candidate incident duplicates for human review.
     */
    public function matches(): void
    {
        RoleMiddleware::requireAdmin();
        $matches = IncidentMatch::findPending();
        ResponseService::json($matches, 200);
    }

    /**
     * GET /api/admin/matches/{id}
     * Retrieve single candidate match details for human review.
     */
    public function showMatch(int $id): void
    {
        RoleMiddleware::requireAdmin();
        $match = IncidentMatch::findById($id);
        if (!$match) {
            ResponseService::error('Candidate match not found.', 404);
        }
        ResponseService::json($match, 200);
    }

    /**
     * POST /api/admin/matches/{id}/merge
     * Confirm and execute safe transactional merge of duplicate records.
     */
    public function mergeMatch(int $id): void
    {
        RoleMiddleware::requireAdmin();
        $user = AuthService::getCurrentUser();

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $notes = trim((string) ($input['notes'] ?? ($input['mergeNotes'] ?? '')));

        $success = IncidentMatch::review($id, 'merge', (int) $user['id'], $notes);
        if (!$success) {
            ResponseService::error('Failed to execute record merge. Records may be ineligible or already merged.', 400);
        }

        ResponseService::json(['matchId' => $id, 'decision' => 'merge'], 200, 'Duplicate candidate confirmed and records merged successfully.');
    }

    /**
     * POST /api/admin/matches/{id}/dismiss
     * Dismiss duplicate candidate match without altering original records.
     */
    public function dismissMatch(int $id): void
    {
        RoleMiddleware::requireAdmin();
        $user = AuthService::getCurrentUser();

        $success = IncidentMatch::review($id, 'dismiss', (int) $user['id']);
        if (!$success) {
            ResponseService::error('Failed to dismiss candidate match.', 400);
        }

        ResponseService::json(['matchId' => $id, 'decision' => 'dismiss'], 200, 'Duplicate candidate dismissed.');
    }

    /**
     * POST /api/admin/matches/{id}/review
     * Review candidate match (merge vs dismiss) - backward-compatible wrapper.
     */
    public function reviewMatch(int $id): void
    {
        RoleMiddleware::requireAdmin();
        $user = AuthService::getCurrentUser();

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $decision = trim((string) ($input['decision'] ?? 'dismiss')); // 'merge' or 'dismiss'
        $notes = trim((string) ($input['notes'] ?? ($input['mergeNotes'] ?? '')));

        $reviewed = IncidentMatch::review($id, $decision, (int) $user['id'], $notes);
        if (!$reviewed) {
            ResponseService::error('Failed to update match decision. Please verify candidate eligibility.', 400);
        }

        ResponseService::json(['matchId' => $id, 'decision' => $decision], 200, "Candidate match {$decision}ed.");
    }
}
