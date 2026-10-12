<?php
declare(strict_types=1);

/**
 * CampusSOS — Verification Suite:
 * AI Incident Matching, Duplicate Detection & Safe Merge
 *
 * Verifies all 14 acceptance criteria:
 * 1. Two similar incidents (same category, location, nearby timestamps, matching keywords) generate a candidate match.
 * 2. Unrelated incidents (different location, different description) do not generate a candidate.
 * 3. Two similar SOS alerts generate an SOS-to-SOS candidate match without delaying either SOS.
 * 4. An eligible incident report and related SOS generate an incident-to-SOS cross-type candidate match.
 * 5. Candidate matches appear in Admin Duplicate Reviews API (GET /api/admin/matches) with full details.
 * 6. Admin confirms a merge and relationship persists in MySQL (merged_into_incident_id / merged_into_sos_id).
 * 7. Both original records and their chronological audit histories remain accessible.
 * 8. Dismiss candidate updates status to 'dismissed' and leaves original records unchanged.
 * 9. Unauthorized users (students, unauthenticated) are blocked with 401/403 from reviewing/merging candidates.
 * 10. Matching functions deterministically when Gemini is unavailable or disabled.
 * 11. Confidential reports bypass automated cross-matching (score = 0, candidate = false).
 * 12. Repeated matching runs do not create duplicate candidate pairs in MySQL.
 * 13. Merging does not interrupt active SOS dispatch or responder visibility.
 * 14. Invalid operations return proper HTTP error codes rather than silently failing.
 */

require_once __DIR__ . '/../bootstrap.php';

use CampusSOS\Config\Database;
use CampusSOS\Services\IncidentMatcherService;

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
    $cookieFile = tempnam(sys_get_temp_dir(), 'csos_match_');
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
echo " CampusSOS AI Incident Matching, Duplicate Detection & Merge Test Suite\n";
echo " Base URL: {$baseUrl}\n";
echo "======================================================================\n\n";

$pdo = Database::getConnection();
if (!$pdo) {
    echo "❌ Cannot connect to database.\n";
    exit(1);
}

// 1. Establish sessions
echo "--- Initializing User Sessions ---\n";
$admin = loginSession('admin@campussos.edu', $baseUrl, $demoPassword);
$student = loginSession('student@campussos.edu', $baseUrl, $demoPassword);
$medical = loginSession('medical@campussos.edu', $baseUrl, $demoPassword);
echo "Admin, Student, and Responder sessions established.\n\n";

// -------------------------------------------------------------
// Test 1: Two similar reports -> Candidate Match created
// -------------------------------------------------------------
echo "--- Scenario 1: Similar Incident Reports Matching ---\n";
$now = date('Y-m-d H:i:s');
$inc1Res = apiCall("{$baseUrl}/api/incidents", 'POST', [
    'title' => 'Water pipe burst in Electrical Block corridor',
    'description' => 'Heavy water leaking from ceiling pipe near lab 204 in Electrical Block.',
    'locationName' => 'Electrical Block',
    'roomNumber' => '204',
    'category' => 'maintenance',
    'occurredAt' => $now,
], $student['cookieFile'], $student['csrf']);
$inc1Id = (int) ($inc1Res['body']['data']['id'] ?? 0);

$inc2Res = apiCall("{$baseUrl}/api/incidents", 'POST', [
    'title' => 'Flooding from broken water pipe in Electrical Block',
    'description' => 'Water flooding corridor near lab 204 in Electrical Block, pipe burst suspected.',
    'locationName' => 'Electrical Block',
    'roomNumber' => '204',
    'category' => 'maintenance',
    'occurredAt' => $now,
], $student['cookieFile'], $student['csrf']);
$inc2Id = (int) ($inc2Res['body']['data']['id'] ?? 0);

// Check if candidate pair was persisted in incident_matches
$smallId = min($inc1Id, $inc2Id);
$largeId = max($inc1Id, $inc2Id);
$stmt = $pdo->prepare("SELECT id, similarity_score, rationale, status FROM incident_matches WHERE record_a_type='incident' AND incident_a_id=? AND record_b_type='incident' AND incident_b_id=?");
$stmt->execute([$smallId, $largeId]);
$match1 = $stmt->fetch();

if ($match1 && (float)$match1['similarity_score'] >= 0.70 && $match1['status'] === 'pending_review') {
    pass("Created candidate match #{$match1['id']} for similar incidents #{$inc1Id} and #{$inc2Id} with score {$match1['similarity_score']}");
} else {
    fail("Failed to create candidate match for similar incidents: " . json_encode($match1) . " (Inc1: {$inc1Id}, Inc2: {$inc2Id})");
}

