<?php
declare(strict_types=1);

namespace CampusSOS\Controllers;

use CampusSOS\Models\Responder;
use CampusSOS\Models\IncidentAssignment;
use CampusSOS\Models\AuditLog;
use CampusSOS\Services\AuthService;
use CampusSOS\Services\ResponseService;
use CampusSOS\Middleware\AuthMiddleware;
use CampusSOS\Config\Database;

class ResponderController
{
    /**
     * GET /api/responders
     * Directory of responders.
     */
    public function index(): void
    {
        AuthMiddleware::authenticate();
        $responders = Responder::findAll();
        ResponseService::json($responders, 200, 'Responder directory retrieved.');
    }

    /**
     * GET /api/responders/category/{category}
     */
    public function byCategory(string $category): void
    {
        AuthMiddleware::authenticate();
        $responders = Responder::findByCategory($category);
        ResponseService::json($responders, 200, "Responders for category '{$category}' retrieved.");
    }

    /**
     * GET /api/responders/my-assignments
     * Retrieve incidents assigned to the currently authenticated responder.
     */
    public function myAssignments(): void
    {
        AuthMiddleware::authenticate();
        $user = AuthService::getCurrentUser();
        if (!$user || $user['role'] !== 'responder') {
            ResponseService::error('Only responders can access assigned incidents.', 403);
        }

        $profile = $user['responderProfile'] ?? null;
        if (!$profile) {
            ResponseService::error('No responder profile linked to this user.', 404);
        }

        $activeOnly = !isset($_GET['all']) && (!isset($_GET['include_resolved']) || $_GET['include_resolved'] !== 'true');
        $assignments = IncidentAssignment::findByResponder((int) $profile['id'], $activeOnly);
        ResponseService::json($assignments, 200);
    }

    /**
     * PUT /api/responders/status
     * Update on-duty availability status ('available', 'busy', 'offline').
     */
    public function updateStatus(): void
    {
        AuthMiddleware::authenticate();
        $user = AuthService::getCurrentUser();
        if (!$user || $user['role'] !== 'responder') {
            ResponseService::error('Only responders can update duty status.', 403);
        }

        $profile = $user['responderProfile'] ?? null;
        if (!$profile) {
            ResponseService::error('No responder profile linked to this user.', 404);
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $status = trim($input['status'] ?? '');
        $currentLocation = trim($input['currentLocation'] ?? ($profile['current_location'] ?? ''));

        if (!in_array($status, ['available', 'busy', 'offline'], true)) {
            ResponseService::error("Invalid status. Must be 'available', 'busy', or 'offline'.", 422);
        }

        $pdo = Database::getConnection();
        if (!$pdo) {
            ResponseService::error('Database unavailable.', 500);
        }

        $stmt = $pdo->prepare("
            UPDATE responders
            SET status = :status, current_location = :loc, updated_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            ':status' => $status,
            ':loc' => $currentLocation,
            ':id' => $profile['id'],
        ]);

        AuditLog::log((int) $user['id'], 'RESPONDER_STATUS_CHANGED', 'responder', (int) $profile['id'], [
            'status' => $status,
            'location' => $currentLocation,
        ]);

        ResponseService::json([
            'id' => $profile['id'],
            'status' => $status,
            'currentLocation' => $currentLocation,
        ], 200, 'Responder status updated.');
    }
}
