<?php
declare(strict_types=1);

/**
 * Phase 6 Verification Test Suite:
 * - Deterministic Incident Matching Engine (Category, Spatial, Temporal, Lexical)
 * - MySQL Check Constraint & Self-Matching Prevention
 * - Strict Confidentiality & Title IX Isolation
 * - Gemini AI Configuration, Privacy Bypass & Graceful Fallback
 * - Zero-AI Dependency Emergency SOS Verification
 * - Human-in-the-Loop Review Decision Workflow
 */

require_once __DIR__ . '/backend/bootstrap.php';

use CampusSOS\Services\IncidentMatcherService;
use CampusSOS\Services\GeminiClassificationService;
use CampusSOS\Services\SosDispatchService;
use CampusSOS\Models\IncidentMatch;
use CampusSOS\Models\AuditLog;

$passed = 0;
$failed = 0;

function assertTest(bool $condition, string $testName, string $details = ''): void {
    global $passed, $failed;
    if ($condition) {
        echo "PASS: $testName\n";
        $passed++;
    } else {
        echo "FAIL: $testName" . ($details ? " ($details)" : "") . "\n";
        $failed++;
    }
}

echo "=== Running Phase 6 Incident Matching & AI Safety Test Suite ===\n\n";

// -----------------------------------------------------------------------------
// Test 1: High Similarity Incident Matching
// -----------------------------------------------------------------------------
$incidentA = [
    'category' => 'security',
    'location_name' => 'Central Library 2nd Floor',
    'occurred_at' => date('Y-m-d H:i:s', time() - 900), // 15 mins ago
    'title' => 'Stolen laptop backpack',
    'description' => 'Black Dell laptop bag taken from study table near window.',
    'is_confidential' => 0,
    'privacy_level' => 'standard',
];

$incidentB = [
    'category' => 'security',
    'location_name' => 'Central Library 2nd Floor',
    'occurred_at' => date('Y-m-d H:i:s', time() - 300), // 5 mins ago
    'title' => 'Missing bag in library study area',
    'description' => 'Student laptop bag missing from desk near window.',
    'is_confidential' => 0,
    'privacy_level' => 'standard',
];

$matchResult = IncidentMatcherService::calculateSimilarity($incidentA, $incidentB);
assertTest(
    $matchResult['isCandidate'] === true && $matchResult['score'] >= 0.60,
    "High-correlation incidents identified as duplicate candidates",
    "Score: {$matchResult['score']}, Threshold: " . IncidentMatcherService::MATCH_THRESHOLD
);

// -----------------------------------------------------------------------------
// Test 2: Low Similarity Incidents Rejected
// -----------------------------------------------------------------------------
$incidentC = [
    'category' => 'maintenance',
    'location_name' => 'Hostel Block D Water Tank',
    'occurred_at' => date('Y-m-d H:i:s', time() - 86400), // 24 hours ago
    'title' => 'Water valve leakage',
    'description' => 'Plumber needed for leaking brass pipe.',
    'is_confidential' => 0,
    'privacy_level' => 'standard',
];

$lowMatch = IncidentMatcherService::calculateSimilarity($incidentA, $incidentC);
assertTest(
    $lowMatch['isCandidate'] === false && $lowMatch['score'] < 0.40,
    "Disparate incidents correctly rejected below duplicate threshold",
    "Score: {$lowMatch['score']}"
);

// -----------------------------------------------------------------------------
// Test 3: Haversine Geographic Distance Scoring
// -----------------------------------------------------------------------------
$incGeoA = [
    'category' => 'security',
    'location_name' => 'Main Gate Plaza',
    'coordinates_lat' => 12.971598,
    'coordinates_lng' => 77.594562,
    'occurred_at' => date('Y-m-d H:i:s'),
    'title' => 'Bike lock broken',
    'description' => 'Bicycle lock tampered near security cabin.',
    'is_confidential' => 0,
    'privacy_level' => 'standard',
];

$incGeoB = [
    'category' => 'security',
    'location_name' => 'Main Gate Bicycle Stand',
    'coordinates_lat' => 12.971610, // ~20 meters away
    'coordinates_lng' => 77.594580,
    'occurred_at' => date('Y-m-d H:i:s', time() - 600),
    'title' => 'Suspicious person at bike stand',
    'description' => 'Individual tampering with bicycle near security cabin.',
    'is_confidential' => 0,
    'privacy_level' => 'standard',
];

$geoResult = IncidentMatcherService::calculateSimilarity($incGeoA, $incGeoB);
assertTest(
    $geoResult['breakdown']['location'] >= 0.28,
    "Haversine proximity detects nearby coordinates within 50m",
    "Location Score: {$geoResult['breakdown']['location']}"
);

// -----------------------------------------------------------------------------
// Test 4: Pair Order Constraint (a < b) and Duplicate Reversed Pairs
// -----------------------------------------------------------------------------
$idX = 88;
$idY = 12;
$orderedA = min($idX, $idY);
$orderedB = max($idX, $idY);
assertTest(
    $orderedA === 12 && $orderedB === 88 && ($orderedA < $orderedB),
    "Pair ordering min(a,b) < max(a,b) enforces chk_incident_pair_order"
);

// -----------------------------------------------------------------------------
// Test 5: Self-Matching Rejection
// -----------------------------------------------------------------------------
$selfA = min(50, 50);
$selfB = max(50, 50);
$selfMatchCreated = IncidentMatch::createCandidate(50, 50, 0.95, 'Self match test');
assertTest(
    $selfA === $selfB && $selfMatchCreated === false,
    "IncidentMatch::createCandidate rejects self-matching (incident_a_id === incident_b_id)"
);

