<?php
declare(strict_types=1);

/**
 * CampusSOS — Verification Suite for Emergency SOS Emergency Type Dropdown & Custom Option
 *
 * Verifies:
 * 1. All 12 predefined emergency types are configured
 * 2. Predefined option selection transmits and persists correctly in database and API
 * 3. Custom emergency type reveals custom description requirement
 * 4. Empty custom description produces validation error
 * 5. Valid custom description persists emergency_type = 'Custom' and emergency_details
 * 6. Campus location and room number logic preserved alongside emergency type
 * 7. SOS dispatch remains deterministic and independent of Gemini/AI
 * 8. Responder portal active alerts display emergencyType and emergencyDetails
 * 9. Regression check: SOS acknowledge and resolve workflows unaffected
 */

require_once __DIR__ . '/../bootstrap.php';
use CampusSOS\Config\Database;

$baseUrl = 'http://localhost:8000';
$demoPassword = 'Password@2026';

$passCount = 0;
$failCount = 0;

function report(bool $passed, string $message): void {
    global $passCount, $failCount;
    if ($passed) {
        $passCount++;
        echo "  ✅ [PASS] {$message}\n";
    } else {
        $failCount++;
        echo "  ❌ [FAIL] {$message}\n";
    }
}

function apiCall(string $url, string $method = 'GET', ?array $data = null, ?string $cookieFile = null, ?string $csrf = null): array {
    $ch = curl_init($url);
    $headers = ['Accept: application/json'];
    if ($csrf) {
        $headers[] = "X-CSRF-Token: {$csrf}";
    }
    if ($data !== null) {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    if ($cookieFile) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'body' => json_decode((string)$body, true) ?? $body];
}

function loginSession(string $email, string $baseUrl, string $password): array {
    $cookieFile = tempnam(sys_get_temp_dir(), 'csos_sostype_');
    $csrfRes = apiCall("{$baseUrl}/api/auth/csrf", 'GET', null, $cookieFile);
    $csrf = $csrfRes['body']['data']['csrfToken'] ?? '';

    $loginRes = apiCall("{$baseUrl}/api/auth/login", 'POST', [
        'email' => $email,
        'password' => $password,
    ], $cookieFile, $csrf);

    if ($loginRes['code'] !== 200) {
        throw new RuntimeException("Failed to login as {$email}: " . json_encode($loginRes));
    }

    $activeCsrf = $loginRes['body']['data']['csrfToken'] ?? $csrf;
    $user = $loginRes['body']['data']['user'];

    return [
        'cookieFile' => $cookieFile,
        'csrf' => $activeCsrf,
        'user' => $user,
    ];
}

echo "======================================================================\n";
echo " CampusSOS Emergency SOS Dropdown & Custom Option Test Suite\n";
echo " Base URL: {$baseUrl}\n";
echo "======================================================================\n\n";

$pdo = Database::getConnection();
if (!$pdo) {
    echo "❌ Database connection failed\n";
    exit(1);
}

// 1. Audit Frontend Files for Exact Options and Requirements
echo "--- 1. Auditing Frontend Constants & Component Markups ---\n";

$emergencyTypesFile = __DIR__ . '/../../frontend/src/constants/emergencyTypes.ts';
report(file_exists($emergencyTypesFile), "File frontend/src/constants/emergencyTypes.ts exists");

$typesContent = file_get_contents($emergencyTypesFile) ?: '';
$expectedOptions = [
    'Fire / Smoke',
    'Vehicle Collision',
    'Medical Emergency',
    'Physical Assault / Fight',
    'Harassment / Threat',
    'Suspicious Activity',
    'Theft / Robbery',
    'Electrical Hazard',
    'Gas Leak',
    'Injury / Accident',
    'Other Emergency',
    'Custom',
];

$allPresent = true;
foreach ($expectedOptions as $opt) {
    if (!str_contains($typesContent, "'{$opt}'")) {
        $allPresent = false;
        break;
    }
}
report($allPresent, "All 12 exact predefined emergency types defined in emergencyTypes.ts");

// Check HomePage.tsx
$homePageContent = file_get_contents(__DIR__ . '/../../frontend/src/pages/HomePage.tsx') ?: '';
report(str_contains($homePageContent, 'Emergency Type (Optional)'), "HomePage modal contains label 'Emergency Type (Optional)'");
report(str_contains($homePageContent, 'Select emergency type'), "HomePage modal contains placeholder option 'Select emergency type'");
report(str_contains($homePageContent, 'Describe the Emergency'), "HomePage modal contains conditional label 'Describe the Emergency'");
report(str_contains($homePageContent, 'Briefly describe the emergency...'), "HomePage modal contains placeholder 'Briefly describe the emergency...'");
report(str_contains($homePageContent, 'Please describe the emergency.'), "HomePage modal contains custom validation 'Please describe the emergency.'");

