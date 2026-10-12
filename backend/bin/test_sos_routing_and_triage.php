<?php
declare(strict_types=1);

/**
 * CampusSOS — Verification Suite:
 * Emergency SOS Routing & Responder Assignment
 *
 * Verifies all 15 acceptance criteria:
 * 1. Fire / Smoke -> Admin & Fire Marshal only
 * 2. Vehicle Collision -> Admin & Medical Response only
 * 3. Injury / Accident -> Admin & Medical Response only
 * 4. Medical Emergency -> Admin & Dr. Priya Varma (Medical) only
 * 5. Physical Assault / Fight -> Admin & Officer Rajesh Kumar (Security) only
 * 6. Harassment / Threat -> Admin & Officer Rajesh Kumar (Security) only
 * 7. Suspicious Activity -> Admin & Officer Rajesh Kumar (Security) only
 * 8. Theft / Robbery -> Admin & Officer Rajesh Kumar (Security) only
 * 9. Electrical Hazard or Gas Leak -> Admin & Anil Desai (Maintenance) only
 * 10. Other Emergency -> Admin only (marked Awaiting Admin Triage), 0 responders see it
 * 11. Custom text (e.g. fire/smoke description) -> classified -> Fire Marshal
 * 12. Custom ambiguous text -> Awaiting Admin Triage -> Admin assigns to responder -> visible to responder
 * 13. Resolve SOS -> disappears from active queue, preserved in DB
 * 14. Direct API call by unauthorized responder to view/resolve another department's alert -> 403 Forbidden
 * 15. All alerts remain visible to Admin across all categories
 */

require_once __DIR__ . '/../bootstrap.php';

use CampusSOS\Config\Database;

$baseUrl = 'http://127.0.0.1:8000';
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
        CURLOPT_TIMEOUT => 15,
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
    $cookieFile = tempnam(sys_get_temp_dir(), 'csos_route_');
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
echo " CampusSOS Emergency SOS Routing & Responder Assignment Test Suite\n";
echo " Base URL: {$baseUrl}\n";
echo "======================================================================\n\n";

// Ensure DB is clean of active test alerts before starting
$pdo = Database::getConnection();
if (!$pdo) {
    echo "❌ Cannot connect to database.\n";
    exit(1);
}

// Reset existing active SOS to resolved so tests start with a known state
$pdo->exec("UPDATE sos_alerts SET status = 'resolved' WHERE status IN ('triggered', 'acknowledged', 'dispatched')");

// 1. Establish sessions for all roles
echo "--- Initializing User Sessions ---\n";
$admin = loginSession('admin@campussos.edu', $baseUrl, $demoPassword);
$student = loginSession('student@campussos.edu', $baseUrl, $demoPassword);
$medical = loginSession('medical@campussos.edu', $baseUrl, $demoPassword);
$security = loginSession('security@campussos.edu', $baseUrl, $demoPassword);
$fire = loginSession('fire@campussos.edu', $baseUrl, $demoPassword);
$maintenance = loginSession('maintenance@campussos.edu', $baseUrl, $demoPassword);
$antiRagging = loginSession('antiragging@campussos.edu', $baseUrl, $demoPassword);
$icc = loginSession('icc@campussos.edu', $baseUrl, $demoPassword);

$allResponders = [
    'medical' => $medical,
    'security' => $security,
    'fire' => $fire,
    'maintenance' => $maintenance,
    'anti_ragging' => $antiRagging,
    'icc' => $icc,
];
echo "All 8 roles logged in successfully.\n\n";

function triggerSos(array $student, string $baseUrl, string $emergencyType, ?string $emergencyDetails = null, int $locationId = 1): int {
    $res = apiCall("{$baseUrl}/api/sos/trigger", 'POST', [
        'location_id' => $locationId,
        'room_number' => 'Test-101',
        'emergency_type' => $emergencyType,
        'emergency_details' => $emergencyDetails,
    ], $student['cookieFile'], $student['csrf']);

    if ($res['code'] !== 201) {
        throw new RuntimeException("Failed to trigger SOS: " . json_encode($res));
    }
    $alert = $res['body']['data']['alert'] ?? $res['body']['data'];
    return (int)$alert['id'];
}

