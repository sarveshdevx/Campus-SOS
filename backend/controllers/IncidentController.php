<?php
declare(strict_types=1);

namespace CampusSOS\Controllers;

use CampusSOS\Models\Incident;
use CampusSOS\Models\IncidentAssignment;
use CampusSOS\Models\IncidentUpdate;
use CampusSOS\Models\IncidentAttachment;
use CampusSOS\Models\Notification;
use CampusSOS\Models\AuditLog;
use CampusSOS\Models\Responder;
use CampusSOS\Services\AuthService;
use CampusSOS\Services\ResponseService;
use CampusSOS\Services\IncidentMatcherService;
use CampusSOS\Services\GeminiClassificationService;
use CampusSOS\Services\LocationService;
use CampusSOS\Middleware\AuthMiddleware;
use CampusSOS\Middleware\RoleMiddleware;

class IncidentController
{
    /**
     * GET /api/incidents
     * Returns incidents permitted for current user's role and category.
     */
    public function index(): void
    {
        $user = AuthService::getCurrentUser();

        $filters = [
            'category' => $_GET['category'] ?? null,
            'status' => $_GET['status'] ?? null,
            'unassigned_only' => !empty($_GET['unassigned_only']),
            'active_only' => !empty($_GET['active_only']),
        ];

        $incidents = Incident::findAllForUser($user, $filters);

        $formatted = array_map(function ($row) {
            $isAnon = (bool) $row['is_anonymous'];
            return [
                'id' => (int) $row['id'],
                'incidentNumber' => $row['incident_number'],
                'category' => $row['category'],
                'title' => $row['title'],
                'description' => $row['description'],
                'locationName' => $row['location_name'],
                'roomNumber' => $row['room_number'] ?? null,
                'status' => $row['status'],
                'priority' => $row['severity'] ?? 'medium',
                'severity' => $row['severity'] ?? 'medium',
                'isAnonymous' => $isAnon,
                'isConfidential' => (bool) $row['is_confidential'],
                'privacyLevel' => $row['privacy_level'],
                'reporterName' => $isAnon ? 'Anonymous' : ($row['reporter_name'] ?? null),
                'reporterStudentId' => $isAnon ? null : ($row['reporter_student_id'] ?? null),
                'reporterEmail' => $isAnon ? null : ($row['reporter_email'] ?? null),
                'reporterPhone' => $isAnon ? null : ($row['reporter_phone'] ?? null),
                'aiClassified' => (bool) ($row['ai_classified'] ?? false),
                'aiConfidence' => $row['ai_confidence'] !== null ? (float) $row['ai_confidence'] : null,
                'occurredAt' => $row['occurred_at'],
                'createdAt' => $row['created_at'],
                'updatedAt' => $row['updated_at'],
                'attachments' => $row['attachments'] ?? IncidentAttachment::findByIncidentId((int) $row['id']),
            ];
        }, $incidents);

        ResponseService::json($formatted, 200);
    }

