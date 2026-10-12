<?php
declare(strict_types=1);

/**
 * CampusSOS — Verification Suite:
 * 1. Removal of BUSY status from duty-status controls (Available & Offline only)
 * 2. Backend and Frontend filtering of resolved incidents from active queues:
 *    - 'My Assigned Incident Cases' (GET /api/responders/my-assignments)
 *    - 'Department Incident Queue' (GET /api/incidents)
 * 3. Immediate disappearance without page reload
 * 4. Historical data preservation in database
 * 5. SOS alerts independence
 */

require_once __DIR__ . '/../bootstrap.php';

use CampusSOS\Config\Database;

$baseUrl = 'http://localhost:8000';
$demoPassword = 'Password@2026';
$passed = 0;
$failed = 0;

function pass(string $msg): void {
    global $passed;
    $passed++;
    echo "  ✅ [PASS] {$msg}\n";
}

function fail(string $msg): void {
    global $failed;
    $failed++;
    echo "  ❌ [FAIL] {$msg}\n";
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
    $cookieFile = tempnam(sys_get_temp_dir(), 'csos_busy_');
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
echo " CampusSOS Busy Status Removal & Resolved Incident Queue Filtering\n";
echo " Base URL: {$baseUrl}\n";
echo "======================================================================\n\n";

// --- 1. Audit Frontend Files for BUSY Button Removal ---
echo "--- 1. Auditing Frontend Code for BUSY Button Removal ---\n";
$dashboardCode = file_get_contents(__DIR__ . '/../../frontend/src/pages/ResponderDashboardPage.tsx');

if (strpos($dashboardCode, "(['available', 'offline'] as const)") !== false) {
    pass("ResponderDashboardPage duty-status buttons array restricted to available and offline only");
} else {
    fail("ResponderDashboardPage duty-status buttons array still has busy or is invalid");
}

if (strpos($dashboardCode, "(['available', 'busy', 'offline'] as const)") === false) {
    pass("Responder duty-status controls no longer include 'busy'");
} else {
    fail("Found legacy '(['available', 'busy', 'offline'] as const)' in duty-status controls");
}

if (strpos($dashboardCode, "const isBusy = r.status === 'busy'") === false) {
    pass("Obsolete isBusy logic removed from responder roster card rendering");
} else {
    fail("Obsolete isBusy logic still present in ResponderDashboardPage");
}

if (strpos($dashboardCode, "setDepartmentIncidents((prev) => prev.filter((inc) => inc.id !== resolveTarget.id))") !== false &&
    strpos($dashboardCode, "setAssignedIncidents((prev) => prev.filter((a) => a.incident_id !== resolveTarget.id))") !== false) {
    pass("Immediate UI removal implemented upon incident resolution in handleConfirmResolve");
} else {
    fail("Immediate optimistic removal missing in handleConfirmResolve");
}

// --- 2. Duty Status API Tests ---
echo "\n--- 2. Duty Status API Behavior (Available and Offline) ---\n";
try {
    $medSession = loginSession('medical@campussos.edu', $baseUrl, $demoPassword);
    pass("Medical responder authenticated successfully");
} catch (Exception $e) {
    fail("Failed to authenticate as Medical Responder: " . $e->getMessage());
    exit(1);
}

// Toggle to offline
$resOffline = apiCall("{$baseUrl}/api/responders/status", 'PUT', ['status' => 'offline'], $medSession['cookieFile'], $medSession['csrf']);
if ($resOffline['code'] === 200 && ($resOffline['body']['data']['status'] ?? '') === 'offline') {
    pass("Medical responder successfully set duty status to OFFLINE (HTTP 200)");
} else {
    fail("Failed to set duty status to OFFLINE: " . json_encode($resOffline));
}

// Verify DB updated
$pdo = Database::getConnection();
$stmt = $pdo->prepare("SELECT status FROM responders WHERE id = 1");
$stmt->execute();
$dbStatus = $stmt->fetchColumn();
if ($dbStatus === 'offline') {
    pass("Database confirmed responder status is 'offline'");
} else {
    fail("Database responder status mismatch: expected 'offline', got '{$dbStatus}'");
}

// Toggle back to available
$resAvail = apiCall("{$baseUrl}/api/responders/status", 'PUT', ['status' => 'available'], $medSession['cookieFile'], $medSession['csrf']);
if ($resAvail['code'] === 200 && ($resAvail['body']['data']['status'] ?? '') === 'available') {
    pass("Medical responder successfully set duty status to AVAILABLE (HTTP 200)");
} else {
    fail("Failed to set duty status to AVAILABLE: " . json_encode($resAvail));
}

$stmt->execute();
$dbStatus2 = $stmt->fetchColumn();
if ($dbStatus2 === 'available') {
    pass("Database confirmed responder status is 'available'");
} else {
    fail("Database responder status mismatch: expected 'available', got '{$dbStatus2}'");
}

// --- 3. Active Department Incident Queue Backend Filtering ---
echo "\n--- 3. Department Incident Queue Backend Filtering (GET /api/incidents) ---\n";

// Create a new medical incident
$resCreate = apiCall("{$baseUrl}/api/incidents", 'POST', [
    'title' => 'Student fainting spell near Idea Lab',
    'description' => 'Student reported feeling dizzy and collapsed temporarily. Consciousness regained.',
    'category' => 'medical',
    'locationName' => 'Idea Lab',
    'severity' => 'high',
], $medSession['cookieFile'], $medSession['csrf']);

if ($resCreate['code'] === 201) {
    $medIncidentId = (int) $resCreate['body']['data']['id'];
    pass("Created active medical incident #{$medIncidentId} (HTTP 201)");
} else {
    fail("Failed to create medical incident: " . json_encode($resCreate));
    exit(1);
}

// Check GET /api/incidents as medical responder
$resQueue1 = apiCall("{$baseUrl}/api/incidents", 'GET', null, $medSession['cookieFile'], $medSession['csrf']);
if ($resQueue1['code'] === 200) {
    $ids = array_column($resQueue1['body']['data'] ?? [], 'id');
    if (in_array($medIncidentId, $ids, true)) {
        pass("Active medical incident #{$medIncidentId} appears in department incident queue");
    } else {
        fail("Medical incident #{$medIncidentId} not found in department queue");
    }
} else {
    fail("Failed to query /api/incidents: " . json_encode($resQueue1));
}

// Medical responder resolves the incident
$resResolve = apiCall("{$baseUrl}/api/incidents/{$medIncidentId}/status", 'PUT', [
    'status' => 'resolved',
    'notes' => 'Patient assessed, vitals stable, given electrolytes and resting in infirmary.',
], $medSession['cookieFile'], $medSession['csrf']);

if ($resResolve['code'] === 200) {
    pass("Medical responder successfully marked incident #{$medIncidentId} as resolved (HTTP 200)");
} else {
    fail("Failed to resolve incident #{$medIncidentId}: " . json_encode($resResolve));
}

// Query GET /api/incidents again as medical responder — MUST NOT contain resolved incident
$resQueue2 = apiCall("{$baseUrl}/api/incidents", 'GET', null, $medSession['cookieFile'], $medSession['csrf']);
if ($resQueue2['code'] === 200) {
    $idsAfter = array_column($resQueue2['body']['data'] ?? [], 'id');
    if (!in_array($medIncidentId, $idsAfter, true)) {
        pass("Resolved incident #{$medIncidentId} is completely filtered out from responder's active department queue");
    } else {
        fail("Resolved incident #{$medIncidentId} is still present in responder's active department queue!");
    }
} else {
    fail("Failed to query /api/incidents after resolution");
}

// --- 4. Assigned Incident Cases Backend Filtering (GET /api/responders/my-assignments) ---
echo "\n--- 4. My Assigned Incident Cases Backend Filtering (GET /api/responders/my-assignments) ---\n";
try {
    $adminSession = loginSession('admin@campussos.edu', $baseUrl, $demoPassword);
    pass("Admin authenticated successfully");
} catch (Exception $e) {
    fail("Failed to authenticate as Admin: " . $e->getMessage());
    exit(1);
}

// Create another incident for assignment test
$resCreate2 = apiCall("{$baseUrl}/api/incidents", 'POST', [
    'title' => 'Heat stroke symptom at Sports Complex',
    'description' => 'Athlete feeling nausea and overheating after match.',
    'category' => 'medical',
    'locationName' => 'Basketball Court',
    'severity' => 'critical',
], $medSession['cookieFile'], $medSession['csrf']);

$medIncidentId2 = (int) $resCreate2['body']['data']['id'];
pass("Created medical incident #{$medIncidentId2} for assignment test");

// Admin assigns incident to Medical Responder (unit ID 1)
$resAssign = apiCall("{$baseUrl}/api/incidents/{$medIncidentId2}/assign", 'POST', [
    'responderId' => 1,
    'notes' => 'Dispatched unit 1 to Basketball Court immediately.',
], $adminSession['cookieFile'], $adminSession['csrf']);

if ($resAssign['code'] === 200) {
    pass("Admin assigned incident #{$medIncidentId2} to Medical unit #1");
} else {
    fail("Failed to assign incident #{$medIncidentId2}: " . json_encode($resAssign));
}

// Medical responder checks assigned cases: GET /api/responders/my-assignments
$resMyAssigned1 = apiCall("{$baseUrl}/api/responders/my-assignments", 'GET', null, $medSession['cookieFile'], $medSession['csrf']);
if ($resMyAssigned1['code'] === 200) {
    $assignedIncidentIds = array_column($resMyAssigned1['body']['data'] ?? [], 'incident_id');
    if (in_array($medIncidentId2, $assignedIncidentIds, true)) {
        pass("Assigned incident #{$medIncidentId2} appears in 'My Assigned Incident Cases'");
    } else {
        fail("Assigned incident #{$medIncidentId2} not found in my-assignments");
    }
} else {
    fail("Failed to query /api/responders/my-assignments");
}

// Medical responder resolves assigned incident #medIncidentId2
$resResolve2 = apiCall("{$baseUrl}/api/incidents/{$medIncidentId2}/status", 'PUT', [
    'status' => 'resolved',
    'notes' => 'Athlete treated with cold compress and rehydration. Cleared by paramedic.',
], $medSession['cookieFile'], $medSession['csrf']);

if ($resResolve2['code'] === 200) {
    pass("Medical responder resolved assigned incident #{$medIncidentId2} (HTTP 200)");
} else {
    fail("Failed to resolve assigned incident: " . json_encode($resResolve2));
}

// Query GET /api/responders/my-assignments again — MUST NOT contain resolved incident
$resMyAssigned2 = apiCall("{$baseUrl}/api/responders/my-assignments", 'GET', null, $medSession['cookieFile'], $medSession['csrf']);
if ($resMyAssigned2['code'] === 200) {
    $assignedIncidentIdsAfter = array_column($resMyAssigned2['body']['data'] ?? [], 'incident_id');
    if (!in_array($medIncidentId2, $assignedIncidentIdsAfter, true)) {
        pass("Resolved incident #{$medIncidentId2} is completely filtered out from 'My Assigned Incident Cases'");
    } else {
        fail("Resolved incident #{$medIncidentId2} still present in 'My Assigned Incident Cases'!");
    }
} else {
    fail("Failed to query /api/responders/my-assignments after resolution");
}

// --- 5. Database Historical Record Preservation ---
echo "\n--- 5. Database Historical Record Preservation ---\n";
$stmtInc = $pdo->prepare("SELECT * FROM incidents WHERE id = :id");
$stmtInc->execute([':id' => $medIncidentId2]);
$incRow = $stmtInc->fetch();

if ($incRow && $incRow['status'] === 'resolved') {
    pass("Incident record #{$medIncidentId2} preserved in database with status='resolved'");
} else {
    fail("Incident record missing or has incorrect status in database");
}

// Verify assignment status is completed
$stmtAss = $pdo->prepare("SELECT assignment_status, completed_at FROM incident_assignments WHERE incident_id = :id AND responder_id = 1");
$stmtAss->execute([':id' => $medIncidentId2]);
$assRow = $stmtAss->fetch();

if ($assRow && $assRow['assignment_status'] === 'completed' && !empty($assRow['completed_at'])) {
    pass("Incident assignment preserved with status='completed' and timestamp recorded");
} else {
    fail("Assignment status not properly completed in database");
}

// Verify audit trail and history updates
$stmtUpd = $pdo->prepare("SELECT COUNT(*) FROM incident_updates WHERE incident_id = :id AND new_status = 'resolved'");
$stmtUpd->execute([':id' => $medIncidentId2]);
if ((int) $stmtUpd->fetchColumn() > 0) {
    pass("Incident chronological updates log preserved resolution transition");
} else {
    fail("Incident resolution update entry missing in incident_updates");
}

// Historical lookup by ID still works
$resShow = apiCall("{$baseUrl}/api/incidents/{$medIncidentId2}", 'GET', null, $medSession['cookieFile'], $medSession['csrf']);
if ($resShow['code'] === 200 && ($resShow['body']['data']['status'] ?? '') === 'resolved') {
    pass("Direct case notes / historical lookup GET /api/incidents/{id} succeeds with full data");
} else {
    fail("Direct case lookup failed for resolved incident");
}

// --- 6. Explicit status filter support ---
echo "\n--- 6. Explicit Status Filter Queries ---\n";
$resExplicit = apiCall("{$baseUrl}/api/incidents?status=resolved", 'GET', null, $medSession['cookieFile'], $medSession['csrf']);
if ($resExplicit['code'] === 200) {
    $resolvedIds = array_column($resExplicit['body']['data'] ?? [], 'id');
    if (in_array($medIncidentId2, $resolvedIds, true)) {
        pass("Explicit GET /api/incidents?status=resolved correctly includes resolved records");
    } else {
        fail("Explicit status query did not return resolved record");
    }
} else {
    fail("Failed to query with ?status=resolved");
}

// --- 7. SOS Independence Verification ---
echo "\n--- 7. Emergency SOS Alert Independence ---\n";
$resSos = apiCall("{$baseUrl}/api/sos/trigger", 'POST', [
    'locationName' => 'Idea Lab',
    'emergencyType' => 'Medical Emergency',
], $medSession['cookieFile'], $medSession['csrf']);
if ($resSos['code'] === 201) {
    $sosId = (int) $resSos['body']['data']['id'];
    pass("Dispatched SOS alert #{$sosId}");
    
    // Check active SOS feed
    $resActiveSos = apiCall("{$baseUrl}/api/sos/active", 'GET', null, $medSession['cookieFile'], $medSession['csrf']);
    $activeSosIds = array_column($resActiveSos['body']['data'] ?? [], 'id');
    if (in_array($sosId, $activeSosIds, true)) {
        pass("Active SOS #{$sosId} displayed in responder active alert stream independently of incident resolution");
    } else {
        fail("Active SOS not found in feed");
    }

    // Clean up test SOS
    apiCall("{$baseUrl}/api/sos/{$sosId}/resolve", 'POST', ['resolutionNotes' => 'Test teardown'], $medSession['cookieFile'], $medSession['csrf']);
} else {
    fail("Failed to create test SOS alert");
}

echo "\n======================================================================\n";
echo " Test Summary: {$passed} Passed, {$failed} Failed\n";
echo "======================================================================\n";

if ($failed > 0) {
    exit(1);
}