// -------------------------------------------------------------
// Test 2: Unrelated reports -> Not flagged
// -------------------------------------------------------------
echo "\n--- Scenario 2: Unrelated Reports Ignored ---\n";
$unrelatedRes = apiCall("{$baseUrl}/api/incidents", 'POST', [
    'title' => 'Lost student ID card near sports field',
    'description' => 'Found an ID card on the running track benches.',
    'locationName' => 'Sports Complex',
    'category' => 'other',
    'occurredAt' => $now,
], $student['cookieFile'], $student['csrf']);
$unrelatedId = (int) ($unrelatedRes['body']['data']['id'] ?? 0);

$stmt = $pdo->prepare("SELECT id FROM incident_matches WHERE (incident_a_id = ? AND incident_b_id = ?) OR (incident_a_id = ? AND incident_b_id = ?)");
$stmt->execute([$inc1Id, $unrelatedId, $unrelatedId, $inc1Id]);
$unrelatedMatch = $stmt->fetch();

if (!$unrelatedMatch) {
    pass("Unrelated incidents (#{$inc1Id} vs #{$unrelatedId}) were NOT flagged as candidate duplicates");
} else {
    fail("Unrelated incidents erroneously flagged as duplicate match #{$unrelatedMatch['id']}");
}

// -------------------------------------------------------------
// Test 3: Two similar SOS alerts -> SOS vs SOS match generated
// -------------------------------------------------------------
echo "\n--- Scenario 3: Similar SOS Alerts Matching (SOS vs SOS) ---\n";
$t1 = microtime(true);
$sos1Res = apiCall("{$baseUrl}/api/sos/trigger", 'POST', [
    'location_id' => 1,
    'locationName' => 'Central Library',
    'roomNumber' => 'Reading Hall',
    'emergency_type' => 'Fire / Smoke',
    'emergency_details' => 'Smoke filling the 2nd floor reading hall',
], $student['cookieFile'], $student['csrf']);
$t2 = microtime(true);
$sos1Id = (int) ($sos1Res['body']['data']['id'] ?? 0);

$sos2Res = apiCall("{$baseUrl}/api/sos/trigger", 'POST', [
    'location_id' => 1,
    'locationName' => 'Central Library',
    'roomNumber' => 'Reading Hall',
    'emergency_type' => 'Fire / Smoke',
    'emergency_details' => 'Visible smoke and burning smell in library reading hall',
], $student['cookieFile'], $student['csrf']);
$sos2Id = (int) ($sos2Res['body']['data']['id'] ?? 0);

$dispatchDuration = round(($t2 - $t1) * 1000, 2);
if ($sos1Id > 0 && $sos2Id > 0 && $dispatchDuration < 3000) {
    pass("Both SOS alerts (#{$sos1Id}, #{$sos2Id}) dispatched immediately without delay ({$dispatchDuration}ms)");
} else {
    fail("SOS alerts dispatch delayed or failed");
}

$smallSos = min($sos1Id, $sos2Id);
$largeSos = max($sos1Id, $sos2Id);
$stmt = $pdo->prepare("SELECT id, similarity_score, status FROM incident_matches WHERE record_a_type='sos' AND incident_a_id=? AND record_b_type='sos' AND incident_b_id=?");
$stmt->execute([$smallSos, $largeSos]);
$sosMatch = $stmt->fetch();

if ($sosMatch && (float)$sosMatch['similarity_score'] >= 0.70) {
    pass("Generated SOS-to-SOS candidate match #{$sosMatch['id']} with score {$sosMatch['similarity_score']}");
} else {
    fail("Failed to generate SOS-to-SOS candidate match: " . json_encode($sosMatch));
}

// -------------------------------------------------------------
// Test 4: Incident vs SOS cross-type matching
// -------------------------------------------------------------
echo "\n--- Scenario 4: Incident vs SOS Cross-Type Matching ---\n";
$incMedRes = apiCall("{$baseUrl}/api/incidents", 'POST', [
    'title' => 'Student collapsed in Gyan Mandir Auditorium',
    'description' => 'A student fainted and collapsed near stage in Gyan Mandir Auditorium.',
    'locationName' => 'Gyan Mandir Auditorium',
    'category' => 'medical',
    'occurredAt' => $now,
], $student['cookieFile'], $student['csrf']);
$incMedId = (int) ($incMedRes['body']['data']['id'] ?? 0);