    /**
     * GET /api/incidents/{id}
     * Returns full incident details with assignments and updates if permitted.
     */
    public function show(int $id): void
    {
        $incident = Incident::findById($id);
        if (!$incident) {
            ResponseService::error('Incident not found.', 404);
        }

        $user = AuthService::getCurrentUser();

        // Check privacy access:
        if ($incident['privacy_level'] === 'confidential') {
            if (!$user) {
                ResponseService::error('Authentication required to view confidential reports.', 401);
            }
            if ($user['role'] === 'student' && (int) ($incident['reporter_user_id'] ?? 0) !== (int) $user['id']) {
                ResponseService::error('Access to this confidential report is restricted.', 403);
            }
            if ($user['role'] === 'responder') {
                $category = $user['responderProfile']['category'] ?? '';
                if ($incident['category'] !== $category) {
                    ResponseService::error('Access restricted to assigned confidential responders.', 403);
                }
            }
        }

        $includeInternal = ($user && in_array($user['role'], ['admin', 'responder'], true));
        $updates = IncidentUpdate::findByIncident($id, $includeInternal);
        $assignments = IncidentAssignment::findByIncident($id);

        $isAnonymous = !empty($incident['is_anonymous']);

        $formatted = [
            'id' => (int) $incident['id'],
            'incidentNumber' => $incident['incident_number'],
            'incident_number' => $incident['incident_number'],
            'category' => $incident['category'],
            'title' => $incident['title'],
            'description' => $incident['description'],
            'locationName' => $incident['location_name'],
            'location_name' => $incident['location_name'],
            'roomNumber' => $incident['room_number'] ?? null,
            'room_number' => $incident['room_number'] ?? null,
            'coordinatesLat' => $incident['coordinates_lat'] !== null ? (float) $incident['coordinates_lat'] : null,
            'coordinatesLng' => $incident['coordinates_lng'] !== null ? (float) $incident['coordinates_lng'] : null,
            'occurredAt' => $incident['occurred_at'],
            'occurred_at' => $incident['occurred_at'],
            'isAnonymous' => $isAnonymous,
            'is_anonymous' => $isAnonymous ? 1 : 0,
            'isConfidential' => (bool) $incident['is_confidential'],
            'is_confidential' => (bool) $incident['is_confidential'],
            'privacyLevel' => $incident['privacy_level'],
            'privacy_level' => $incident['privacy_level'],
            'status' => $incident['status'],
            'severity' => $incident['severity'],
            'priority' => $incident['severity'],
            'aiClassified' => (bool) ($incident['ai_classified'] ?? false),
            'aiConfidence' => $incident['ai_confidence'] !== null ? (float) $incident['ai_confidence'] : null,
            'createdAt' => $incident['created_at'],
            'created_at' => $incident['created_at'],
            'updatedAt' => $incident['updated_at'],
            'updated_at' => $incident['updated_at'],
            'reporterUserId' => $isAnonymous ? null : ($incident['reporter_user_id'] ? (int) $incident['reporter_user_id'] : null),
            'reporter_user_id' => $isAnonymous ? null : ($incident['reporter_user_id'] ? (int) $incident['reporter_user_id'] : null),
            'reporterName' => $isAnonymous ? 'Anonymous' : ($incident['reporter_name'] ?? null),
            'reporter_name' => $isAnonymous ? 'Anonymous' : ($incident['reporter_name'] ?? null),
            'reporterStudentId' => $isAnonymous ? null : ($incident['reporter_student_id'] ?? null),
            'reporter_student_id' => $isAnonymous ? null : ($incident['reporter_student_id'] ?? null),
            'reporterEmail' => $isAnonymous ? null : ($incident['reporter_email'] ?? null),
            'reporter_email' => $isAnonymous ? null : ($incident['reporter_email'] ?? null),
            'reporterPhone' => $isAnonymous ? null : ($incident['reporter_phone'] ?? null),
            'reporter_phone' => $isAnonymous ? null : ($incident['reporter_phone'] ?? null),
            'updates' => array_map(function($u) use ($isAnonymous, $incident) {
                if ($isAnonymous) {
                    $u['actor_name'] = ($u['actor_user_id'] == ($incident['reporter_user_id'] ?? null) || empty($u['actor_name'])) ? 'Anonymous Student' : $u['actor_name'];
                    $u['actor_user_id'] = null;
                }
                return $u;
            }, $updates),
            'assignments' => $assignments,
            'attachments' => IncidentAttachment::findByIncidentId($id),
        ];

        ResponseService::json($formatted, 200);
    }

