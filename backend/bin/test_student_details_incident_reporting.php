<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Incident.php';
require_once __DIR__ . '/../models/IncidentUpdate.php';
require_once __DIR__ . '/../models/IncidentAssignment.php';
require_once __DIR__ . '/../models/AuditLog.php';
require_once __DIR__ . '/../services/AuthService.php';
require_once __DIR__ . '/../services/ResponseService.php';
require_once __DIR__ . '/../services/GeminiClassificationService.php';
require_once __DIR__ . '/../services/IncidentMatcherService.php';
require_once __DIR__ . '/../services/LocationService.php';
require_once __DIR__ . '/../controllers/IncidentController.php';

use CampusSOS\Config\Database;
use CampusSOS\Models\User;
use CampusSOS\Models\Incident;
use CampusSOS\Models\IncidentUpdate;
use CampusSOS\Services\AuthService;
use CampusSOS\Controllers\IncidentController;

$pdo = Database::getConnection();
if (!$pdo) {
    echo "FAIL: Cannot connect to database\n";
    exit(1);
}

echo "=== TEST: Student Details in Incident Reporting ===\n";

// 1. Verify Student User exists
$student = User::findByEmail('student@campussos.edu');
if (!$student) {
    echo "FAIL: Student user student@campussos.edu not found\n";
    exit(1);
}
echo "✓ Found Student User: {$student['full_name']} (ID: {$student['id']}, StudentID: {$student['student_id']})\n";

// Helper to simulate request
function runWithUser(?array $user, callable $fn) {
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

// 2. Test Identified Report Creation
echo "\n--- Testing Identified Report Submission ---\n";
$identifiedIncidentId = null;
runWithUser($student, function($user) use (&$identifiedIncidentId) {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $payload = [
        'title' => 'Broken Water Filter near C-302',
        'description' => 'Water filter leaking continuously on 3rd floor hallway.',
        'category' => 'maintenance',
        'locationName' => 'Academic Block C - Room C-302',
        'roomNumber' => 'C-302',
        'isAnonymous' => false,
        'privacyLevel' => 'standard',
    ];

    // Simulate php://input with reflection or custom mock
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, json_encode($payload));
    rewind($stream);

    // Call Incident::create directly and verify
    $loc = \CampusSOS\Services\LocationService::validateAndFormat($payload['locationName'], $payload['roomNumber']);
    $incNum = 'INC-TEST-' . rand(1000, 9999);
    $data = [
        'incident_number' => $incNum,
        'reporter_user_id' => $user['id'],
        'is_anonymous' => false,
        'is_confidential' => false,
        'privacy_level' => 'standard',
        'category' => $payload['category'],
        'title' => $payload['title'],
        'description' => $payload['description'],
        'location_name' => $loc['formattedLocation'],
        'room_number' => $loc['roomNumber'],
        'occurred_at' => date('Y-m-d H:i:s'),
        'status' => 'reported',
        'severity' => 'medium',
        'ai_classified' => false,
        'ai_confidence' => null,
    ];

    $identifiedIncidentId = Incident::create($data);
    IncidentUpdate::create($identifiedIncidentId, $user['id'], null, 'reported', 'Incident reported by campus member.');
});

echo "✓ Created Identified Incident ID: {$identifiedIncidentId}\n";

// Verify identified incident data in Incident::findById
$identifiedRecord = Incident::findById($identifiedIncidentId);
if (!$identifiedRecord) {
    echo "FAIL: Identified incident not found by id\n";
    exit(1);
}

if ($identifiedRecord['reporter_name'] !== $student['full_name']) {
    echo "FAIL: Expected reporter_name '{$student['full_name']}', got '{$identifiedRecord['reporter_name']}'\n";
    exit(1);
}
if ($identifiedRecord['reporter_student_id'] !== $student['student_id']) {
    echo "FAIL: Expected reporter_student_id '{$student['student_id']}', got '{$identifiedRecord['reporter_student_id']}'\n";
    exit(1);
}
if ($identifiedRecord['reporter_email'] !== $student['email']) {
    echo "FAIL: Expected reporter_email '{$student['email']}', got '{$identifiedRecord['reporter_email']}'\n";
    exit(1);
}
if ($identifiedRecord['reporter_phone'] !== $student['phone_number']) {
    echo "FAIL: Expected reporter_phone '{$student['phone_number']}', got '{$identifiedRecord['reporter_phone']}'\n";
    exit(1);
}
echo "✓ Identified incident correctly preserves and displays student name, student ID, email, and phone\n";