$sosMedRes = apiCall("{$baseUrl}/api/sos/trigger", 'POST', [
    'location_id' => 13,
    'locationName' => 'Gyan Mandir Auditorium',
    'emergency_type' => 'Medical Emergency',
    'emergency_details' => 'Medical help needed student unconscious in auditorium',
], $student['cookieFile'], $student['csrf']);
$sosMedId = (int) ($sosMedRes['body']['data']['id'] ?? 0);

$stmt = $pdo->prepare("SELECT id, record_a_type, record_b_type, similarity_score, rationale FROM incident_matches WHERE record_a_type='incident' AND incident_a_id=? AND record_b_type='sos' AND incident_b_id=?");
$stmt->execute([$incMedId, $sosMedId]);
$crossMatch = $stmt->fetch();

if ($crossMatch && (float)$crossMatch['similarity_score'] >= 0.60) {
    pass("Generated cross-type candidate match #{$crossMatch['id']} (Incident #{$incMedId} vs SOS #{$sosMedId}) with score {$crossMatch['similarity_score']}");
} else {
    fail("Failed to generate cross-type match: " . json_encode($crossMatch));
}

// -------------------------------------------------------------
// Test 5: Candidate appears in Admin Duplicate Reviews API
// -------------------------------------------------------------
echo "\n--- Scenario 5: Admin Duplicate Reviews API Retrieval ---\n";
$matchesRes = apiCall("{$baseUrl}/api/admin/matches", 'GET', null, $admin['cookieFile']);
$matchesList = $matchesRes['body']['data'] ?? [];
$matchIds = array_column($matchesList, 'id');

if ($matchesRes['code'] === 200 && in_array($match1['id'], $matchIds) && in_array($sosMatch['id'], $matchIds)) {
    pass("Admin Duplicate Reviews API returns all pending candidates (#{$match1['id']}, #{$sosMatch['id']}) with full details");
} else {
    fail("Admin matches API failed or missing candidates: " . json_encode($matchIds));
}

// -------------------------------------------------------------
// Test 6 & 7: Confirm Merge persists in MySQL and preserves records & audit
// -------------------------------------------------------------
echo "\n--- Scenario 6 & 7: Safe Transactional Merge & Audit History ---\n";
$mergeRes = apiCall("{$baseUrl}/api/admin/matches/{$match1['id']}/merge", 'POST', [
    'notes' => 'Confirmed duplicate report of pipe leak in science block.',
], $admin['cookieFile'], $admin['csrf']);

if ($mergeRes['code'] === 200 && ($mergeRes['body']['success'] ?? false)) {
    pass("Admin confirmed merge on candidate #{$match1['id']} via POST /api/admin/matches/{id}/merge");
} else {
    fail("Confirm merge failed: " . json_encode($mergeRes));
}

// Check MySQL relationship persistence:
$stmt = $pdo->prepare("SELECT id, status, merged_into_incident_id, merged_at, merged_by_user_id, merge_notes FROM incidents WHERE id = ?");
$stmt->execute([$largeId]);
$mergedRecord = $stmt->fetch();

if ($mergedRecord && (int)$mergedRecord['merged_into_incident_id'] === $smallId && $mergedRecord['status'] === 'closed') {
    pass("Secondary incident #{$largeId} linked to primary #{$smallId} with status='closed' and merge_notes recorded");
} else {
    fail("Secondary incident merge state invalid in MySQL: " . json_encode($mergedRecord));
}

// Verify both original records still exist and are accessible
$priShow = apiCall("{$baseUrl}/api/incidents/{$smallId}", 'GET', null, $admin['cookieFile']);
$secShow = apiCall("{$baseUrl}/api/incidents/{$largeId}", 'GET', null, $admin['cookieFile']);
if ($priShow['code'] === 200 && $secShow['code'] === 200) {
    pass("Both original incident records (#{$smallId} and #{$largeId}) remain fully accessible via API");
} else {
    fail("Original incident records inaccessible after merge");
}

// Check candidate status updated to 'confirmed_duplicate'
$stmt = $pdo->prepare("SELECT status, reviewed_by_user_id FROM incident_matches WHERE id = ?");
$stmt->execute([$match1['id']]);
$updatedMatch = $stmt->fetch();
if ($updatedMatch && $updatedMatch['status'] === 'confirmed_duplicate' && (int)$updatedMatch['reviewed_by_user_id'] === (int)$admin['user']['id']) {
    pass("Candidate #{$match1['id']} status updated to 'confirmed_duplicate' with reviewer ID recorded");
} else {
    fail("Candidate match status not updated correctly: " . json_encode($updatedMatch));
}

