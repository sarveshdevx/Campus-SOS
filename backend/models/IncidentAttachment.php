<?php
declare(strict_types=1);

namespace CampusSOS\Models;

use CampusSOS\Config\Database;
use PDO;

class IncidentAttachment
{
    public const MAX_ATTACHMENTS_PER_RECORD = 2;
    public const MAX_FILE_SIZE_BYTES = 10485760; // 10MB
    public const ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    /**
     * Insert an attachment record into the database.
     */
    public static function create(array $data): ?int
    {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return null;
        }

        $stmt = $pdo->prepare("
            INSERT INTO incident_attachments (
                incident_id, sos_alert_id, file_path, file_name, file_type, file_size, uploaded_by_user_id, uploaded_at
            ) VALUES (
                :incident_id, :sos_alert_id, :file_path, :file_name, :file_type, :file_size, :uploaded_by_user_id, NOW()
            )
        ");

        $ok = $stmt->execute([
            ':incident_id' => $data['incident_id'] ?? null,
            ':sos_alert_id' => $data['sos_alert_id'] ?? null,
            ':file_path' => $data['file_path'],
            ':file_name' => $data['file_name'],
            ':file_type' => $data['file_type'],
            ':file_size' => (int) $data['file_size'],
            ':uploaded_by_user_id' => $data['uploaded_by_user_id'] ?? null,
        ]);

        return $ok ? (int) $pdo->lastInsertId() : null;
    }

    /**
     * Find single attachment by ID.
     */
    public static function findById(int $id): ?array
    {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return null;
        }

        $stmt = $pdo->prepare("
            SELECT a.*,
                   i.privacy_level AS incident_privacy_level,
                   i.category AS incident_category,
                   i.reporter_user_id AS incident_reporter_id,
                   s.detected_category AS sos_category,
                   s.assigned_department AS sos_department,
                   s.user_id AS sos_user_id
            FROM incident_attachments a
            LEFT JOIN incidents i ON a.incident_id = i.id
            LEFT JOIN sos_alerts s ON a.sos_alert_id = s.id
            WHERE a.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ? self::formatRow($row) : null;
    }

    /**
     * Find all attachments for an incident.
     */
    public static function findByIncidentId(int $incidentId): array
    {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return [];
        }

        $stmt = $pdo->prepare("
            SELECT * FROM incident_attachments
            WHERE incident_id = :incident_id
            ORDER BY id ASC
            LIMIT 2
        ");
        $stmt->execute([':incident_id' => $incidentId]);
        $rows = $stmt->fetchAll();

        return array_map([self::class, 'formatRow'], $rows);
    }

    /**
     * Find all attachments for an SOS alert.
     */
    public static function findBySosAlertId(int $sosAlertId): array
    {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return [];
        }

        $stmt = $pdo->prepare("
            SELECT * FROM incident_attachments
            WHERE sos_alert_id = :sos_alert_id
            ORDER BY id ASC
            LIMIT 2
        ");
        $stmt->execute([':sos_alert_id' => $sosAlertId]);
        $rows = $stmt->fetchAll();

        return array_map([self::class, 'formatRow'], $rows);
    }

