<?php
declare(strict_types=1);

namespace CampusSOS\Controllers;

use CampusSOS\Models\IncidentAttachment;
use CampusSOS\Models\Incident;
use CampusSOS\Models\SosAlert;
use CampusSOS\Services\AuthService;
use CampusSOS\Services\ResponseService;
use CampusSOS\Services\SosRoutingService;

class AttachmentController
{
    /**
     * GET /api/attachments/{id}
     * Returns attachment metadata with RBAC verification.
     */
    public function show(int $id): void
    {
        $attachment = IncidentAttachment::findById($id);
        if (!$attachment) {
            ResponseService::error('Attachment not found.', 404);
        }

        $user = AuthService::getCurrentUser();
        if (!$this->isAuthorized($attachment, $user)) {
            ResponseService::error('Access to this attachment is restricted.', 403);
        }

        ResponseService::json($attachment, 200);
    }

    /**
     * GET /api/attachments/{id}/file
     * Streams binary image file with RBAC verification.
     */
    public function serveFile(int $id): void
    {
        $attachment = IncidentAttachment::findById($id);
        if (!$attachment) {
            ResponseService::error('Attachment not found.', 404);
        }

        $user = AuthService::getCurrentUser();
        if (!$this->isAuthorized($attachment, $user)) {
            ResponseService::error('Access to this attachment is restricted.', 403);
        }

        $filePath = dirname(__DIR__) . '/public' . $attachment['filePath'];
        if (!file_exists($filePath)) {
            ResponseService::error('File does not exist on disk.', 404);
        }

        $mime = $attachment['fileType'] ?: 'image/jpeg';
        $size = filesize($filePath);

        // Clear output buffer
        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . $size);
        header('Content-Disposition: inline; filename="' . addslashes($attachment['fileName']) . '"');
        header('Cache-Control: private, max-age=86400');
        header('X-Content-Type-Options: nosniff');

        readfile($filePath);
        exit;
    }

    /**
     * Check if user is authorized to view this attachment.
     */
    private function isAuthorized(array $attachment, ?array $user): bool
    {
        // 1. Admin has universal access
        if ($user && $user['role'] === 'admin') {
            return true;
        }

        // 2. Incident-linked attachment
        if (!empty($attachment['incidentId'])) {
            $incident = Incident::findById((int) $attachment['incidentId']);
            if ($incident) {
                // Confidential report checks
                if ($incident['privacy_level'] === 'confidential') {
                    if (!$user) return false;
                    if ($user['role'] === 'student') {
                        return (int) ($incident['reporter_user_id'] ?? 0) === (int) $user['id'];
                    }
                    if ($user['role'] === 'responder') {
                        $cat = $user['responderProfile']['category'] ?? '';
                        return $incident['category'] === $cat;
                    }
                    return false;
                }

                // Standard report checks
                if ($user && $user['role'] === 'responder') {
                    $cat = $user['responderProfile']['category'] ?? '';
                    return $incident['category'] === $cat || !empty($incident['assignments']);
                }

                // Public / student checks
                if (!$user || $user['role'] === 'student') {
                    return $incident['privacy_level'] === 'standard'
                        || ($user && (int) ($incident['reporter_user_id'] ?? 0) === (int) $user['id']);
                }
            }
        }

        // 3. SOS-linked attachment
        if (!empty($attachment['sosAlertId'])) {
            $alert = SosAlert::findById((int) $attachment['sosAlertId']);
            if ($alert) {
                if (!$user) return false;
                if ($user['role'] === 'student') {
                    return (int) ($alert['user_id'] ?? 0) === (int) $user['id'];
                }
                if ($user['role'] === 'responder') {
                    $responder = $user['responderProfile'] ?? null;
                    return $responder && SosRoutingService::isResponderAuthorized($responder, $alert);
                }
            }
        }

        return false;
    }
}