    /**
     * POST /api/incidents
     * Creates new incident report with candidate duplicate evaluation.
     */
    public function create(): void
    {
        $input = !empty($_POST) ? $_POST : (json_decode(file_get_contents('php://input'), true) ?? []);

        $title = trim($input['title'] ?? '');
        $description = trim($input['description'] ?? '');
        $category = trim($input['category'] ?? 'other');
        $rawLocation = trim($input['locationName'] ?? '');
        $rawRoom = isset($input['roomNumber']) ? trim((string) $input['roomNumber']) : null;

        if (empty($title) || empty($description)) {
            ResponseService::error('Title and description are required fields.', 422);
        }

        if (empty($rawLocation)) {
            ResponseService::error('Location landmark is a required field.', 422);
        }

        $locValidation = LocationService::validateAndFormat($rawLocation, $rawRoom);
        if (!$locValidation['valid']) {
            ResponseService::error($locValidation['error'], 422);
        }

        $locationName = $locValidation['formattedLocation'];
        $roomNumber = $locValidation['roomNumber'];

        $user = AuthService::getCurrentUser();
        $isAnonymous = !empty($input['isAnonymous']);
        $privacyLevel = $input['privacyLevel'] ?? 'standard';
        $isConfidential = ($privacyLevel === 'confidential');
        $incidentNumber = 'INC-' . date('Y') . '-' . str_pad((string) rand(1000, 9999), 4, '0', STR_PAD_LEFT);

        // Run classification service (Gemini if enabled & privacy-safe; otherwise deterministic heuristic)
        $classification = GeminiClassificationService::classifyIncident(
            $title,
            $description,
            $category,
            $privacyLevel
        );

        // If user submitted category is other and classifier suggested a specific category, suggest/adopt it
        if ($category === 'other' && $classification['suggestedCategory'] !== 'other') {
            $category = $classification['suggestedCategory'];
        }

        $rawSeverity = $input['severity'] ?? ($input['priority'] ?? $classification['urgency']);
        $severityMap = [
            'low' => 'low',
            'medium' => 'medium',
            'high' => 'high',
            'critical' => 'critical',
            'urgent' => 'high',
            'emergency' => 'critical',
        ];
        $severity = $severityMap[strtolower((string) $rawSeverity)] ?? 'medium';
        $aiClassified = ($classification['source'] === 'gemini');
        $aiConfidence = $classification['confidence'];

        $reporterUserId = null;
        if (!$isAnonymous) {
            if ($user && isset($user['id'])) {
                $reporterUserId = (int) $user['id'];
            } elseif (!empty($input['reporterUserId'])) {
                $reporterUserId = (int) $input['reporterUserId'];
            }
        }

        $incidentData = [
            'incident_number' => $incidentNumber,
            'reporter_user_id' => $reporterUserId,
            'is_anonymous' => $isAnonymous,
            'is_confidential' => $isConfidential,
            'privacy_level' => $privacyLevel,
            'category' => $category,
            'title' => $title,
            'description' => $description,
            'location_name' => $locationName,
            'room_number' => $roomNumber,
            'coordinates_lat' => $input['coordinatesLat'] ?? null,
            'coordinates_lng' => $input['coordinatesLng'] ?? null,
            'occurred_at' => $input['occurredAt'] ?? date('Y-m-d H:i:s'),
            'status' => 'reported',
            'severity' => $severity,
            'ai_classified' => $aiClassified,
            'ai_confidence' => $aiConfidence,
        ];

        $incidentId = Incident::create($incidentData);
        if (!$incidentId) {
            $incidentId = rand(1000, 9999);
        }

        // Initial creation history entry (strictly omit actor if anonymous)
        IncidentUpdate::create(
            $incidentId,
            $isAnonymous ? null : ($user['id'] ?? $reporterUserId),
            null,
            'reported',
            $isAnonymous ? 'Incident reported anonymously.' : 'Incident reported by campus member.',
            false
        );

        AuditLog::log($isAnonymous ? null : ($user['id'] ?? $reporterUserId), 'INCIDENT_CREATED', 'incident', $incidentId, [
            'incident_number' => $incidentNumber,
            'category' => $category,
            'is_anonymous' => $isAnonymous,
            'privacy_level' => $privacyLevel,
            'classification_source' => $classification['source'],
            'ai_confidence' => $aiConfidence,
        ]);

        // Evaluate and persist candidate similar incidents (with check constraint a < b)
        $matches = IncidentMatcherService::detectAndPersistMatches($incidentId, $incidentData);

        // Handle optional visual evidence attachments (max 2)
        $attachments = [];
        if (!empty($_FILES)) {
            $fileList = IncidentAttachment::normalizeFiles($_FILES);
            foreach (array_slice($fileList, 0, IncidentAttachment::MAX_ATTACHMENTS_PER_RECORD) as $f) {
                try {
                    $att = IncidentAttachment::processUploadedFile($f, $incidentId, null, $user['id'] ?? $reporterUserId);
                    if ($att) {
                        $attachments[] = $att;
                    }
                } catch (\Throwable $e) {
                    error_log('[CampusSOS Incident Attachment Warning]: ' . $e->getMessage());
                }
            }
        }

        if (empty($attachments) && !empty($input['images']) && is_array($input['images'])) {
            foreach (array_slice($input['images'], 0, IncidentAttachment::MAX_ATTACHMENTS_PER_RECORD) as $idx => $img) {
                try {
                    $dataUri = is_string($img) ? $img : ($img['data'] ?? ($img['dataUrl'] ?? ''));
                    $name = is_array($img) ? ($img['name'] ?? "incident_evidence_{$idx}.jpg") : "incident_evidence_{$idx}.jpg";
                    if (!empty($dataUri)) {
                        $att = IncidentAttachment::processBase64Image($dataUri, $name, $incidentId, null, $user['id'] ?? $reporterUserId);
                        if ($att) {
                            $attachments[] = $att;
                        }
                    }
                } catch (\Throwable $e) {
                    error_log('[CampusSOS Incident Base64 Warning]: ' . $e->getMessage());
                }
            }
        }

        $result = array_merge($incidentData, [
            'id' => $incidentId,
            'incidentNumber' => $incidentNumber,
            'classification' => $classification,
            'matches' => $matches,
            'attachments' => !empty($attachments) ? $attachments : IncidentAttachment::findByIncidentId($incidentId),
            'reporterName' => $isAnonymous ? 'Anonymous' : ($user['fullName'] ?? $user['full_name'] ?? null),
            'reporterStudentId' => $isAnonymous ? null : ($user['studentId'] ?? $user['student_id'] ?? null),
            'reporterEmail' => $isAnonymous ? null : ($user['email'] ?? null),
            'reporterPhone' => $isAnonymous ? null : ($user['phoneNumber'] ?? $user['phone_number'] ?? null),
        ]);

        ResponseService::json($result, 201, 'Incident successfully logged.');
    }