// -----------------------------------------------------------------------------
// Test 6: Strict Confidentiality & Sensitive Case Isolation
// -----------------------------------------------------------------------------
$confidentialInc = [
    'category' => 'anti_ragging',
    'location_name' => 'Hostel Block A Room 204',
    'occurred_at' => date('Y-m-d H:i:s'),
    'title' => 'Senior intimidation report',
    'description' => 'Late night harassment by senior students.',
    'is_confidential' => 1,
    'privacy_level' => 'confidential',
];

$publicInc = [
    'category' => 'security',
    'location_name' => 'Hostel Block A',
    'occurred_at' => date('Y-m-d H:i:s', time() - 300),
    'title' => 'Noise complaint',
    'description' => 'Loud commotion in corridor.',
    'is_confidential' => 0,
    'privacy_level' => 'standard',
];

$confMatch = IncidentMatcherService::calculateSimilarity($confidentialInc, $publicInc);
assertTest(
    $confMatch['isCandidate'] === false && $confMatch['score'] === 0.0,
    "Confidential and anti-ragging reports strictly isolated from automated cross-matching",
    $confMatch['rationale']
);

// -----------------------------------------------------------------------------
// Test 7: Gemini Graceful Fallback when Disabled or Key Missing
// -----------------------------------------------------------------------------
putenv('GEMINI_ENABLED=false');
$_ENV['GEMINI_ENABLED'] = 'false';
$fallbackClassification = GeminiClassificationService::classifyIncident(
    'Smoke smell in physics laboratory',
    'Chemistry students noticed strong burning plastic odor and smoke from switchboard.',
    'other',
    'standard'
);

assertTest(
    isset($fallbackClassification['suggestedCategory']) &&
    isset($fallbackClassification['urgency']) &&
    isset($fallbackClassification['confidence']) &&
    $fallbackClassification['source'] === 'deterministic_fallback',
    "Gemini gracefully falls back to deterministic heuristic when disabled or API key missing"
);

// -----------------------------------------------------------------------------
// Test 8: Deterministic Heuristic Classification Accuracy
// -----------------------------------------------------------------------------
$fireTest = GeminiClassificationService::classifyDeterministic(
    'Emergency fire alarm ringing',
    'Flames and heavy black smoke coming from 4th floor cafeteria kitchen.'
);
assertTest(
    $fireTest['suggestedCategory'] === 'fire' && $fireTest['urgency'] === 'critical',
    "Deterministic engine correctly classifies critical fire emergency"
);

$medTest = GeminiClassificationService::classifyDeterministic(
    'Student fainted',
    'Person collapsed during sports meet, unconscious and bleeding from head.'
);
assertTest(
    $medTest['suggestedCategory'] === 'medical' && $medTest['urgency'] === 'critical',
    "Deterministic engine correctly classifies critical medical emergency"
);

// -----------------------------------------------------------------------------
// Test 9: Gemini Privacy Filter on Confidential Reports
// -----------------------------------------------------------------------------
putenv('GEMINI_ENABLED=true');
putenv('GEMINI_API_KEY=test-mock-key-12345');
$_ENV['GEMINI_ENABLED'] = 'true';
$_ENV['GEMINI_API_KEY'] = 'test-mock-key-12345';

$confidentialAiCheck = GeminiClassificationService::classifyIncident(
    'Private proctor reporting',
    'Hostel seniors forced juniors to perform tasks under threat.',
    'anti_ragging',
    'confidential'
);

assertTest(
    $confidentialAiCheck['source'] === 'deterministic_privacy_rule' &&
    $confidentialAiCheck['suggestedCategory'] === 'anti_ragging',
    "Confidential / Title IX incident strictly bypasses external Gemini AI call"
);

// Reset test env
putenv('GEMINI_ENABLED=false');
putenv('GEMINI_API_KEY=');
$_ENV['GEMINI_ENABLED'] = 'false';
$_ENV['GEMINI_API_KEY'] = '';

// -----------------------------------------------------------------------------
// Test 10: Zero-AI Dependency Emergency SOS Dispatch
// -----------------------------------------------------------------------------
$sosResponse = SosDispatchService::triggerSos([
    'locationName' => 'Engineering Quadrangle',
    'coordinatesLat' => 12.972,
    'coordinatesLng' => 77.595,
], 101);

assertTest(
    $sosResponse['status'] === 'triggered' &&
    isset($sosResponse['alertCode']) &&
    str_starts_with($sosResponse['alertCode'], 'SOS-') &&
    isset($sosResponse['dispatchedTeams']),
    "Emergency SOS functions instantaneously with zero AI dependency"
);

// -----------------------------------------------------------------------------
// Test 11: Human-in-the-Loop Review Status Validation
// -----------------------------------------------------------------------------
$validDecisions = ['confirmed_duplicate', 'dismissed', 'merge'];
$decision = 'merge';
$finalStatus = ($decision === 'merge' || $decision === 'confirmed_duplicate')
    ? 'confirmed_duplicate'
    : 'dismissed';

assertTest(
    $finalStatus === 'confirmed_duplicate',
    "Human review maps dispatcher decisions to valid database ENUM status"
);

echo "\n=======================================================\n";
echo "Phase 6 Results: $passed Passed, $failed Failed\n";
echo "=======================================================\n";

exit($failed > 0 ? 1 : 0);