// 3. Test Anonymous Report Submission
echo "\n--- Testing Anonymous Report Submission ---\n";
$anonymousIncidentId = null;
runWithUser($student, function($user) use (&$anonymousIncidentId) {
    $loc = \CampusSOS\Services\LocationService::validateAndFormat('Hostel Block A - Room H-105', 'H-105');
    $incNum = 'INC-ANON-' . rand(1000, 9999);
    $data = [
        'incident_number' => $incNum,
        'reporter_user_id' => null, // Explicitly null for anonymous
        'is_anonymous' => true,
        'is_confidential' => false,
        'privacy_level' => 'standard',
        'category' => 'security',
        'title' => 'Suspicious Individual near Hostel A cycle stand',
        'description' => 'Unidentified person attempting to tamper with bicycles.',
        'location_name' => $loc['formattedLocation'],
        'room_number' => $loc['roomNumber'],
        'occurred_at' => date('Y-m-d H:i:s'),
        'status' => 'reported',
        'severity' => 'medium',
        'ai_classified' => false,
        'ai_confidence' => null,
    ];

    $anonymousIncidentId = Incident::create($data);
    IncidentUpdate::create($anonymousIncidentId, null, null, 'reported', 'Incident reported anonymously.');
});

echo "✓ Created Anonymous Incident ID: {$anonymousIncidentId}\n";

$anonRecord = Incident::findById($anonymousIncidentId);
if (!$anonRecord) {
    echo "FAIL: Anonymous incident not found by id\n";
    exit(1);
}

if ((int)$anonRecord['is_anonymous'] !== 1) {
    echo "FAIL: Expected is_anonymous = 1, got {$anonRecord['is_anonymous']}\n";
    exit(1);
}
if ($anonRecord['reporter_name'] !== 'Anonymous') {
    echo "FAIL: Expected reporter_name 'Anonymous', got '{$anonRecord['reporter_name']}'\n";
    exit(1);
}
if ($anonRecord['reporter_user_id'] !== null) {
    echo "FAIL: Expected reporter_user_id NULL, got '{$anonRecord['reporter_user_id']}'\n";
    exit(1);
}
if ($anonRecord['reporter_student_id'] !== null) {
    echo "FAIL: Expected reporter_student_id NULL, got '{$anonRecord['reporter_student_id']}'\n";
    exit(1);
}
if ($anonRecord['reporter_email'] !== null) {
    echo "FAIL: Expected reporter_email NULL, got '{$anonRecord['reporter_email']}'\n";
    exit(1);
}
if ($anonRecord['reporter_phone'] !== null) {
    echo "FAIL: Expected reporter_phone NULL, got '{$anonRecord['reporter_phone']}'\n";
    exit(1);
}
echo "✓ Anonymous incident completely withholds student name, student ID, email, and phone at the database/model level\n";

// 4. Test Activity Updates Timeline Leakage
echo "\n--- Testing Activity History Timeline Anonymity ---\n";
$updates = IncidentUpdate::findByIncident($anonymousIncidentId, true);
foreach ($updates as $up) {
    if ($up['actor_name'] === $student['full_name']) {
        echo "FAIL: Student name leaked in timeline update!\n";
        exit(1);
    }
    if ($up['actor_user_id'] !== null) {
        echo "FAIL: Student user ID leaked in timeline update: {$up['actor_user_id']}\n";
        exit(1);
    }
}
echo "✓ Timeline activity updates do NOT leak student name or user ID\n";

// 5. Test Admin Visibility
echo "\n--- Testing Admin Retrieval via Incident::findAll ---\n";
$admin = User::findByEmail('admin@campussos.edu');
$adminIncidents = Incident::findAllForUser($admin, []);

$foundIdentified = false;
$foundAnonymous = false;

foreach ($adminIncidents as $inc) {
    if ((int)$inc['id'] === $identifiedIncidentId) {
        $foundIdentified = true;
        if ($inc['reporter_name'] !== $student['full_name']) {
            echo "FAIL: Admin saw wrong reporter name for identified incident: {$inc['reporter_name']}\n";
            exit(1);
        }
        if ($inc['reporter_student_id'] !== $student['student_id']) {
            echo "FAIL: Admin saw wrong student ID for identified incident: {$inc['reporter_student_id']}\n";
            exit(1);
        }
    }
    if ((int)$inc['id'] === $anonymousIncidentId) {
        $foundAnonymous = true;
        if ($inc['reporter_name'] !== 'Anonymous') {
            echo "FAIL: Admin saw non-anonymous name for anonymous incident: {$inc['reporter_name']}\n";
            exit(1);
        }
        if ($inc['reporter_student_id'] !== null) {
            echo "FAIL: Admin saw non-null student ID for anonymous incident: {$inc['reporter_student_id']}\n";
            exit(1);
        }
    }
}

if (!$foundIdentified || !$foundAnonymous) {
    echo "FAIL: Admin did not find both test incidents in list\n";
    exit(1);
}
echo "✓ Admin correctly sees full student details for identified incident, and 'Anonymous' with no credentials for anonymous incident\n";

echo "\n============================================\n";
echo "ALL STUDENT DETAILS INCIDENT REPORTING TESTS PASSED!\n";
echo "============================================\n";
exit(0);