function checkActiveVisibility(array $session, string $baseUrl, int $alertId): bool {
    $res = apiCall("{$baseUrl}/api/sos/active", 'GET', null, $session['cookieFile']);
    if ($res['code'] !== 200) {
        return false;
    }
    $alerts = $res['body']['data']['alerts'] ?? $res['body']['data'] ?? [];
    if (!is_array($alerts)) {
        return false;
    }
    foreach ($alerts as $alert) {
        if ((int)$alert['id'] === $alertId) {
            return true;
        }
    }
    return false;
}

// -------------------------------------------------------------
// Test 1: Fire / Smoke -> Admin and Fire Marshal only
// -------------------------------------------------------------
echo "--- Scenario 1: Fire / Smoke Routing ---\n";
$fireAlertId = triggerSos($student, $baseUrl, 'Fire / Smoke', 'Smoke billowing from 3rd floor lab');
if (checkActiveVisibility($admin, $baseUrl, $fireAlertId)) {
    pass("Fire alert #{$fireAlertId} is visible to Admin");
} else {
    fail("Fire alert #{$fireAlertId} NOT visible to Admin");
}
if (checkActiveVisibility($fire, $baseUrl, $fireAlertId)) {
    pass("Fire alert #{$fireAlertId} is visible to Fire Marshal");
} else {
    fail("Fire alert #{$fireAlertId} NOT visible to Fire Marshal");
}
$otherRespondersSeeFire = false;
foreach (['medical', 'security', 'maintenance', 'anti_ragging', 'icc'] as $roleKey) {
    if (checkActiveVisibility($allResponders[$roleKey], $baseUrl, $fireAlertId)) {
        $otherRespondersSeeFire = true;
        fail("Fire alert #{$fireAlertId} erroneously visible to {$roleKey}");
    }
}
if (!$otherRespondersSeeFire) {
    pass("Fire alert #{$fireAlertId} is NOT visible to non-fire responders");
}

// -------------------------------------------------------------
// Test 2: Vehicle Collision -> Admin and Medical Response only
// -------------------------------------------------------------
echo "\n--- Scenario 2: Vehicle Collision Routing ---\n";
$collisionAlertId = triggerSos($student, $baseUrl, 'Vehicle Collision', 'Two vehicles collided at campus gate');
if (checkActiveVisibility($admin, $baseUrl, $collisionAlertId) && checkActiveVisibility($medical, $baseUrl, $collisionAlertId)) {
    pass("Vehicle Collision alert #{$collisionAlertId} is visible to Admin and Medical Response");
} else {
    fail("Vehicle Collision alert #{$collisionAlertId} not visible to Admin and/or Medical Response");
}
$otherRespondersSeeCollision = false;
foreach (['fire', 'security', 'maintenance', 'anti_ragging', 'icc'] as $roleKey) {
    if (checkActiveVisibility($allResponders[$roleKey], $baseUrl, $collisionAlertId)) {
        $otherRespondersSeeCollision = true;
        fail("Vehicle Collision alert #{$collisionAlertId} erroneously visible to {$roleKey}");
    }
}
if (!$otherRespondersSeeCollision) {
    pass("Vehicle Collision alert #{$collisionAlertId} is NOT visible to other responders");
}

// -------------------------------------------------------------
// Test 3: Injury / Accident -> Admin and Medical Response only
// -------------------------------------------------------------
echo "\n--- Scenario 3: Injury / Accident Routing ---\n";
$injuryAlertId = triggerSos($student, $baseUrl, 'Injury / Accident', 'Student slipped on stairs and hurt ankle');
if (checkActiveVisibility($admin, $baseUrl, $injuryAlertId) && checkActiveVisibility($medical, $baseUrl, $injuryAlertId)) {
    pass("Injury / Accident alert #{$injuryAlertId} is visible to Admin and Medical Response");
} else {
    fail("Injury / Accident alert #{$injuryAlertId} not visible to Admin and/or Medical");
}
$otherSeeInjury = false;
foreach (['fire', 'security', 'maintenance', 'anti_ragging', 'icc'] as $roleKey) {
    if (checkActiveVisibility($allResponders[$roleKey], $baseUrl, $injuryAlertId)) {
        $otherSeeInjury = true;
        fail("Injury / Accident alert #{$injuryAlertId} erroneously visible to {$roleKey}");
    }
}
if (!$otherSeeInjury) {
    pass("Injury / Accident alert #{$injuryAlertId} is NOT visible to other responders");
}

