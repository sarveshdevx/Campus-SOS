<?php
declare(strict_types=1);

namespace CampusSOS\Controllers;

use CampusSOS\Models\SosAlert;
use CampusSOS\Models\Incident;
use CampusSOS\Models\IncidentUpdate;
use CampusSOS\Models\IncidentAssignment;
use CampusSOS\Models\IncidentAttachment;
use CampusSOS\Models\AuditLog;
use CampusSOS\Models\Notification;
use CampusSOS\Services\AuthService;
use CampusSOS\Services\SosDispatchService;
use CampusSOS\Services\ResponseService;
use CampusSOS\Middleware\RoleMiddleware;

class SosController
{
    /**
     * POST /api/sos/trigger
     * Instant deterministic emergency SOS dispatch with optional visual evidence.
     */
    public function trigger(): void
    {
        $input = !empty($_POST) ? $_POST : (json_decode(file_get_contents('php://input'), true) ?? []);
        $user = AuthService::getCurrentUser();

        try {
            $alert = SosDispatchService::triggerSos($input, $user['id'] ?? null);
            $alertId = (int) $alert['id'];
            $attachments = [];

            // Handle optional visual evidence attachments (max 2)
            // 1. Multipart/form-data files via $_FILES
            if (!empty($_FILES)) {
                $fileList = IncidentAttachment::normalizeFiles($_FILES);
                foreach (array_slice($fileList, 0, IncidentAttachment::MAX_ATTACHMENTS_PER_RECORD) as $f) {
                    try {
                        $att = IncidentAttachment::processUploadedFile($f, null, $alertId, $user['id'] ?? null);
                        if ($att) {
                            $attachments[] = $att;
                        }
                    } catch (\Throwable $e) {
                        error_log('[CampusSOS SOS Attachment Warning]: ' . $e->getMessage());
                    }
                }
            }

            // 2. Base64 / data-uri images via JSON payload
            if (empty($attachments) && !empty($input['images']) && is_array($input['images'])) {
                foreach (array_slice($input['images'], 0, IncidentAttachment::MAX_ATTACHMENTS_PER_RECORD) as $idx => $img) {
                    try {
                        $dataUri = is_string($img) ? $img : ($img['data'] ?? ($img['dataUrl'] ?? ''));
                        $name = is_array($img) ? ($img['name'] ?? "sos_evidence_{$idx}.jpg") : "sos_evidence_{$idx}.jpg";
                        if (!empty($dataUri)) {
                            $att = IncidentAttachment::processBase64Image($dataUri, $name, null, $alertId, $user['id'] ?? null);
                            if ($att) {
                                $attachments[] = $att;
                            }
                        }
                    } catch (\Throwable $e) {
                        error_log('[CampusSOS SOS Base64 Warning]: ' . $e->getMessage());
                    }
                }
            }

            $alert['attachments'] = !empty($attachments) ? $attachments : IncidentAttachment::findBySosAlertId($alertId);
            ResponseService::json($alert, 201, 'Emergency SOS successfully dispatched.');
        } catch (\InvalidArgumentException $e) {
            ResponseService::error($e->getMessage(), 422);
        }
    }

    /**
     * GET /api/sos/active
     * Retrieve active SOS alerts scoped by user role and category authorization.
     */
    public function active(): void
    {
        $user = AuthService::getCurrentUser();
        $alerts = SosAlert::findAllActive($user);
        ResponseService::json($alerts, 200);
    }

    /**
     * GET /api/sos/{id}
     * Retrieve single SOS alert details if authorized.
     */
    public function show(int $id): void
    {
        $user = AuthService::getCurrentUser();
        if (!$user) {
            ResponseService::error('Authentication required to view SOS alerts.', 401);
        }

        $alert = SosAlert::findById($id);
        if (!$alert) {
            ResponseService::error('SOS alert not found.', 404);
        }

        // Admin can inspect any SOS alert
        if ($user['role'] === 'admin') {
            ResponseService::json($alert, 200);
            return;
        }

        // Responders can only inspect alerts routed or assigned to them
        if ($user['role'] === 'responder') {
            $responder = $user['responderProfile'] ?? null;
            if (!$responder || !\CampusSOS\Services\SosRoutingService::isResponderAuthorized($responder, $alert)) {
                ResponseService::error('Access restricted to designated responders for this emergency category.', 403);
            }
            ResponseService::json($alert, 200);
            return;
        }

        // Students can only view their own triggered alert
        if ($user['role'] === 'student' && (int) ($alert['user_id'] ?? 0) === (int) $user['id']) {
            ResponseService::json($alert, 200);
            return;
        }

        ResponseService::error('Access forbidden.', 403);
    }

    /**
     * POST /api/sos/{id}/acknowledge
     * Acknowledge active emergency SOS.
     */
    public function acknowledge(int $id): void
    {
        $user = AuthService::getCurrentUser();
        if (!$user || !in_array($user['role'], ['admin', 'responder'], true)) {
            ResponseService::error('Only authorized personnel can acknowledge SOS alerts.', 403);
        }

        $alert = SosAlert::findById($id);
        if (!$alert) {
            ResponseService::error('SOS alert not found.', 404);
        }

        if ($user['role'] === 'responder') {
            $responder = $user['responderProfile'] ?? null;
            if (!$responder || !\CampusSOS\Services\SosRoutingService::isResponderAuthorized($responder, $alert)) {
                ResponseService::error('Access restricted to designated responders for this emergency category.', 403);
            }
        }

        $success = SosAlert::acknowledge($id);
        if (!$success) {
            ResponseService::error('Failed to acknowledge alert or alert already acknowledged.', 400);
        }

        AuditLog::log($user['id'] ?? null, 'SOS_ACKNOWLEDGED', 'sos_alert', $id);

        ResponseService::json(['id' => $id, 'status' => 'acknowledged'], 200, 'SOS acknowledged by dispatcher.');
    }