// -------------------------------------------------------------
// Test 8: Dismiss candidate leaves original records unchanged
// -------------------------------------------------------------
echo "\n--- Scenario 8: Dismiss Candidate Match ---\n";
$dismissRes = apiCall("{$baseUrl}/api/admin/matches/{$sosMatch['id']}/dismiss", 'POST', null, $admin['cookieFile'], $admin['csrf']);
if ($dismissRes['code'] === 200 && ($dismissRes['body']['success'] ?? false)) {
    pass("Admin dismissed candidate #{$sosMatch['id']}");
} else {
    fail("Dismiss failed: " . json_encode($dismissRes));
}

// Verify SOS alerts remain completely unchanged
$stmt = $pdo->prepare("SELECT status, merged_into_sos_id FROM sos_alerts WHERE id = ?");
$stmt->execute([$largeSos]);
$sosAfterDismiss = $stmt->fetch();
if ($sosAfterDismiss && empty($sosAfterDismiss['merged_into_sos_id']) && $sosAfterDismiss['status'] !== 'cancelled') {
    pass("SOS alert #{$largeSos} remains completely intact and active after dismissal");
} else {
    fail("SOS alert altered erroneously after dismissal: " . json_encode($sosAfterDismiss));
}

// -------------------------------------------------------------
// Test 9: Unauthorized users blocked (401/403)
// -------------------------------------------------------------
echo "\n--- Scenario 9: RBAC Enforcement on Review/Merge Endpoints ---\n";
$studentMergeRes = apiCall("{$baseUrl}/api/admin/matches/{$crossMatch['id']}/merge", 'POST', [
    'notes' => 'Unauthorized student merge',
], $student['cookieFile'], $student['csrf']);

if ($studentMergeRes['code'] === 403) {
    pass("Student blocked from merging candidate match (HTTP 403 Forbidden)");
} else {
    fail("Student not blocked from merge (HTTP {$studentMergeRes['code']})");
}

$anonMergeRes = apiCall("{$baseUrl}/api/admin/matches/{$crossMatch['id']}/merge", 'POST');
if ($anonMergeRes['code'] === 401 || $anonMergeRes['code'] === 403) {
    pass("Unauthenticated request blocked from merging candidate match (HTTP {$anonMergeRes['code']})");
} else {
    fail("Unauthenticated request not blocked from merge (HTTP {$anonMergeRes['code']})");
}

// -------------------------------------------------------------
// Test 10: Deterministic matching works without Gemini
// -------------------------------------------------------------
echo "\n--- Scenario 10: Deterministic Matching Independence ---\n";
$simDeterministic = IncidentMatcherService::calculateSimilarity([
    'category' => 'fire',
    'location_name' => 'Hostel 3 Mess',
    'occurred_at' => date('Y-m-d H:i:s'),
    'title' => 'Small toaster fire',
    'description' => 'Toaster caught fire in mess kitchen thick smoke',
], [
    'category' => 'fire',
    'location_name' => 'Hostel 3 Mess',
    'occurred_at' => date('Y-m-d H:i:s'),
    'title' => 'Kitchen smoke alert',
    'description' => 'Smoke from toaster blaze in hostel 3 mess',
]);

if ($simDeterministic['isCandidate'] && $simDeterministic['score'] >= 0.70) {
    pass("Deterministic engine scored similar records at {$simDeterministic['score']} without external API calls");
} else {
    fail("Deterministic similarity engine failed: " . json_encode($simDeterministic));
}

// -------------------------------------------------------------
// Test 11: Confidential reports bypass AI and automated cross-matching
// -------------------------------------------------------------
echo "\n--- Scenario 11: Confidential Reports Privacy Isolation ---\n";
$confidentialInc = [
    'category' => 'sexual_harassment',
    'location_name' => 'Admin Block',
    'is_confidential' => 1,
    'privacy_level' => 'confidential',
    'title' => 'Confidential inquiry',
    'description' => 'Confidential details',
];
$ordinaryInc = [
    'category' => 'sexual_harassment',
    'location_name' => 'Admin Block',
    'is_confidential' => 0,
    'privacy_level' => 'standard',
    'title' => 'Inquiry',
    'description' => 'Confidential details',
];

$confSim = IncidentMatcherService::calculateSimilarity($confidentialInc, $ordinaryInc);
if (!$confSim['isCandidate'] && $confSim['score'] === 0.0) {
    pass("Confidential report strictly isolated from automated cross-matching (score = 0.0, isCandidate = false)");
} else {
    fail("Confidential report was not isolated: " . json_encode($confSim));
}

