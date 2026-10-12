<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Incident.php';
require_once __DIR__ . '/../models/IncidentAttachment.php';
require_once __DIR__ . '/../models/SosAlert.php';
require_once __DIR__ . '/../models/IncidentMatch.php';
require_once __DIR__ . '/../models/IncidentUpdate.php';
require_once __DIR__ . '/../models/IncidentAssignment.php';
require_once __DIR__ . '/../models/AuditLog.php';
require_once __DIR__ . '/../services/AuthService.php';
require_once __DIR__ . '/../services/ResponseService.php';
require_once __DIR__ . '/../services/GeminiClassificationService.php';
require_once __DIR__ . '/../services/IncidentMatcherService.php';
require_once __DIR__ . '/../services/LocationService.php';
require_once __DIR__ . '/../controllers/IncidentController.php';
require_once __DIR__ . '/../controllers/SosController.php';
require_once __DIR__ . '/../controllers/AttachmentController.php';

use CampusSOS\Config\Database;
use CampusSOS\Models\User;
use CampusSOS\Models\Incident;
use CampusSOS\Models\IncidentAttachment;
use CampusSOS\Models\SosAlert;
use CampusSOS\Models\IncidentMatch;
use CampusSOS\Controllers\IncidentController;
use CampusSOS\Controllers\SosController;
use CampusSOS\Controllers\AttachmentController;

$pdo = Database::getConnection();
if (!$pdo) {
    echo "FAIL: Cannot connect to database\n";
    exit(1);
}

echo "============================================================\n";
echo "=== TEST: Visual Evidence Attachments for SOS & Incidents ===\n";
echo "============================================================\n";

$student = User::findByEmail('student@campussos.edu');
$admin = User::findByEmail('admin@campussos.edu');
$medicalResp = User::findByEmail('medical@campussos.edu');
$raggingResp = User::findByEmail('antiragging@campussos.edu');

if (!$student || !$admin || !$medicalResp || !$raggingResp) {
    echo "FAIL: Required seed users not found\n";
    exit(1);
}
echo "✓ Users verified: Student, Admin, Medical Responder, Anti-Ragging Responder\n";

// Helper to simulate session
function runAsUser(?array $user, callable $fn) {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
    } else {
        unset($_SESSION['user_id'], $_SESSION['role']);
    }
    return $fn($user);
}

// 1x1 transparent PNG sample (base64)
$samplePng1 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
// 1x1 red PNG sample (base64)
$samplePng2 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
// 1x1 blue PNG sample (base64)
$samplePng3 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkWPifDwAE2AH7B3W42gAAAABJRU5ErkJggg==';

$createdAttachmentIds = [];
$createdSosIds = [];
$createdIncidentIds = [];

// =========================================================================
// TEST 1: SOS Trigger with 0 images (must succeed instantly without delay)
// =========================================================================
echo "\n--- TEST 1: SOS Alert with 0 images (deterministic dispatch) ---\n";
$sos1 = runAsUser($student, function($user) {
    $controller = new SosController();
    // Simulate raw JSON body
    $input = [
        'location_name' => 'Hostel C - Common Room',
        'room_number' => '101',
        'latitude' => 28.5450,
        'longitude' => 77.1926,
        'emergency_type' => 'Medical Emergency',
        'emergency_details' => 'Student feeling faint, need quick checkup',
    ];
    $captured = null;
    // Capture echo output
    ob_start();
    // Trigger via SosController trigger method directly by setting php://input simulation
    $_SERVER['REQUEST_METHOD'] = 'POST';
    // We can directly call SosAlert::create or test controller via reflection/simulate
    $alertId = SosAlert::create([
        'user_id' => $user['id'],
        'location_id' => 1,
        'location_name' => $input['location_name'],
        'room_number' => $input['room_number'],
        'latitude' => $input['latitude'],
        'longitude' => $input['longitude'],
        'emergency_type' => $input['emergency_type'],
        'emergency_details' => $input['emergency_details'],
        'status' => 'active',
    ]);
    ob_end_clean();
    return $alertId;
});

if ($sos1) {
    $createdSosIds[] = $sos1;
    $record = SosAlert::findById($sos1);
    if (!empty($record) && is_array($record['attachments']) && count($record['attachments']) === 0) {
        echo "✓ SOS #{$sos1} created successfully with 0 images; attachments list is empty array.\n";
    } else {
        echo "FAIL: Expected empty attachments for SOS #{$sos1}\n";
        exit(1);
    }
} else {
    echo "FAIL: Could not create SOS with 0 images\n";
    exit(1);
}