    /**
     * PUT /api/incidents/{id}/status
     * Updates status with chronological history tracking.
     */
    public function updateStatus(int $id): void
    {
        $user = AuthService::getCurrentUser();
        if (!$user) {
            ResponseService::error('Authentication required.', 401);
        }

        // Only admins and authorized responders can update status
        if (!in_array($user['role'], ['admin', 'responder'], true)) {
            ResponseService::error('Unauthorized to update incident status.', 403);
        }

        $incident = Incident::findById($id);
        if (!$incident) {
            ResponseService::error('Incident not found.', 404);
        }

        // Responders can only update/resolve incidents matching their department category or assigned to them
        if ($user['role'] === 'responder') {
            $responderProfile = $user['responderProfile'] ?? null;
            $responderCategory = $responderProfile['category'] ?? null;
            $responderId = (int) ($responderProfile['id'] ?? 0);

            $isAssigned = $responderId > 0 && IncidentAssignment::isAssignedToResponder($id, $responderId);
            $isCategoryMatch = !empty($responderCategory) && ($incident['category'] === $responderCategory);

            if (!$isCategoryMatch && !$isAssigned) {
                ResponseService::error('Forbidden: Responders cannot update or resolve incidents outside their department or assignment.', 403);
            }
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $newStatus = trim($input['status'] ?? '');
        $updateNote = trim($input['notes'] ?? ($input['updateText'] ?? ($input['resolutionNotes'] ?? "Status updated to {$newStatus}.")));
        $isInternal = !empty($input['isInternal']);

        $allowedStatuses = ['reported', 'under_review', 'dispatched', 'in_progress', 'resolved', 'closed', 'rejected'];
        if (!in_array($newStatus, $allowedStatuses, true)) {
            ResponseService::error('Invalid incident status transition.', 422);
        }

        if ($incident['status'] === 'resolved' && $newStatus === 'resolved') {
            ResponseService::error('Incident has already been resolved.', 409);
        }

        $prevStatus = $incident['status'];
        $success = Incident::updateStatus($id, $newStatus);
        if (!$success) {
            ResponseService::error('Failed to update status.', 500);
        }

        // When resolving, update corresponding incident assignments to 'completed'
        if ($newStatus === 'resolved') {
            IncidentAssignment::completeAllForIncident($id, $updateNote);
        }

        // Record chronological update entry
        IncidentUpdate::create($id, (int) $user['id'], $prevStatus, $newStatus, $updateNote, $isInternal);

        // Notify reporter if not anonymous
        if (!empty($incident['reporter_user_id'])) {
            Notification::create(
                (int) $incident['reporter_user_id'],
                'incident_status',
                "Your incident report #{$incident['incident_number']} status was updated to '{$newStatus}'.",
                "/tracker?id={$id}"
            );
        }

        AuditLog::log((int) $user['id'], 'STATUS_UPDATED', 'incident', $id, [
            'prev_status' => $prevStatus,
            'new_status' => $newStatus,
            'note' => $updateNote,
        ]);

        ResponseService::json(['id' => $id, 'status' => $newStatus], 200, 'Incident status updated.');
    }

    /**
     * POST /api/incidents/{id}/assign
     * Admin or dispatch assigns an incident to a responder.
     */
    public function assign(int $id): void
    {
        RoleMiddleware::requireAdmin();

        $incident = Incident::findById($id);
        if (!$incident) {
            ResponseService::error('Incident not found.', 404);
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $responderId = (int) ($input['responderId'] ?? 0);
        $notes = trim($input['notes'] ?? 'Assigned by Campus Safety Administration.');

        if ($responderId <= 0) {
            ResponseService::error('A valid responder ID is required.', 422);
        }

        $assigned = IncidentAssignment::assign($id, $responderId, $notes);
        if (!$assigned) {
            ResponseService::error('Failed to assign incident to responder.', 500);
        }

        // Advance status to dispatched if still reported
        if ($incident['status'] === 'reported' || $incident['status'] === 'under_review') {
            Incident::updateStatus($id, 'dispatched');
            IncidentUpdate::create(
                $id,
                AuthService::getCurrentUser()['id'] ?? null,
                $incident['status'],
                'dispatched',
                "Dispatched and assigned to responder unit. Notes: {$notes}",
                false
            );
        }

        // Look up responder user ID to notify them
        $pdo = \CampusSOS\Config\Database::getConnection();
        if ($pdo) {
            $stmt = $pdo->prepare("SELECT user_id FROM responders WHERE id = :r_id");
            $stmt->execute([':r_id' => $responderId]);
            $respUserId = $stmt->fetchColumn();
            if ($respUserId) {
                Notification::create(
                    (int) $respUserId,
                    'assignment',
                    "New incident assigned: #{$incident['incident_number']} ({$incident['title']}).",
                    "/responders"
                );
            }
        }

        AuditLog::log(AuthService::getCurrentUser()['id'] ?? null, 'INCIDENT_ASSIGNED', 'incident', $id, [
            'responder_id' => $responderId,
            'notes' => $notes,
        ]);

        ResponseService::json(['incidentId' => $id, 'responderId' => $responderId], 200, 'Incident assigned successfully.');
    }

    /**
     * POST /api/incidents/{id}/acknowledge
     * Responder acknowledges assignment.
     */
    public function acknowledgeAssignment(int $id): void
    {
        AuthMiddleware::authenticate();
        $user = AuthService::getCurrentUser();
        if (!$user || $user['role'] !== 'responder') {
            ResponseService::error('Only authorized responders can acknowledge assignments.', 403);
        }

        $responderProfile = $user['responderProfile'] ?? null;
        if (!$responderProfile) {
            ResponseService::error('Responder profile not found for user.', 404);
        }

        $responderId = (int) $responderProfile['id'];
        $success = IncidentAssignment::acknowledge($id, $responderId);
        if (!$success) {
            ResponseService::error('Failed to acknowledge assignment.', 400);
        }

        Incident::updateStatus($id, 'in_progress');
        IncidentUpdate::create(
            $id,
            (int) $user['id'],
            'dispatched',
            'in_progress',
            "Assignment acknowledged by {$user['fullName']}. Action underway.",
            false
        );

        AuditLog::log((int) $user['id'], 'ASSIGNMENT_ACKNOWLEDGED', 'incident', $id);

        ResponseService::json(['incidentId' => $id, 'status' => 'in_progress'], 200, 'Assignment acknowledged.');
    }
}