    /**
     * Count attachments for an incident.
     */
    public static function countByIncident(int $incidentId): int
    {
        $pdo = Database::getConnection();
        if (!$pdo) return 0;
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM incident_attachments WHERE incident_id = :id");
        $stmt->execute([':id' => $incidentId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Count attachments for an SOS alert.
     */
    public static function countBySosAlert(int $sosAlertId): int
    {
        $pdo = Database::getConnection();
        if (!$pdo) return 0;
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM incident_attachments WHERE sos_alert_id = :id");
        $stmt->execute([':id' => $sosAlertId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Normalize $_FILES array across single, multiple, and keyed uploads.
     */
    public static function normalizeFiles(array $files): array
    {
        $normalized = [];

        foreach ($files as $field => $data) {
            if (is_array($data['name'] ?? null)) {
                $count = count($data['name']);
                for ($i = 0; $i < $count; $i++) {
                    if (($data['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                        continue;
                    }
                    $normalized[] = [
                        'name' => $data['name'][$i],
                        'type' => $data['type'][$i] ?? '',
                        'tmp_name' => $data['tmp_name'][$i],
                        'error' => $data['error'][$i],
                        'size' => $data['size'][$i],
                    ];
                }
            } elseif (isset($data['name'])) {
                if (($data['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $normalized[] = $data;
                }
            }
        }

        return $normalized;
    }

    /**
     * Process and store an uploaded file ($_FILES item).
     */
    public static function processUploadedFile(
        array $fileInfo,
        ?int $incidentId = null,
        ?int $sosAlertId = null,
        ?int $userId = null
    ): ?array {
        if (($fileInfo['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return null;
        }

        $tmpPath = $fileInfo['tmp_name'] ?? '';
        if (!is_uploaded_file($tmpPath) && !file_exists($tmpPath)) {
            return null;
        }

        $fileSize = (int) ($fileInfo['size'] ?? filesize($tmpPath));
        if ($fileSize <= 0 || $fileSize > self::MAX_FILE_SIZE_BYTES) {
            return null;
        }

        // Validate MIME type securely
        $mime = self::detectMimeType($tmpPath);
        if (!isset(self::ALLOWED_MIME_TYPES[$mime])) {
            return null;
        }

        $ext = self::ALLOWED_MIME_TYPES[$mime];
        $originalName = basename((string) ($fileInfo['name'] ?? "image.{$ext}"));
        // Clean original name
        $originalName = preg_replace('/[^a-zA-Z0-9_\-\. ]/', '', $originalName) ?: "image.{$ext}";

        $uploadDir = dirname(__DIR__) . '/public/uploads/attachments';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $uniqueFilename = sprintf(
            'photo_%s_%s.%s',
            date('Ymd_His'),
            bin2hex(random_bytes(4)),
            $ext
        );

        $destination = $uploadDir . '/' . $uniqueFilename;
        $saved = false;

        if (is_uploaded_file($tmpPath)) {
            $saved = move_uploaded_file($tmpPath, $destination);
        } else {
            $saved = copy($tmpPath, $destination);
        }

        if (!$saved) {
            return null;
        }

        $relativePath = '/uploads/attachments/' . $uniqueFilename;

        $attachmentId = self::create([
            'incident_id' => $incidentId,
            'sos_alert_id' => $sosAlertId,
            'file_path' => $relativePath,
            'file_name' => $originalName,
            'file_type' => $mime,
            'file_size' => $fileSize,
            'uploaded_by_user_id' => $userId,
        ]);

        if (!$attachmentId) {
            @unlink($destination);
            return null;
        }

        return self::findById($attachmentId);
    }

    /**
     * Process base64 encoded data URI or raw base64.
     */
    public static function processBase64Image(
        string $dataUri,
        string $originalName,
        ?int $incidentId = null,
        ?int $sosAlertId = null,
        ?int $userId = null
    ): ?array {
        // Parse data:image/png;base64,....
        $mime = 'image/jpeg';
        $base64Data = $dataUri;

        if (preg_match('/^data:(image\/[a-zA-Z0-9\+\-\.]+);base64,(.+)$/', $dataUri, $matches)) {
            $mime = strtolower($matches[1]);
            $base64Data = $matches[2];
        }

        $binary = base64_decode($base64Data, true);
        if ($binary === false || strlen($binary) === 0) {
            return null;
        }

        $fileSize = strlen($binary);
        if ($fileSize > self::MAX_FILE_SIZE_BYTES) {
            return null;
        }

        // Temporarily write to inspect MIME
        $tempFile = tempnam(sys_get_temp_dir(), 'csos_');
        file_put_contents($tempFile, $binary);
        $detectedMime = self::detectMimeType($tempFile);
        unlink($tempFile);

        if (!isset(self::ALLOWED_MIME_TYPES[$detectedMime])) {
            return null;
        }

        $ext = self::ALLOWED_MIME_TYPES[$detectedMime];
        $uploadDir = dirname(__DIR__) . '/public/uploads/attachments';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $uniqueFilename = sprintf(
            'photo_%s_%s.%s',
            date('Ymd_His'),
            bin2hex(random_bytes(4)),
            $ext
        );

        $destination = $uploadDir . '/' . $uniqueFilename;
        if (file_put_contents($destination, $binary) === false) {
            return null;
        }

        $cleanOriginalName = basename($originalName);
        $cleanOriginalName = preg_replace('/[^a-zA-Z0-9_\-\. ]/', '', $cleanOriginalName) ?: "photo.{$ext}";
        $relativePath = '/uploads/attachments/' . $uniqueFilename;

        $attachmentId = self::create([
            'incident_id' => $incidentId,
            'sos_alert_id' => $sosAlertId,
            'file_path' => $relativePath,
            'file_name' => $cleanOriginalName,
            'file_type' => $detectedMime,
            'file_size' => $fileSize,
            'uploaded_by_user_id' => $userId,
        ]);

        if (!$attachmentId) {
            @unlink($destination);
            return null;
        }

        return self::findById($attachmentId);
    }

    /**
     * Secure MIME detection using finfo or getimagesize.
     */
    private static function detectMimeType(string $filePath): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $filePath);
            finfo_close($finfo);
            if ($mime) {
                return strtolower($mime);
            }
        }

        $img = @getimagesize($filePath);
        if ($img && !empty($img['mime'])) {
            return strtolower($img['mime']);
        }

        return 'application/octet-stream';
    }

    /**
     * Format a database row into an API-ready array.
     */
    public static function formatRow(array $row): array
    {
        $id = (int) $row['id'];
        $filePath = (string) $row['file_path'];

        return [
            'id' => $id,
            'incidentId' => !empty($row['incident_id']) ? (int) $row['incident_id'] : null,
            'incident_id' => !empty($row['incident_id']) ? (int) $row['incident_id'] : null,
            'sosAlertId' => !empty($row['sos_alert_id']) ? (int) $row['sos_alert_id'] : null,
            'sos_alert_id' => !empty($row['sos_alert_id']) ? (int) $row['sos_alert_id'] : null,
            'fileName' => $row['file_name'],
            'file_name' => $row['file_name'],
            'fileType' => $row['file_type'],
            'file_type' => $row['file_type'],
            'fileSize' => (int) $row['file_size'],
            'file_size' => (int) $row['file_size'],
            'filePath' => $filePath,
            'file_path' => $filePath,
            'url' => $filePath, // Direct static path served by PHP server
            'downloadUrl' => "/api/attachments/{$id}/file",
            'uploadedAt' => $row['uploaded_at'],
            'uploaded_at' => $row['uploaded_at'],
            // Internal privacy attributes if joined
            'incident_privacy_level' => $row['incident_privacy_level'] ?? null,
            'incident_category' => $row['incident_category'] ?? null,
            'incident_reporter_id' => $row['incident_reporter_id'] ?? null,
            'sos_category' => $row['sos_category'] ?? null,
            'sos_department' => $row['sos_department'] ?? null,
            'sos_user_id' => $row['sos_user_id'] ?? null,
        ];
    }
}