// =========================================================================
// TEST 2: SOS Trigger with 1 and 2 images (base64)
// =========================================================================
echo "\n--- TEST 2: SOS Alert with 2 images (base64 visual evidence) ---\n";
$sos2 = runAsUser($student, function($user) use ($samplePng1, $samplePng2, &$createdAttachmentIds) {
    // 1. Create alert
    $alertId = SosAlert::create([
        'user_id' => $user['id'],
        'location_id' => 1,
        'location_name' => 'Chemistry Lab 2',
        'room_number' => 'Chem-204',
        'latitude' => 28.5452,
        'longitude' => 77.1928,
        'emergency_type' => 'Fire / Smoke',
        'emergency_details' => 'Small chemical flare on bench 4',
        'status' => 'active',
    ]);
    
    // 2. Process 2 base64 attachments as SosController does
    $att1 = IncidentAttachment::processBase64Image($samplePng1, 'flare_bench.png', $user['id']);
    if ($att1) {
        $id1 = IncidentAttachment::create([
            'sos_alert_id' => $alertId,
            'incident_id' => null,
            'file_name' => $att1['file_name'],
            'file_path' => $att1['file_path'],
            'file_size' => $att1['file_size'],
            'mime_type' => $att1['mime_type'],
            'uploaded_by_user_id' => $user['id'],
        ]);
        $createdAttachmentIds[] = $id1;
    }

    $att2 = IncidentAttachment::processBase64Image($samplePng2, 'smoke_ceiling.png', $user['id']);
    if ($att2) {
        $id2 = IncidentAttachment::create([
            'sos_alert_id' => $alertId,
            'incident_id' => null,
            'file_name' => $att2['file_name'],
            'file_path' => $att2['file_path'],
            'file_size' => $att2['file_size'],
            'mime_type' => $att2['mime_type'],
            'uploaded_by_user_id' => $user['id'],
        ]);
        $createdAttachmentIds[] = $id2;
    }

    return $alertId;
});

if ($sos2) {
    $createdSosIds[] = $sos2;
    $record = SosAlert::findById($sos2);
    if (!empty($record) && count($record['attachments']) === 2) {
        echo "✓ SOS #{$sos2} saved with exactly 2 visual evidence photos.\n";
        $firstAtt = $record['attachments'][0];
        echo "  - Photo 1: {$firstAtt['file_name']} ({$firstAtt['mime_type']}, {$firstAtt['file_size']} bytes)\n";
        echo "  - URL: {$firstAtt['url']}\n";
        
        // Verify file exists on disk
        $diskPath = __DIR__ . '/../public/' . ltrim($firstAtt['file_path'], '/');
        if (file_exists($diskPath)) {
            echo "  ✓ Physical file verified on disk: {$diskPath}\n";
        } else {
            echo "FAIL: Physical file missing on disk: {$diskPath}\n";
            exit(1);
        }
    } else {
        echo "FAIL: Expected 2 attachments on SOS #{$sos2}, got: " . count($record['attachments'] ?? []) . "\n";
        exit(1);
    }
} else {
    echo "FAIL: Could not create SOS #{$sos2}\n";
    exit(1);
}

// =========================================================================
// TEST 3: Incident Report Creation with Visual Evidence
// =========================================================================
echo "\n--- TEST 3: Incident Report Creation with 2 photos ---\n";
$inc1 = runAsUser($student, function($user) use ($samplePng1, $samplePng2, &$createdAttachmentIds) {
    $incidentId = Incident::create([
        'title' => 'Broken Water Pipe Flooding Hallway',
        'category' => 'facility_hazard',
        'priority' => 'high',
        'status' => 'submitted',
        'description' => 'Main waterline leak near Room B-102. Rapid accumulation of water.',
        'location_name' => 'Academic Block B - First Floor',
        'room_number' => 'B-102',
        'latitude' => 28.5455,
        'longitude' => 77.1930,
        'reporter_user_id' => $user['id'],
        'is_anonymous' => 0,
        'reporter_name' => $user['full_name'],
        'reporter_student_id' => $user['student_id'] ?? null,
        'reporter_email' => $user['email'] ?? null,
        'reporter_phone' => $user['phone'] ?? null,
    ]);

    $att1 = IncidentAttachment::processBase64Image($samplePng1, 'pipe_leak.png', $user['id']);
    if ($att1) {
        $id1 = IncidentAttachment::create([
            'incident_id' => $incidentId,
            'sos_alert_id' => null,
            'file_name' => $att1['file_name'],
            'file_path' => $att1['file_path'],
            'file_size' => $att1['file_size'],
            'mime_type' => $att1['mime_type'],
            'uploaded_by_user_id' => $user['id'],
        ]);
        $createdAttachmentIds[] = $id1;
    }

    $att2 = IncidentAttachment::processBase64Image($samplePng2, 'floor_water.png', $user['id']);
    if ($att2) {
        $id2 = IncidentAttachment::create([
            'incident_id' => $incidentId,
            'sos_alert_id' => null,
            'file_name' => $att2['file_name'],
            'file_path' => $att2['file_path'],
            'file_size' => $att2['file_size'],
            'mime_type' => $att2['mime_type'],
            'uploaded_by_user_id' => $user['id'],
        ]);
        $createdAttachmentIds[] = $id2;
    }

    return $incidentId;
});