    /**
     * POST /api/sos/{id}/assign
     * Assign SOS alert to a specific responder team (Admin manual triage).
     */
    public function assign(int $id): void
    {
        RoleMiddleware::requireAdmin();
        $user = AuthService::getCurrentUser();

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $responderId = (int) ($input['responderId'] ?? ($input['responder_id'] ?? 0));
        $notes = trim($input['notes'] ?? 'Emergency unit assigned by Administrator triage.');

        if ($responderId <= 0) {
            ResponseService::error('Valid responder ID is required.', 422);
        }

        $success = SosAlert::assignToResponder($id, $responderId, $notes);
        if (!$success) {
            ResponseService::error('Failed to assign responder to SOS alert.', 500);
        }

        // Notify responder
        $pdo = \CampusSOS\Config\Database::getConnection();
        if ($pdo) {
            $stmt = $pdo->prepare("SELECT user_id FROM responders WHERE id = :r_id");
            $stmt->execute([':r_id' => $responderId]);
            $respUserId = $stmt->fetchColumn();
            if ($respUserId) {
                Notification::create(
                    (int) $respUserId,
                    'sos_dispatch',
                    "URGENT: Emergency SOS assigned to your unit by Administrator.",
                    "/responders"
                );
            }
        }

        AuditLog::log((int) $user['id'], 'SOS_ASSIGNED', 'sos_alert', $id, [
            'responder_id' => $responderId,
            'notes' => $notes,
            'manual_triage' => true,
        ]);

        ResponseService::json(['id' => $id, 'responderId' => $responderId, 'status' => 'dispatched'], 200, 'SOS successfully assigned to designated responder.');
    }

    /**
     * POST /api/sos/{id}/resolve
     * Resolve emergency alert with notes.
     */
    public function resolve(int $id): void
    {
        $user = AuthService::getCurrentUser();
        if (!$user || !in_array($user['role'], ['admin', 'responder'], true)) {
            ResponseService::error('Only authorized personnel can resolve SOS alerts.', 403);
        }

        $alert = SosAlert::findById($id);
        if (!$alert) {
            ResponseService::error('SOS alert not found.', 404);
        }

        if ($user['role'] === 'responder') {
            $responder = $user['responderProfile'] ?? null;
            if (!$responder || !\CampusSOS\Services\SosRoutingService::isResponderAuthorized($responder, $alert)) {
                ResponseService::error('Access restricted to designated responders for this emergency category.', 403);
            }
        }

        if ($alert['status'] === 'resolved') {
            ResponseService::error('SOS alert has already been resolved.', 409);
        }

        if ($alert['status'] === 'cancelled') {
            ResponseService::error('Cannot resolve a cancelled SOS alert.', 400);
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $notes = trim($input['resolutionNotes'] ?? ($input['notes'] ?? 'Resolved by on-duty campus safety team.'));

        $success = SosAlert::resolve($id, $notes, (int) $user['id']);
        if (!$success) {
            ResponseService::error('Failed to resolve alert.', 500);
        }

        // Advance linked incident if present and active
        if (!empty($alert['incident_id'])) {
            $linkedIncidentId = (int) $alert['incident_id'];
            $linked = Incident::findById($linkedIncidentId);
            if ($linked && $linked['status'] !== 'resolved') {
                Incident::updateStatus($linkedIncidentId, 'resolved');
                IncidentUpdate::create(
                    $linkedIncidentId,
                    (int) $user['id'],
                    $linked['status'],
                    'resolved',
                    "Auto-resolved upon emergency SOS #{$id} resolution: {$notes}",
                    false
                );
                IncidentAssignment::completeAllForIncident($linkedIncidentId, "Resolved via SOS #{$id}");
                AuditLog::log((int) $user['id'], 'STATUS_UPDATED', 'incident', $linkedIncidentId, [
                    'prev_status' => $linked['status'],
                    'new_status' => 'resolved',
                    'note' => "Resolved via SOS #{$id}",
                ]);
            }
        }

        AuditLog::log((int) $user['id'], 'SOS_RESOLVED', 'sos_alert', $id, [
            'notes' => $notes,
            'incident_id' => $alert['incident_id'] ?? null,
        ]);

        ResponseService::json(['id' => $id, 'status' => 'resolved'], 200, 'SOS marked resolved.');
    }

    /**
     * GET /api/sos/contacts
     */
    public function contacts(): void
    {
        $contacts = [
            ['name' => 'Campus Health Clinic Emergency', 'phone' => '+91 (0) 11-2345-6789', 'type' => 'medical'],
            ['name' => 'Campus Security Main Gate', 'phone' => '+91 (0) 11-2345-6780', 'type' => 'security'],
            ['name' => 'Fire Station & Disaster Response', 'phone' => '101', 'type' => 'fire'],
            ['name' => 'National Anti-Ragging Helpline', 'phone' => '1800-180-5522', 'type' => 'anti_ragging'],
            ['name' => 'Internal Complaints Committee (ICC)', 'phone' => '+91 98765 43214', 'type' => 'confidential'],
        ];

        ResponseService::json($contacts, 200);
    }
}