// -------------------------------------------------------------
// Test 4: Medical Emergency -> Admin and Dr. Priya Varma (Medical) only
// -------------------------------------------------------------
echo "\n--- Scenario 4: Medical Emergency Routing ---\n";
$medAlertId = triggerSos($student, $baseUrl, 'Medical Emergency', 'Severe allergic reaction reported');
if (checkActiveVisibility($admin, $baseUrl, $medAlertId) && checkActiveVisibility($medical, $baseUrl, $medAlertId)) {
    pass("Medical Emergency alert #{$medAlertId} is visible to Admin and Dr. Priya Varma (Medical)");
} else {
    fail("Medical Emergency alert #{$medAlertId} not visible to Admin and/or Medical");
}
$otherSeeMed = false;
foreach (['fire', 'security', 'maintenance', 'anti_ragging', 'icc'] as $roleKey) {
    if (checkActiveVisibility($allResponders[$roleKey], $baseUrl, $medAlertId)) {
        $otherSeeMed = true;
        fail("Medical alert #{$medAlertId} erroneously visible to {$roleKey}");
    }
}
if (!$otherSeeMed) {
    pass("Medical alert #{$medAlertId} is NOT visible to other responders");
}

// -------------------------------------------------------------
// Test 5: Physical Assault / Fight -> Admin & Officer Rajesh Kumar only
// -------------------------------------------------------------
echo "\n--- Scenario 5: Physical Assault / Fight Routing ---\n";
$fightAlertId = triggerSos($student, $baseUrl, 'Physical Assault / Fight', 'Altercation near dining hall');
if (checkActiveVisibility($admin, $baseUrl, $fightAlertId) && checkActiveVisibility($security, $baseUrl, $fightAlertId)) {
    pass("Assault / Fight alert #{$fightAlertId} is visible to Admin and Security Superintendent");
} else {
    fail("Assault / Fight alert #{$fightAlertId} not visible to Admin and/or Security");
}
$otherSeeFight = false;
foreach (['fire', 'medical', 'maintenance', 'anti_ragging', 'icc'] as $roleKey) {
    if (checkActiveVisibility($allResponders[$roleKey], $baseUrl, $fightAlertId)) {
        $otherSeeFight = true;
        fail("Assault / Fight alert #{$fightAlertId} erroneously visible to {$roleKey}");
    }
}
if (!$otherSeeFight) {
    pass("Assault / Fight alert #{$fightAlertId} is NOT visible to non-security responders");
}

// -------------------------------------------------------------
// Test 6: Harassment / Threat -> Admin & Security only
// -------------------------------------------------------------
echo "\n--- Scenario 6: Harassment / Threat Routing ---\n";
$harassAlertId = triggerSos($student, $baseUrl, 'Harassment / Threat', 'Intimidation reported outside library');
if (checkActiveVisibility($admin, $baseUrl, $harassAlertId) && checkActiveVisibility($security, $baseUrl, $harassAlertId)) {
    pass("Harassment / Threat alert #{$harassAlertId} is visible to Admin and Security");
} else {
    fail("Harassment / Threat alert #{$harassAlertId} not visible to Admin and/or Security");
}
if (!checkActiveVisibility($medical, $baseUrl, $harassAlertId) && !checkActiveVisibility($fire, $baseUrl, $harassAlertId)) {
    pass("Harassment / Threat alert #{$harassAlertId} blocked from other responders");
} else {
    fail("Harassment / Threat alert #{$harassAlertId} leaked to other responders");
}