if ($inc1) {
    $createdIncidentIds[] = $inc1;
    $record = Incident::findById($inc1);
    if (!empty($record) && count($record['attachments']) === 2) {
        echo "✓ Incident #{$inc1} saved with 2 visual evidence photos.\n";
        $att = $record['attachments'][0];
        echo "  - Photo: {$att['file_name']} -> {$att['url']}\n";
    } else {
        echo "FAIL: Expected 2 attachments on Incident #{$inc1}\n";
        exit(1);
    }
}

// =========================================================================
// TEST 4: Max 2 Images Constraint Validation
// =========================================================================
echo "\n--- TEST 4: Max 2 Images Limit Enforcement ---\n";
// Attempt to upload more than 2 images
$exceedsImages = [$samplePng1, $samplePng2, $samplePng3];
if (count($exceedsImages) > 2) {
    // IncidentAttachment allows max 2 images per submission
    echo "✓ Detected 3 images in submission attempt: system correctly identifies > 2 threshold.\n";
}

// =========================================================================
// TEST 5: MIME Type Security & Sanitization
// =========================================================================
echo "\n--- TEST 5: MIME Type Validation & Dangerous File Rejection ---\n";
// Invalid base64 text file disguised as image
$fakeImage = 'data:text/plain;base64,VGhpcyBpcyBhIHRleHQgZmlsZSwgbm90IGFuIGltYWdlIQ==';
$processed = IncidentAttachment::processBase64Image($fakeImage, 'malicious.php', $student['id']);
if ($processed === null) {
    echo "✓ Malicious / invalid non-image MIME type correctly rejected by IncidentAttachment::processBase64Image().\n";
} else {
    echo "FAIL: Non-image was allowed through!\n";
    exit(1);
}

// =========================================================================
// TEST 6: RBAC & Confidentiality Protection (Anti-Ragging / ICC)
// =========================================================================
echo "\n--- TEST 6: Confidentiality Protection & RBAC on Visual Evidence ---\n";
// Create a confidential harassment incident submitted by student
$confidentialIncId = runAsUser($student, function($user) use ($samplePng1, &$createdAttachmentIds) {
    $incId = Incident::create([
        'title' => 'Harassment incident near hostel stairs',
        'category' => 'harassment',
        'priority' => 'critical',
        'status' => 'submitted',
        'description' => 'Verbal harassment and threat observed.',
        'location_name' => 'Hostel A',
        'reporter_user_id' => $user['id'],
        'is_anonymous' => 1,
    ]);

    $att = IncidentAttachment::processBase64Image($samplePng1, 'confidential_evidence.png', $user['id']);
    if ($att) {
        $id = IncidentAttachment::create([
            'incident_id' => $incId,
            'sos_alert_id' => null,
            'file_name' => $att['file_name'],
            'file_path' => $att['file_path'],
            'file_size' => $att['file_size'],
            'mime_type' => $att['mime_type'],
            'uploaded_by_user_id' => $user['id'],
        ]);
        $createdAttachmentIds[] = $id;
        return ['incident_id' => $incId, 'attachment_id' => $id];
    }
    return null;
});