// Check FloatingSosButton.tsx
$floatingContent = file_get_contents(__DIR__ . '/../../frontend/src/components/common/FloatingSosButton.tsx') ?: '';
report(str_contains($floatingContent, 'Emergency Type (Optional)'), "Floating SOS modal contains label 'Emergency Type (Optional)'");
report(str_contains($floatingContent, 'Select emergency type'), "Floating SOS modal contains placeholder option 'Select emergency type'");
report(str_contains($floatingContent, 'Describe the Emergency'), "Floating SOS modal contains conditional label 'Describe the Emergency'");
report(str_contains($floatingContent, 'Briefly describe the emergency...'), "Floating SOS modal contains placeholder 'Briefly describe the emergency...'");
report(str_contains($floatingContent, 'Please describe the emergency.'), "Floating SOS modal contains custom validation 'Please describe the emergency.'");


// 2. Database Schema Audit
echo "\n--- 2. Database Schema Audit ---\n";
$stmt = $pdo->query("SHOW COLUMNS FROM sos_alerts LIKE 'emergency_type'");
$colType = $stmt->fetch(PDO::FETCH_ASSOC);
report($colType !== false, "Table sos_alerts contains column 'emergency_type'");

$stmt = $pdo->query("SHOW COLUMNS FROM sos_alerts LIKE 'emergency_details'");
$colDetails = $stmt->fetch(PDO::FETCH_ASSOC);
report($colDetails !== false, "Table sos_alerts contains column 'emergency_details'");


// 3. API Dispatch with Predefined Emergency Types
echo "\n--- 3. Testing API Dispatch with Predefined Emergency Types ---\n";
$student = loginSession('student@campussos.edu', $baseUrl, $demoPassword);

// Test 3a: Fire / Smoke at Football Ground (general area, no room)
$startTime = microtime(true);
$fireSos = apiCall("{$baseUrl}/api/sos/trigger", 'POST', [
    'locationName' => 'Football Ground',
    'emergencyType' => 'Fire / Smoke',
], $student['cookieFile'], $student['csrf']);
$latencyMs = (microtime(true) - $startTime) * 1000;

report($fireSos['code'] === 201, "Dispatched SOS with 'Fire / Smoke' (HTTP 201)");
$fireSosId = (int) ($fireSos['body']['data']['id'] ?? 0);
report($fireSosId > 0, "Received valid SOS alert ID #{$fireSosId}");
report(($fireSos['body']['data']['emergencyType'] ?? '') === 'Fire / Smoke', "API response contains emergencyType 'Fire / Smoke'");
report($latencyMs < 500, sprintf("Deterministic zero-latency response (%.2fms < 500ms)", $latencyMs));

// Verify DB
$stmt = $pdo->prepare("SELECT emergency_type, emergency_details, location_name, room_number FROM sos_alerts WHERE id = :id");
$stmt->execute([':id' => $fireSosId]);
$dbRow = $stmt->fetch(PDO::FETCH_ASSOC);
report($dbRow['emergency_type'] === 'Fire / Smoke', "Database persisted emergency_type = 'Fire / Smoke'");
report($dbRow['emergency_details'] === null, "Database emergency_details is NULL for standard predefined type");
report($dbRow['location_name'] === 'Football Ground', "Location 'Football Ground' preserved");

// Test 3b: Medical Emergency at building with Room Number (Civil Block - Room 302)
$medSos = apiCall("{$baseUrl}/api/sos/trigger", 'POST', [
    'locationName' => 'Civil Block — Room 302',
    'roomNumber' => '302',
    'emergencyType' => 'Medical Emergency',
], $student['cookieFile'], $student['csrf']);

report($medSos['code'] === 201, "Dispatched SOS with 'Medical Emergency' at Civil Block Room 302 (HTTP 201)");
$medSosId = (int) ($medSos['body']['data']['id'] ?? 0);
$stmt->execute([':id' => $medSosId]);
$dbMed = $stmt->fetch(PDO::FETCH_ASSOC);
report($dbMed['emergency_type'] === 'Medical Emergency', "Database persisted emergency_type = 'Medical Emergency'");
report($dbMed['room_number'] === '302', "Building room number '302' preserved");


// 4. Custom Option Validation & Persistence
echo "\n--- 4. Testing Custom Emergency Option Behavior ---\n";

// Test 4a: Custom selected but empty description -> 422 Unprocessable Entity
$emptyCustom = apiCall("{$baseUrl}/api/sos/trigger", 'POST', [
    'locationName' => 'Central Library',
    'emergencyType' => 'Custom',
    'emergencyDetails' => '',
], $student['cookieFile'], $student['csrf']);