// -------------------------------------------------------------
// Test 7: Suspicious Activity -> Admin & Security only
// -------------------------------------------------------------
echo "\n--- Scenario 7: Suspicious Activity Routing ---\n";
$suspiciousAlertId = triggerSos($student, $baseUrl, 'Suspicious Activity', 'Unidentified person trespassing behind server room');
if (checkActiveVisibility($admin, $baseUrl, $suspiciousAlertId) && checkActiveVisibility($security, $baseUrl, $suspiciousAlertId)) {
    pass("Suspicious Activity alert #{$suspiciousAlertId} is visible to Admin and Security");
} else {
    fail("Suspicious Activity alert #{$suspiciousAlertId} not visible to Admin and/or Security");
}
if (!checkActiveVisibility($fire, $baseUrl, $suspiciousAlertId) && !checkActiveVisibility($maintenance, $baseUrl, $suspiciousAlertId)) {
    pass("Suspicious Activity alert #{$suspiciousAlertId} blocked from fire and maintenance");
} else {
    fail("Suspicious Activity alert leaked to other responders");
}

// -------------------------------------------------------------
// Test 8: Theft / Robbery -> Admin & Security only
// -------------------------------------------------------------
echo "\n--- Scenario 8: Theft / Robbery Routing ---\n";
$theftAlertId = triggerSos($student, $baseUrl, 'Theft / Robbery', 'Laptop stolen from study carrel');
if (checkActiveVisibility($admin, $baseUrl, $theftAlertId) && checkActiveVisibility($security, $baseUrl, $theftAlertId)) {
    pass("Theft / Robbery alert #{$theftAlertId} is visible to Admin and Security");
} else {
    fail("Theft / Robbery alert #{$theftAlertId} not visible to Admin and/or Security");
}
if (!checkActiveVisibility($medical, $baseUrl, $theftAlertId) && !checkActiveVisibility($fire, $baseUrl, $theftAlertId)) {
    pass("Theft / Robbery alert #{$theftAlertId} blocked from medical and fire responders");
} else {
    fail("Theft / Robbery alert leaked to other responders");
}

// -------------------------------------------------------------
// Test 9: Electrical Hazard or Gas Leak -> Admin & Anil Desai (Maintenance) only
// -------------------------------------------------------------
echo "\n--- Scenario 9: Electrical Hazard & Gas Leak Routing ---\n";
$electricAlertId = triggerSos($student, $baseUrl, 'Electrical Hazard', 'Sparking circuit box on 2nd floor corridor');
$gasAlertId = triggerSos($student, $baseUrl, 'Gas Leak', 'Strong sulfur odor near chemistry laboratory');

if (checkActiveVisibility($admin, $baseUrl, $electricAlertId) && checkActiveVisibility($maintenance, $baseUrl, $electricAlertId)) {
    pass("Electrical Hazard alert #{$electricAlertId} is visible to Admin and Maintenance (Anil Desai)");
} else {
    fail("Electrical Hazard alert not visible to Admin/Maintenance");
}
if (checkActiveVisibility($admin, $baseUrl, $gasAlertId) && checkActiveVisibility($maintenance, $baseUrl, $gasAlertId)) {
    pass("Gas Leak alert #{$gasAlertId} is visible to Admin and Maintenance (Anil Desai)");
} else {
    fail("Gas Leak alert not visible to Admin/Maintenance");
}

if (!checkActiveVisibility($security, $baseUrl, $electricAlertId) && !checkActiveVisibility($fire, $baseUrl, $electricAlertId)) {
    pass("Electrical Hazard alert blocked from security and fire");
} else {
    fail("Electrical Hazard alert leaked to other responders");
}

// -------------------------------------------------------------
// Test 10: Other Emergency -> Admin only, marked 'Awaiting Admin Triage'
// -------------------------------------------------------------
echo "\n--- Scenario 10: Other Emergency Routing & Triage State ---\n";
$otherAlertId = triggerSos($student, $baseUrl, 'Other Emergency', 'Strange buzzing noise and water dripping');
if (checkActiveVisibility($admin, $baseUrl, $otherAlertId)) {
    pass("Other Emergency alert #{$otherAlertId} is visible to Admin");
} else {
    fail("Other Emergency alert #{$otherAlertId} NOT visible to Admin");
}