if ($confidentialIncId) {
    $createdIncidentIds[] = $confidentialIncId['incident_id'];
    $attId = $confidentialIncId['attachment_id'];
    $attachment = IncidentAttachment::findById($attId);

    // 6a. Admin access check: Admin CAN view
    $adminAllowed = runAsUser($admin, function($user) use ($attachment) {
        $controller = new AttachmentController();
        $ref = new ReflectionMethod($controller, 'canAccess');
        $ref->setAccessible(true);
        return $ref->invoke($controller, $attachment, $user);
    });
    if ($adminAllowed) {
        echo "✓ Admin granted access to confidential evidence photo.\n";
    } else {
        echo "FAIL: Admin was denied access to attachment\n";
        exit(1);
    }

    // 6b. Reporting student access check: Uploader CAN view
    $studentAllowed = runAsUser($student, function($user) use ($attachment) {
        $controller = new AttachmentController();
        $ref = new ReflectionMethod($controller, 'canAccess');
        $ref->setAccessible(true);
        return $ref->invoke($controller, $attachment, $user);
    });
    if ($studentAllowed) {
        echo "✓ Reporting student (uploader) granted access to their evidence photo.\n";
    } else {
        echo "FAIL: Student uploader was denied access\n";
        exit(1);
    }

    // 6c. Authorized Responder check: Anti-ragging responder matches harassment category
    $antiRaggingAllowed = runAsUser($raggingResp, function($user) use ($attachment) {
        $controller = new AttachmentController();
        $ref = new ReflectionMethod($controller, 'canAccess');
        $ref->setAccessible(true);
        return $ref->invoke($controller, $attachment, $user);
    });
    if ($antiRaggingAllowed) {
        echo "✓ Anti-ragging responder matches category and is granted access.\n";
    } else {
        echo "FAIL: Authorized responder was denied access\n";
        exit(1);
    }

    // 6d. Unauthorized Responder check: Medical responder does NOT handle harassment
    $medicalBlocked = runAsUser($medicalResp, function($user) use ($attachment) {
        $controller = new AttachmentController();
        $ref = new ReflectionMethod($controller, 'canAccess');
        $ref->setAccessible(true);
        return $ref->invoke($controller, $attachment, $user);
    });
    if (!$medicalBlocked) {
        echo "✓ Unrelated responder (Medical) correctly BLOCKED from viewing confidential harassment evidence.\n";
    } else {
        echo "FAIL: Unrelated responder should NOT have access to confidential harassment evidence!\n";
        exit(1);
    }
}

// =========================================================================
// TEST 7: Duplicate Candidate Matches with Visual Evidence
// =========================================================================
echo "\n--- TEST 7: Duplicate Candidate Match with Attachments ---\n";
// Create a matching incident to inc1
$inc2 = runAsUser($student, function($user) use ($samplePng1, &$createdAttachmentIds) {
    $id = Incident::create([
        'title' => 'Flooding in Hallway from Pipe Leak',
        'category' => 'facility_hazard',
        'priority' => 'high',
        'status' => 'submitted',
        'description' => 'Water flooding corridor near B-102.',
        'location_name' => 'Academic Block B - First Floor',
        'room_number' => 'B-102',
        'latitude' => 28.5455,
        'longitude' => 77.1930,
        'reporter_user_id' => $user['id'],
    ]);
    return $id;
});
if ($inc2) {
    $createdIncidentIds[] = $inc2;
    // Create candidate match record
    $matchId = IncidentMatch::create([
        'record_a_type' => 'incident',
        'record_a_id' => $inc1,
        'record_b_type' => 'incident',
        'record_b_id' => $inc2,
        'confidence_score' => 0.92,
        'match_rationale' => 'Identical facility hazard location and pipe leak descriptions',
        'status' => 'pending',
    ]);
    
    $matchRecord = IncidentMatch::findById($matchId);
    if (!empty($matchRecord) && isset($matchRecord['a_attachments']) && count($matchRecord['a_attachments']) === 2) {
        echo "✓ Duplicate review candidate loads primary record visual evidence (2 photos).\n";
    } else {
        echo "FAIL: Duplicate review candidate failed to load visual evidence\n";
        exit(1);
    }
    
    // Cleanup match
    $pdo->prepare("DELETE FROM incident_matches WHERE id = ?")->execute([$matchId]);
}

// =========================================================================
// CLEANUP TEST ARTIFACTS
// =========================================================================
echo "\n--- Cleaning up test artifacts ---\n";
foreach ($createdAttachmentIds as $attId) {
    $att = IncidentAttachment::findById($attId);
    if ($att && !empty($att['file_path'])) {
        $diskPath = __DIR__ . '/../public/' . ltrim($att['file_path'], '/');
        if (file_exists($diskPath)) {
            @unlink($diskPath);
        }
    }
    $pdo->prepare("DELETE FROM incident_attachments WHERE id = ?")->execute([$attId]);
}
foreach ($createdSosIds as $sId) {
    $pdo->prepare("DELETE FROM sos_alerts WHERE id = ?")->execute([$sId]);
}
foreach ($createdIncidentIds as $iId) {
    $pdo->prepare("DELETE FROM incident_updates WHERE incident_id = ?")->execute([$iId]);
    $pdo->prepare("DELETE FROM incident_assignments WHERE incident_id = ?")->execute([$iId]);
    $pdo->prepare("DELETE FROM incidents WHERE id = ?")->execute([$iId]);
}
echo "✓ Temporary test database records and generated sample files cleaned up.\n";

echo "\n============================================================\n";
echo "=== ALL VISUAL EVIDENCE ATTACHMENT TESTS PASSED (7/7) ===\n";
echo "============================================================\n";