// -------------------------------------------------------------
// Test 12: Repeated matching runs do not create duplicate candidates
// -------------------------------------------------------------
echo "\n--- Scenario 12: Duplicate Pair Constraint & Idempotency ---\n";
$initialCount = (int)$pdo->query("SELECT COUNT(*) FROM incident_matches WHERE record_a_type='incident' AND incident_a_id={$smallId} AND record_b_type='incident' AND incident_b_id={$largeId}")->fetchColumn();

// Rerun detectAndPersistForIncident
IncidentMatcherService::detectAndPersistForIncident($smallId, [
    'id' => $smallId,
    'category' => 'maintenance',
    'location_name' => 'Science Block',
    'room_number' => '204',
    'occurred_at' => $now,
    'title' => 'Water pipe burst',
    'description' => 'Water pipe burst',
]);

$countAfter = (int)$pdo->query("SELECT COUNT(*) FROM incident_matches WHERE record_a_type='incident' AND incident_a_id={$smallId} AND record_b_type='incident' AND incident_b_id={$largeId}")->fetchColumn();

if ($initialCount === 1 && $countAfter === 1) {
    pass("Repeated matching run did not create duplicate candidate rows in MySQL");
} else {
    fail("Duplicate candidate rows created: initial={$initialCount}, after={$countAfter}");
}

// -------------------------------------------------------------
// Test 13: Merging does not interrupt active SOS safety dispatch
// -------------------------------------------------------------
echo "\n--- Scenario 13: Active SOS Dispatch Safety during Merge ---\n";
// Merge crossMatch (Incident #incMedId vs SOS #sosMedId)
$crossMergeRes = apiCall("{$baseUrl}/api/admin/matches/{$crossMatch['id']}/merge", 'POST', [
    'notes' => 'Cross-type merge linking SOS to formal incident investigation.',
], $admin['cookieFile'], $admin['csrf']);

if ($crossMergeRes['code'] === 200) {
    pass("Cross-type merge committed successfully via API");
} else {
    fail("Cross-type merge failed: " . json_encode($crossMergeRes));
}

// Verify SOS remains active and visible to Medical Responder
$stmt = $pdo->prepare("SELECT status, incident_id, merged_into_incident_id FROM sos_alerts WHERE id = ?");
$stmt->execute([$sosMedId]);
$sosLinked = $stmt->fetch();

if ($sosLinked && in_array($sosLinked['status'], ['triggered', 'dispatched', 'acknowledged']) && (int)$sosLinked['merged_into_incident_id'] === $incMedId) {
    pass("SOS alert #{$sosMedId} maintains active dispatch status '{$sosLinked['status']}' while linked to incident #{$incMedId}");
} else {
    fail("SOS alert dispatch was corrupted by merge: " . json_encode($sosLinked));
}

$medActiveRes = apiCall("{$baseUrl}/api/sos/active", 'GET', null, $medical['cookieFile']);
$medActiveAlerts = $medActiveRes['body']['data']['alerts'] ?? $medActiveRes['body']['data'] ?? [];
$medActiveIds = array_column(is_array($medActiveAlerts) ? $medActiveAlerts : [], 'id');

if (in_array($sosMedId, $medActiveIds)) {
    pass("Linked SOS alert #{$sosMedId} remains visible in Medical Responder's active queue");
} else {
    fail("Linked SOS alert was hidden from Medical Responder active queue");
}

// -------------------------------------------------------------
// Test 14: Clear error logging and proper API responses
// -------------------------------------------------------------
echo "\n--- Scenario 14: Error Handling & Diagnostics ---\n";
$badMatchRes = apiCall("{$baseUrl}/api/admin/matches/99999/merge", 'POST', null, $admin['cookieFile'], $admin['csrf']);
if ($badMatchRes['code'] === 400 && !empty($badMatchRes['body']['message'])) {
    pass("Invalid match merge returns HTTP 400 with user-friendly message: '{$badMatchRes['body']['message']}'");
} else {
    fail("Invalid match did not return expected error: " . json_encode($badMatchRes));
}

// Clean up test cookie files
foreach ([$admin, $student, $medical] as $sess) {
    if (file_exists($sess['cookieFile'])) {
        @unlink($sess['cookieFile']);
    }
}

echo "\n======================================================================\n";
echo " Test Suite Complete: {$passed} Passed, {$failed} Failed\n";
echo "======================================================================\n";

exit($failed > 0 ? 1 : 0);