// Verify triage status in DB / API
$stmt = $pdo->prepare("SELECT triage_status, assigned_responder_id FROM sos_alerts WHERE id = ?");
$stmt->execute([$otherAlertId]);
$dbOther = $stmt->fetch();
if ($dbOther && $dbOther['triage_status'] === 'awaiting_triage' && empty($dbOther['assigned_responder_id'])) {
    pass("Other Emergency alert #{$otherAlertId} is marked 'awaiting_triage' with no initial responder assigned");
} else {
    fail("Other Emergency alert triage state incorrect: " . json_encode($dbOther));
}

$responderSawOther = false;
foreach ($allResponders as $roleKey => $session) {
    if (checkActiveVisibility($session, $baseUrl, $otherAlertId)) {
        $responderSawOther = true;
        fail("Other Emergency alert #{$otherAlertId} was visible to {$roleKey} prior to triage");
    }
}
if (!$responderSawOther) {
    pass("Other Emergency alert #{$otherAlertId} is invisible to all responders prior to Admin triage");
}

// -------------------------------------------------------------
// Test 11: Custom fire text -> AI/heuristic classification -> Fire Marshal
// -------------------------------------------------------------
echo "\n--- Scenario 11: Custom Fire Text Classification ---\n";
$customFireId = triggerSos($student, $baseUrl, 'Custom', 'Blaze spotted in the garbage bin, thick black smoke spreading');
if (checkActiveVisibility($admin, $baseUrl, $customFireId)) {
    pass("Custom fire alert #{$customFireId} is visible to Admin");
} else {
    fail("Custom fire alert #{$customFireId} not visible to Admin");
}

$stmt = $pdo->prepare("SELECT detected_category, triage_status, assigned_responder_id FROM sos_alerts WHERE id = ?");
$stmt->execute([$customFireId]);
$dbCustomFire = $stmt->fetch();
if ($dbCustomFire && (!empty($dbCustomFire['detected_category']) && stripos($dbCustomFire['detected_category'], 'fire') !== false)) {
    pass("Custom text successfully classified into detected_category='{$dbCustomFire['detected_category']}'");
} else {
    fail("Custom fire text failed classification: " . json_encode($dbCustomFire));
}

if (checkActiveVisibility($fire, $baseUrl, $customFireId)) {
    pass("Custom fire alert #{$customFireId} automatically routed and visible to Fire Marshal");
} else {
    fail("Custom fire alert #{$customFireId} NOT visible to Fire Marshal");
}

// -------------------------------------------------------------
// Test 12: Custom ambiguous text -> Awaiting Admin Triage -> Admin assigns -> visible to responder
// -------------------------------------------------------------
echo "\n--- Scenario 12: Custom Ambiguous Text & Admin Manual Triage Assignment ---\n";
$ambiguousId = triggerSos($student, $baseUrl, 'Custom', 'Something weird is happening on the roof, need someone to inspect');

if (checkActiveVisibility($admin, $baseUrl, $ambiguousId)) {
    pass("Ambiguous custom alert #{$ambiguousId} is visible to Admin");
} else {
    fail("Ambiguous custom alert #{$ambiguousId} NOT visible to Admin");
}

$stmt = $pdo->prepare("SELECT triage_status FROM sos_alerts WHERE id = ?");
$stmt->execute([$ambiguousId]);
$dbAmbiguous = $stmt->fetch();
if ($dbAmbiguous && $dbAmbiguous['triage_status'] === 'awaiting_triage') {
    pass("Ambiguous custom alert is marked 'awaiting_triage'");
} else {
    fail("Ambiguous custom alert is NOT awaiting_triage: " . json_encode($dbAmbiguous));
}

// Check maintenance cannot see it yet
if (!checkActiveVisibility($maintenance, $baseUrl, $ambiguousId)) {
    pass("Maintenance responder cannot see ambiguous alert prior to assignment");
} else {
    fail("Maintenance responder saw unassigned ambiguous alert");
}