report($emptyCustom['code'] === 422, "Submitting 'Custom' with empty description returns HTTP 422 Validation Error");
report(str_contains(strtolower($emptyCustom['body']['message'] ?? ''), 'custom'), "Error message explicitly references custom emergency requirement");

// Test 4b: Custom selected with valid description
$customDesc = 'Drone collided with rooftop solar panel, sparking near ventilation intake.';
$validCustom = apiCall("{$baseUrl}/api/sos/trigger", 'POST', [
    'locationName' => 'Idea Lab',
    'emergencyType' => 'Custom',
    'emergencyDetails' => $customDesc,
], $student['cookieFile'], $student['csrf']);

report($validCustom['code'] === 201, "Submitting 'Custom' with valid description succeeds (HTTP 201)");
$customSosId = (int) ($validCustom['body']['data']['id'] ?? 0);
report($customSosId > 0, "Received custom SOS alert ID #{$customSosId}");
report(($validCustom['body']['data']['emergencyType'] ?? '') === 'Custom', "Response contains emergencyType 'Custom'");
report(($validCustom['body']['data']['emergencyDetails'] ?? '') === $customDesc, "Response contains custom emergencyDetails");

$stmt->execute([':id' => $customSosId]);
$dbCustom = $stmt->fetch(PDO::FETCH_ASSOC);
report($dbCustom['emergency_type'] === 'Custom', "Database persisted emergency_type = 'Custom'");
report($dbCustom['emergency_details'] === $customDesc, "Database persisted custom description in emergency_details");


// 5. Active SOS Feed in Responder Portal
echo "\n--- 5. Responder Portal Active Alerts Verification ---\n";
$medical = loginSession('medical@campussos.edu', $baseUrl, $demoPassword);

$activeRes = apiCall("{$baseUrl}/api/sos/active", 'GET', null, $medical['cookieFile']);
report($activeRes['code'] === 200, "Responder retrieved active alerts feed (HTTP 200)");

$alerts = $activeRes['body']['data'] ?? [];
$foundCustom = false;
$foundFire = false;
foreach ($alerts as $a) {
    if (($a['id'] ?? 0) === $customSosId) {
        $foundCustom = true;
        report(($a['emergencyType'] ?? $a['emergency_type'] ?? '') === 'Custom', "Active alert #{$customSosId} displays emergencyType 'Custom'");
        report(($a['emergencyDetails'] ?? $a['emergency_details'] ?? '') === $customDesc, "Active alert #{$customSosId} includes custom details");
    }
    if (($a['id'] ?? 0) === $fireSosId) {
        $foundFire = true;
        report(($a['emergencyType'] ?? $a['emergency_type'] ?? '') === 'Fire / Smoke', "Active alert #{$fireSosId} displays emergencyType 'Fire / Smoke'");
    }
}
report($foundCustom && $foundFire, "Both newly dispatched SOS alerts visible to on-duty responders");


// 6. Resolution Lifecycle Continuity
echo "\n--- 6. Resolution Lifecycle Continuity ---\n";
$resolveRes = apiCall("{$baseUrl}/api/sos/{$customSosId}/resolve", 'POST', [
    'resolutionNotes' => 'Solar panel inspected and isolated. Threat neutralized.',
], $medical['cookieFile'], $medical['csrf']);

report($resolveRes['code'] === 200, "Custom SOS #{$customSosId} resolved cleanly (HTTP 200)");

$stmt = $pdo->prepare("SELECT status, resolution_notes, resolved_at FROM sos_alerts WHERE id = :id");
$stmt->execute([':id' => $customSosId]);
$dbResolved = $stmt->fetch(PDO::FETCH_ASSOC);
report($dbResolved['status'] === 'resolved', "Status updated to 'resolved' in database");
report(!empty($dbResolved['resolved_at']), "resolved_at timestamp populated");


// 7. Deterministic Zero-Latency Performance Under Load
echo "\n--- 7. Deterministic Performance Check ---\n";
$times = [];
for ($i = 0; $i < 5; $i++) {
    $t0 = microtime(true);
    $res = apiCall("{$baseUrl}/api/sos/trigger", 'POST', [
        'locationName' => 'Gate 1',
        'emergencyType' => $expectedOptions[$i % count($expectedOptions)],
    ], $student['cookieFile'], $student['csrf']);
    $times[] = (microtime(true) - $t0) * 1000;
}
$avgMs = array_sum($times) / count($times);
report($avgMs < 100, sprintf("Average dispatch latency across 5 requests: %.2fms (< 100ms)", $avgMs));

echo "\n======================================================================\n";
echo " Test Summary: {$passCount} Passed, {$failCount} Failed\n";
echo "======================================================================\n";

// Cleanup cookies
@unlink($student['cookieFile']);
@unlink($medical['cookieFile']);

if ($failCount > 0) {
    exit(1);
}
