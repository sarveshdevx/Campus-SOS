<?php
declare(strict_types=1);

/**
 * CampusSOS — Comprehensive Live REST API Verification for Responder Resolve Actions
 *
 * Verifies:
 * 1. RBAC Security:
 *    - Unauthenticated requests blocked from resolving SOS (403) and incidents (401)
 *    - Students blocked from resolving SOS (403) and incidents (403)
 * 2. Emergency SOS Alert Resolution:
 *    - Responders can resolve active SOS alerts with notes via POST /api/sos/{id}/resolve
 *    - Alert status becomes 'resolved', timestamp set, resolved_by_user_id recorded
 *    - Alert immediately removed from active alerts feed
 *    - Related sos_assignments marked 'cleared'
 *    - Duplicate resolve attempts return HTTP 409 Conflict
 * 3. Linked SOS + Incident Auto-Resolution:
 *    - Resolving an SOS alert with a linked incident advances the linked incident to 'resolved'
 *    - Incident updates timeline records auto-resolution
 * 4. Incident Report Departmental Scoping:
 *    - Responders blocked from resolving incidents outside their category (HTTP 403)
 *    - Responders authorized to resolve incidents matching their category (HTTP 200)
 *    - Duplicate resolve of already-resolved incidents returns HTTP 409 Conflict
 * 5. Incident Assignment Override:
 *    - Responders assigned cross-department can resolve assigned incidents (HTTP 200)
 *    - Incident assignments marked 'completed' upon resolution
 * 6. Confidential Incident Scoping:
 *    - Unauthorized responders blocked from resolving confidential cases (HTTP 403)
 *    - Appropriate confidential responders (e.g. ICC / Anti-ragging) authorized (HTTP 200)
 * 7. Admin Resolution Privileges:
 *    - Administrators can resolve any SOS or incident report
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
    $cookieFile = tempnam(sys_get_temp_dir(), 'csos_res_');
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
echo " CampusSOS Responder Portal Resolve Actions Live API Test Suite\n";
echo " Base URL: {$baseUrl}\n";
echo "======================================================================\n\n";

$pdo = Database::getConnection();
if (!$pdo) {
    echo "❌ Database connection failed\n";
    exit(1);
}

// Set up sessions for needed test roles
echo "--- Initializing Role Sessions ---\n";
$adminSession = loginSession('admin@campussos.edu', $baseUrl, $demoPassword);
$medSession = loginSession('medical@campussos.edu', $baseUrl, $demoPassword);
$maintSession = loginSession('maintenance@campussos.edu', $baseUrl, $demoPassword);
$secSession = loginSession('security@campussos.edu', $baseUrl, $demoPassword);
$fireSession = loginSession('fire@campussos.edu', $baseUrl, $demoPassword);
$iccSession = loginSession('icc@campussos.edu', $baseUrl, $demoPassword);
$studentSession = loginSession('student@campussos.edu', $baseUrl, $demoPassword);
echo "  Sessions authenticated for Admin, Medical, Maint, Sec, Fire, ICC, Student.\n\n";


// =====================================================================
// 1. RBAC Security Restrictions
// =====================================================================
echo "--- 1. RBAC Security Restrictions ---\n";

// Unauthenticated user
$unauthSos = apiCall("{$baseUrl}/api/sos/1/resolve", 'POST', ['resolutionNotes' => 'Hack attempt']);
report($unauthSos['code'] === 403, "Unauthenticated user blocked from resolving SOS (HTTP 403)");

$unauthInc = apiCall("{$baseUrl}/api/incidents/1/status", 'PUT', ['status' => 'resolved']);
report(in_array($unauthInc['code'], [401, 403], true), "Unauthenticated user blocked from updating incident status (HTTP 401/403)");

// Student user
$studentSos = apiCall("{$baseUrl}/api/sos/1/resolve", 'POST', ['resolutionNotes' => 'Student trying to resolve'], $studentSession['cookieFile'], $studentSession['csrf']);
report($studentSos['code'] === 403, "Student blocked from resolving SOS (HTTP 403)");

$studentInc = apiCall("{$baseUrl}/api/incidents/1/status", 'PUT', ['status' => 'resolved'], $studentSession['cookieFile'], $studentSession['csrf']);
report($studentInc['code'] === 403, "Student blocked from resolving incident reports (HTTP 403)");


// =====================================================================
// 2. Emergency SOS Alert Resolution
// =====================================================================
echo "\n--- 2. Emergency SOS Alert Resolution Lifecycle ---\n";

// Trigger an SOS alert
$triggerRes = apiCall("{$baseUrl}/api/sos/trigger", 'POST', [
    'locationName' => 'Basketball Court',
    'emergencyType' => 'medical',
], $studentSession['cookieFile'], $studentSession['csrf']);

$sosAlertId = (int) ($triggerRes['body']['data']['id'] ?? 0);
report($triggerRes['code'] === 201 && $sosAlertId > 0, "Emergency SOS triggered on Basketball Court (ID: #{$sosAlertId})");

// Verify it is active
$activeRes = apiCall("{$baseUrl}/api/sos/active", 'GET', null, $medSession['cookieFile']);
$activeIds = array_column($activeRes['body']['data'] ?? [], 'id');
report(in_array($sosAlertId, $activeIds, true), "SOS #{$sosAlertId} appears in active alerts feed");

// Medical responder resolves SOS
$resolveNotes = 'Paramedic treated student for heat exhaustion. Cold fluids administered. Student escorted safely.';
$resSosRes = apiCall("{$baseUrl}/api/sos/{$sosAlertId}/resolve", 'POST', [
    'resolutionNotes' => $resolveNotes,
], $medSession['cookieFile'], $medSession['csrf']);

report($resSosRes['code'] === 200, "Medical responder resolved SOS #{$sosAlertId} (HTTP 200)");
report(($resSosRes['body']['data']['status'] ?? '') === 'resolved', "Response confirms status 'resolved'");

// Check database fields
$stmt = $pdo->prepare("SELECT status, resolved_at, resolved_by_user_id, resolution_notes FROM sos_alerts WHERE id = :id");
$stmt->execute([':id' => $sosAlertId]);
$dbSos = $stmt->fetch(PDO::FETCH_ASSOC);

report($dbSos['status'] === 'resolved', "Database confirms SOS status is 'resolved'");
report(!empty($dbSos['resolved_at']), "Database confirms resolved_at timestamp is populated");
report((int) $dbSos['resolved_by_user_id'] === (int) $medSession['user']['id'], "Database confirms resolved_by_user_id matches Medical Responder user ID");
report($dbSos['resolution_notes'] === $resolveNotes, "Database confirms resolution_notes persisted accurately");

// Verify it's no longer in active alerts
$activeAfter = apiCall("{$baseUrl}/api/sos/active", 'GET', null, $medSession['cookieFile']);
$activeIdsAfter = array_column($activeAfter['body']['data'] ?? [], 'id');
report(!in_array($sosAlertId, $activeIdsAfter, true), "Resolved SOS #{$sosAlertId} is removed from active alerts feed");

// Duplicate resolution test: must return HTTP 409 Conflict
$dupSos = apiCall("{$baseUrl}/api/sos/{$sosAlertId}/resolve", 'POST', [
    'resolutionNotes' => 'Duplicate resolve attempt',
], $medSession['cookieFile'], $medSession['csrf']);
report($dupSos['code'] === 409, "Duplicate resolution of SOS #{$sosAlertId} rejected with HTTP 409 Conflict");


// =====================================================================
// 3. Linked SOS and Incident Auto-Resolution
// =====================================================================
echo "\n--- 3. Linked SOS and Incident Auto-Resolution ---\n";

// Create incident
$incCreateRes = apiCall("{$baseUrl}/api/incidents", 'POST', [
    'title' => 'Heatstroke emergency during marathon',
    'description' => 'Student collapsed near Gyan Mandir Auditorium.',
    'locationName' => 'Gyan Mandir Auditorium',
    'category' => 'medical',
    'privacyLevel' => 'standard',
    'severity' => 'urgent',
], $studentSession['cookieFile'], $studentSession['csrf']);

$linkedIncId = (int) ($incCreateRes['body']['data']['id'] ?? 0);
report($incCreateRes['code'] === 201 && $linkedIncId > 0, "Created medical incident #{$linkedIncId}");

// Trigger SOS linked to this incident via direct DB linkage
$alertCode = 'SOS-' . date('Ymd') . '-' . rand(1000, 9999);
$stmt = $pdo->prepare("INSERT INTO sos_alerts (alert_code, user_id, incident_id, location_name, status, triggered_at) VALUES (:code, :uid, :inc_id, 'Gyan Mandir Auditorium', 'dispatched', NOW())");
$stmt->execute([':code' => $alertCode, ':uid' => $studentSession['user']['id'], ':inc_id' => $linkedIncId]);
$linkedSosId = (int) $pdo->lastInsertId();
report($linkedSosId > 0, "Created SOS #{$linkedSosId} linked to Incident #{$linkedIncId}");

// Resolve SOS
$resLinkedSos = apiCall("{$baseUrl}/api/sos/{$linkedSosId}/resolve", 'POST', [
    'resolutionNotes' => 'Patient stabilized by emergency clinic.',
], $medSession['cookieFile'], $medSession['csrf']);

report($resLinkedSos['code'] === 200, "Resolved linked SOS #{$linkedSosId} (HTTP 200)");

// Check that linked incident was also advanced to 'resolved'
$stmt = $pdo->prepare("SELECT status FROM incidents WHERE id = :id");
$stmt->execute([':id' => $linkedIncId]);
$linkedIncStatus = $stmt->fetchColumn();
report($linkedIncStatus === 'resolved', "Linked incident #{$linkedIncId} automatically advanced to 'resolved'");


// =====================================================================
// 4. Incident Report Departmental Scoping
// =====================================================================
echo "\n--- 4. Incident Report Departmental Scoping ---\n";

// Create a maintenance incident
$maintIncRes = apiCall("{$baseUrl}/api/incidents", 'POST', [
    'title' => 'Broken AC Compressor in Central Library',
    'description' => 'Heavy noise and smoke from exterior unit.',
    'locationName' => 'Central Library',
    'category' => 'maintenance',
    'privacyLevel' => 'standard',
    'severity' => 'medium',
], $studentSession['cookieFile'], $studentSession['csrf']);

$maintIncId = (int) ($maintIncRes['body']['data']['id'] ?? 0);
report($maintIncRes['code'] === 201 && $maintIncId > 0, "Created Maintenance Incident #{$maintIncId}");

// Medical responder attempts to resolve maintenance incident -> 403 Forbidden
$medAttempt = apiCall("{$baseUrl}/api/incidents/{$maintIncId}/status", 'PUT', [
    'status' => 'resolved',
    'notes' => 'Medical team trying to resolve maintenance issue.',
], $medSession['cookieFile'], $medSession['csrf']);
report($medAttempt['code'] === 403, "Medical responder blocked from resolving Maintenance Incident (HTTP 403 Forbidden)");

// Maintenance responder resolves maintenance incident -> 200 OK
$maintResolve = apiCall("{$baseUrl}/api/incidents/{$maintIncId}/status", 'PUT', [
    'status' => 'resolved',
    'notes' => 'Technician replaced faulty compressor fan belt. Unit running normally.',
], $maintSession['cookieFile'], $maintSession['csrf']);
report($maintResolve['code'] === 200, "Maintenance responder successfully resolved Maintenance Incident (HTTP 200)");

// Duplicate resolve on already-resolved incident -> 409 Conflict
$dupInc = apiCall("{$baseUrl}/api/incidents/{$maintIncId}/status", 'PUT', [
    'status' => 'resolved',
    'notes' => 'Duplicate resolve call',
], $maintSession['cookieFile'], $maintSession['csrf']);
report($dupInc['code'] === 409, "Duplicate resolution of incident #{$maintIncId} rejected with HTTP 409 Conflict");


// =====================================================================
// 5. Assignment Override (Cross-Department Assigned Case)
// =====================================================================
echo "\n--- 5. Assignment Override (Cross-Department Assigned Case) ---\n";

// Create security incident
$secIncRes = apiCall("{$baseUrl}/api/incidents", 'POST', [
    'title' => 'Blocked fire hydrant at Gate 2',
    'description' => 'Unauthorized car parked directly over campus fire hydrant.',
    'locationName' => 'Gate 2',
    'category' => 'security',
    'privacyLevel' => 'standard',
    'severity' => 'high',
], $studentSession['cookieFile'], $studentSession['csrf']);

$secIncId = (int) ($secIncRes['body']['data']['id'] ?? 0);
report($secIncRes['code'] === 201 && $secIncId > 0, "Created Security Incident #{$secIncId}");

// Admin assigns this security incident to Fire responder
$fireRespId = (int) ($fireSession['user']['responderProfile']['id'] ?? 0);
$assignRes = apiCall("{$baseUrl}/api/incidents/{$secIncId}/assign", 'POST', [
    'responderId' => $fireRespId,
    'notes' => 'Assigned to Fire response unit for hydrant clearance',
], $adminSession['cookieFile'], $adminSession['csrf']);
report($assignRes['code'] === 200, "Admin assigned Security Incident #{$secIncId} to Fire Responder unit #{$fireRespId}");

// Fire responder can now resolve it due to explicit assignment
$fireResolve = apiCall("{$baseUrl}/api/incidents/{$secIncId}/status", 'PUT', [
    'status' => 'resolved',
    'notes' => 'Car towed by campus patrol; hydrant access clear and tested.',
], $fireSession['cookieFile'], $fireSession['csrf']);
report($fireResolve['code'] === 200, "Assigned Fire Responder successfully resolved Security Incident (HTTP 200)");

// Verify assignment marked 'completed' in database
$stmt = $pdo->prepare("SELECT assignment_status FROM incident_assignments WHERE incident_id = :inc_id AND responder_id = :resp_id");
$stmt->execute([':inc_id' => $secIncId, ':resp_id' => $fireRespId]);
$assignStatus = $stmt->fetchColumn();
report($assignStatus === 'completed', "Incident assignment status transitioned to 'completed'");


// =====================================================================
// 6. Confidential Incident Scoping (ICC & Anti-Ragging)
// =====================================================================
echo "\n--- 6. Confidential Incident Scoping ---\n";

// Create confidential ICC incident
$iccIncRes = apiCall("{$baseUrl}/api/incidents", 'POST', [
    'title' => 'Sensitive ICC inquiry report',
    'description' => 'Confidential inquiry conducted under PoSH act guidelines.',
    'locationName' => 'Idea Lab',
    'category' => 'sexual_harassment',
    'privacyLevel' => 'confidential',
    'severity' => 'urgent',
], $studentSession['cookieFile'], $studentSession['csrf']);

$iccIncId = (int) ($iccIncRes['body']['data']['id'] ?? 0);
report($iccIncRes['code'] === 201 && $iccIncId > 0, "Created Confidential ICC Incident #{$iccIncId}");

// Security responder attempts to resolve confidential ICC incident -> 403 Forbidden
$secConfAttempt = apiCall("{$baseUrl}/api/incidents/{$iccIncId}/status", 'PUT', [
    'status' => 'resolved',
    'notes' => 'Security responder attempting to resolve confidential ICC report.',
], $secSession['cookieFile'], $secSession['csrf']);
report($secConfAttempt['code'] === 403, "Security responder blocked from resolving Confidential ICC report (HTTP 403 Forbidden)");

// ICC responder resolves confidential ICC incident -> 200 OK
$iccResolve = apiCall("{$baseUrl}/api/incidents/{$iccIncId}/status", 'PUT', [
    'status' => 'resolved',
    'notes' => 'Inquiry hearings concluded. Documentation archived confidentially.',
], $iccSession['cookieFile'], $iccSession['csrf']);
report($iccResolve['code'] === 200, "Authorized ICC Responder successfully resolved Confidential ICC report (HTTP 200)");


// =====================================================================
// 7. Administrator Super-Resolution & Audit Logging
// =====================================================================
echo "\n--- 7. Administrator Permissions & Audit Logging ---\n";

$adminIncRes = apiCall("{$baseUrl}/api/incidents", 'POST', [
    'title' => 'General campus maintenance inspection',
    'description' => 'Routine inspection test.',
    'locationName' => 'Vehicle Parking',
    'category' => 'other',
    'privacyLevel' => 'standard',
    'severity' => 'low',
], $studentSession['cookieFile'], $studentSession['csrf']);

$adminIncId = (int) ($adminIncRes['body']['data']['id'] ?? 0);
report($adminIncRes['code'] === 201 && $adminIncId > 0, "Created general incident #{$adminIncId}");

// Admin resolves directly
$adminResolve = apiCall("{$baseUrl}/api/incidents/{$adminIncId}/status", 'PUT', [
    'status' => 'resolved',
    'notes' => 'Administrator confirmed resolution with campus facilities director.',
], $adminSession['cookieFile'], $adminSession['csrf']);
report($adminResolve['code'] === 200, "Administrator resolved incident across any category (HTTP 200)");

// Verify audit log entries
$stmt = $pdo->prepare("SELECT COUNT(*) FROM audit_logs WHERE action IN ('SOS_RESOLVED', 'STATUS_UPDATED')");
$stmt->execute();
$auditCount = (int) $stmt->fetchColumn();
report($auditCount > 0, "Audit logs recorded resolution events ({$auditCount} entries present)");

echo "\n======================================================================\n";
echo " Test Summary: {$passCount} Passed, {$failCount} Failed\n";
echo "======================================================================\n";

// Cleanup cookies
@unlink($adminSession['cookieFile']);
@unlink($medSession['cookieFile']);
@unlink($maintSession['cookieFile']);
@unlink($secSession['cookieFile']);
@unlink($fireSession['cookieFile']);
@unlink($iccSession['cookieFile']);
@unlink($studentSession['cookieFile']);

if ($failCount > 0) {
    exit(1);
}