// Admin manually assigns alert to Maintenance responder (responder ID 4)
$assignRes = apiCall("{$baseUrl}/api/sos/{$ambiguousId}/assign", 'POST', [
    'responder_id' => 4,
    'notes' => 'Admin triaged: Facility maintenance unit please inspect roof access doors.',
], $admin['cookieFile'], $admin['csrf']);

if ($assignRes['code'] === 200 && ($assignRes['body']['success'] ?? false)) {
    pass("Admin successfully assigned ambiguous alert #{$ambiguousId} to Maintenance responder");
} else {
    fail("Admin assign failed: " . json_encode($assignRes));
}

// Now verify it becomes visible to Maintenance responder
if (checkActiveVisibility($maintenance, $baseUrl, $ambiguousId)) {
    pass("Alert #{$ambiguousId} is now visible to Maintenance responder in active queue");
} else {
    fail("Alert #{$ambiguousId} still not visible to Maintenance responder after assignment");
}

// Verify it did not leak to Fire Marshal
if (!checkActiveVisibility($fire, $baseUrl, $ambiguousId)) {
    pass("Alert #{$ambiguousId} did NOT leak to Fire Marshal after maintenance assignment");
} else {
    fail("Alert #{$ambiguousId} erroneously visible to Fire Marshal");
}

// -------------------------------------------------------------
// Test 13: Resolve SOS -> Disappears from active queue, preserved in DB
// -------------------------------------------------------------
echo "\n--- Scenario 13: Resolve SOS & Queue Filtering & DB Preservation ---\n";
$resolveRes = apiCall("{$baseUrl}/api/sos/{$ambiguousId}/resolve", 'POST', [
    'resolution_notes' => 'Roof latch secured and inspected. All normal.',
], $maintenance['cookieFile'], $maintenance['csrf']);

if ($resolveRes['code'] === 200 && ($resolveRes['body']['success'] ?? false)) {
    pass("Maintenance responder successfully resolved SOS alert #{$ambiguousId}");
} else {
    fail("Failed to resolve SOS: " . json_encode($resolveRes));
}

// Verify disappearance from active queue for both responder and admin
if (!checkActiveVisibility($maintenance, $baseUrl, $ambiguousId)) {
    pass("Alert #{$ambiguousId} disappeared from Maintenance active queue");
} else {
    fail("Alert #{$ambiguousId} still present in Maintenance active queue");
}
if (!checkActiveVisibility($admin, $baseUrl, $ambiguousId)) {
    pass("Alert #{$ambiguousId} disappeared from Admin active queue");
} else {
    fail("Alert #{$ambiguousId} still present in Admin active queue");
}

// Verify record is preserved in DB with resolution notes
$stmt = $pdo->prepare("SELECT status, resolution_notes, resolved_at FROM sos_alerts WHERE id = ?");
$stmt->execute([$ambiguousId]);
$dbResolved = $stmt->fetch();
if ($dbResolved && $dbResolved['status'] === 'resolved' && !empty($dbResolved['resolved_at']) && !empty($dbResolved['resolution_notes'])) {
    pass("Resolved alert #{$ambiguousId} safely preserved in database with resolution notes: '{$dbResolved['resolution_notes']}'");
} else {
    fail("Resolved alert record invalid in database: " . json_encode($dbResolved));
}

// -------------------------------------------------------------
// Test 14: Direct API Call by Unauthorized Responder -> HTTP 403
// -------------------------------------------------------------
echo "\n--- Scenario 14: Direct API Authorization Enforcement (HTTP 403) ---\n";
// Let's create an active Security alert (Physical Assault / Fight)
$secAlertId = triggerSos($student, $baseUrl, 'Physical Assault / Fight', 'Security matter only');

// 14a. Medical responder tries to view security alert via GET /api/sos/{id}
$unauthShowRes = apiCall("{$baseUrl}/api/sos/{$secAlertId}", 'GET', null, $medical['cookieFile']);
if ($unauthShowRes['code'] === 403) {
    pass("GET /api/sos/{$secAlertId} by unauthorized responder (Medical) returned 403 Forbidden");
} else {
    fail("GET /api/sos/{$secAlertId} by unauthorized responder returned HTTP {$unauthShowRes['code']} instead of 403");
}

