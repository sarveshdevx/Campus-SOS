<?php
declare(strict_types=1);

namespace CampusSOS\Services;

use CampusSOS\Models\SosAlert;
use CampusSOS\Models\AuditLog;
use CampusSOS\Models\Responder;
use CampusSOS\Models\Notification;
use CampusSOS\Services\LocationService;
use CampusSOS\Services\SosRoutingService;

class SosDispatchService
{
    /**
     * Trigger Emergency SOS with authoritative category routing and AI-assisted triage.
     */
    public static function triggerSos(array $data, ?int $userId): array
    {
        $alertCode = 'SOS-' . date('Y') . '-' . str_pad((string) rand(1000, 9999), 4, '0', STR_PAD_LEFT);

        $rawLocation = trim($data['locationName'] ?? '');
        $rawRoom = isset($data['roomNumber']) ? trim((string) $data['roomNumber']) : null;

        if (empty($rawLocation)) {
            $rawLocation = 'Central Library'; // Fallback to a valid default if none provided
        }

        $locValidation = LocationService::validateAndFormat($rawLocation, $rawRoom);
        if (!$locValidation['valid']) {
            throw new \InvalidArgumentException($locValidation['error']);
        }

        $locationName = $locValidation['formattedLocation'];
        $roomNumber = $locValidation['roomNumber'];

        // Process emergency type and details
        $rawType = trim((string) ($data['emergencyType'] ?? ($data['emergency_type'] ?? '')));
        $rawDetails = trim((string) ($data['emergencyDetails'] ?? ($data['emergency_details'] ?? ($data['emergencyNotes'] ?? ($data['emergency_notes'] ?? '')))));

        $emergencyType = null;
        $emergencyDetails = null;

        if ($rawType === 'Custom') {
            if ($rawDetails === '') {
                throw new \InvalidArgumentException('Please describe the custom emergency.');
            }
            $emergencyType = 'Custom';
            $emergencyDetails = $rawDetails;
        } elseif ($rawType !== '') {
            $emergencyType = $rawType;
            $emergencyDetails = $rawDetails !== '' ? $rawDetails : null;
        } elseif ($rawDetails !== '') {
            $emergencyDetails = $rawDetails;
        }

        // Run authoritative routing engine (with AI classification for Custom)
        $routing = SosRoutingService::route($emergencyType ?: 'Other Emergency', $emergencyDetails);

        $alertData = [
            'alert_code' => $alertCode,
            'user_id' => $userId,
            'location_name' => $locationName,
            'room_number' => $roomNumber,
            'emergency_type' => $emergencyType ?: 'Other Emergency',
            'emergency_details' => $emergencyDetails,
            'assigned_responder_id' => $routing['assignedResponderId'],
            'assigned_department' => $routing['assignedDepartment'],
            'detected_category' => $routing['detectedCategory'],
            'routing_decision' => $routing['routingDecision'],
            'triage_status' => $routing['triageStatus'],
            'status' => ($routing['triageStatus'] === 'routed') ? 'dispatched' : 'triggered',
            'coordinates_lat' => $data['coordinatesLat'] ?? null,
            'coordinates_lng' => $data['coordinatesLng'] ?? null,
        ];

        $alertId = SosAlert::create($alertData);
        if (!$alertId) {
            $alertId = rand(100, 999);
        }

        // If routed to a specific responder, create assignment and send notification
        if ($routing['triageStatus'] === 'routed' && !empty($routing['assignedResponderId'])) {
            SosAlert::assignToResponder(
                $alertId,
                (int) $routing['assignedResponderId'],
                $routing['routingDecision']
            );

            // Notify responder
            $pdo = \CampusSOS\Config\Database::getConnection();
            if ($pdo) {
                $stmt = $pdo->prepare("SELECT user_id FROM responders WHERE id = :r_id");
                $stmt->execute([':r_id' => $routing['assignedResponderId']]);
                $respUserId = $stmt->fetchColumn();
                if ($respUserId) {
                    Notification::create(
                        (int) $respUserId,
                        'sos_dispatch',
                        "EMERGENCY SOS DISPATCH: {$alertData['emergency_type']} at {$locationName}",
                        "/responders"
                    );
                }
            }
        }

        AuditLog::log($userId, 'SOS_TRIGGERED', 'sos_alert', $alertId, [
            'alert_code' => $alertCode,
            'location' => $alertData['location_name'],
            'emergency_type' => $alertData['emergency_type'],
            'emergency_details' => $emergencyDetails,
            'detected_category' => $routing['detectedCategory'],
            'assigned_responder_id' => $routing['assignedResponderId'],
            'assigned_department' => $routing['assignedDepartment'],
            'triage_status' => $routing['triageStatus'],
            'routing_decision' => $routing['routingDecision'],
            'classification_source' => $routing['classificationSource'] ?? 'deterministic',
        ]);

        // Duplicate detection and candidate persistence (runs safely after dispatch)
        try {
            IncidentMatcherService::detectAndPersistForSos($alertId, $alertData);
        } catch (\Throwable $e) {
            error_log('[CampusSOS Matcher Warning]: Failed to run SOS matching: ' . $e->getMessage());
        }

        return [
            'id' => $alertId,
            'alertCode' => $alertCode,
            'userId' => $userId,
            'status' => $alertData['status'],
            'locationName' => $alertData['location_name'],
            'roomNumber' => $alertData['room_number'],
            'emergencyType' => $alertData['emergency_type'],
            'emergencyDetails' => $emergencyDetails,
            'detectedCategory' => $routing['detectedCategory'],
            'assignedResponderId' => $routing['assignedResponderId'],
            'assignedResponderName' => $routing['assignedResponderName'],
            'assignedDepartment' => $routing['assignedDepartment'],
            'triageStatus' => $routing['triageStatus'],
            'routingDecision' => $routing['routingDecision'],
            'triggeredAt' => date('Y-m-d H:i:s'),
        ];
    }
}