// 14b. Fire responder tries to resolve security alert via POST /api/sos/{id}/resolve
$unauthResolveRes = apiCall("{$baseUrl}/api/sos/{$secAlertId}/resolve", 'POST', [
    'resolution_notes' => 'Unauthorized resolve attempt',
], $fire['cookieFile'], $fire['csrf']);
if ($unauthResolveRes['code'] === 403) {
    pass("POST /api/sos/{$secAlertId}/resolve by unauthorized responder (Fire) returned 403 Forbidden");
} else {
    fail("POST /api/sos/{$secAlertId}/resolve by unauthorized responder returned HTTP {$unauthResolveRes['code']} instead of 403");
}

// 14c. Authorized responder (Security) CAN view it (HTTP 200)
$authShowRes = apiCall("{$baseUrl}/api/sos/{$secAlertId}", 'GET', null, $security['cookieFile']);
if ($authShowRes['code'] === 200 && ($authShowRes['body']['success'] ?? false)) {
    pass("GET /api/sos/{$secAlertId} by authorized responder (Security) returned 200 OK");
} else {
    fail("GET /api/sos/{$secAlertId} by authorized responder failed: " . json_encode($authShowRes));
}

// 14d. Authorized responder (Security) CAN resolve it (HTTP 200)
$authResolveRes = apiCall("{$baseUrl}/api/sos/{$secAlertId}/resolve", 'POST', [
    'resolution_notes' => 'Security cleared the area.',
], $security['cookieFile'], $security['csrf']);
if ($authResolveRes['code'] === 200) {
    pass("POST /api/sos/{$secAlertId}/resolve by authorized responder (Security) returned 200 OK");
} else {
    fail("POST /api/sos/{$secAlertId}/resolve by authorized responder failed: " . json_encode($authResolveRes));
}

// -------------------------------------------------------------
// Test 15: All Alerts Remain Visible to Admin across All Categories
// -------------------------------------------------------------
echo "\n--- Scenario 15: Admin Universal Oversight ---\n";
// Create one alert in each active category
$cat1 = triggerSos($student, $baseUrl, 'Fire / Smoke', 'Admin oversight fire test');
$cat2 = triggerSos($student, $baseUrl, 'Medical Emergency', 'Admin oversight med test');
$cat3 = triggerSos($student, $baseUrl, 'Theft / Robbery', 'Admin oversight sec test');
$cat4 = triggerSos($student, $baseUrl, 'Electrical Hazard', 'Admin oversight maint test');
$cat5 = triggerSos($student, $baseUrl, 'Other Emergency', 'Admin oversight triage test');

$adminAlertsRes = apiCall("{$baseUrl}/api/sos/active", 'GET', null, $admin['cookieFile']);
$alertsList = $adminAlertsRes['body']['data']['alerts'] ?? $adminAlertsRes['body']['data'] ?? [];
$adminAlertIds = array_column(is_array($alertsList) ? $alertsList : [], 'id');

$allVisibleToAdmin = in_array($cat1, $adminAlertIds) &&
                     in_array($cat2, $adminAlertIds) &&
                     in_array($cat3, $adminAlertIds) &&
                     in_array($cat4, $adminAlertIds) &&
                     in_array($cat5, $adminAlertIds);

if ($allVisibleToAdmin) {
    pass("Admin can see ALL active SOS alerts regardless of category (#{$cat1}, #{$cat2}, #{$cat3}, #{$cat4}, #{$cat5})");
} else {
    fail("Admin active queue is missing some alerts: " . json_encode($adminAlertIds));
}

// Clean up test cookies
foreach (array_merge([$admin, $student], array_values($allResponders)) as $sess) {
    if (file_exists($sess['cookieFile'])) {
        @unlink($sess['cookieFile']);
    }
}

echo "\n======================================================================\n";
echo " Test Suite Complete: {$passed} Passed, {$failed} Failed\n";
echo "======================================================================\n";

exit($failed > 0 ? 1 : 0);
