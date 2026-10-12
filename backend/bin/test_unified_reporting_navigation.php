<?php
declare(strict_types=1);

/**
 * CampusSOS — Unified Reporting Navigation & Confidential Mode Verification Suite
 */

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/Incident.php';
require_once __DIR__ . '/../models/SosAlert.php';
require_once __DIR__ . '/../models/AuditLog.php';
require_once __DIR__ . '/../models/Responder.php';
require_once __DIR__ . '/../services/LocationService.php';
require_once __DIR__ . '/../services/SosDispatchService.php';
require_once __DIR__ . '/../services/IncidentMatcherService.php';

use CampusSOS\Config\Database;
use CampusSOS\Models\Incident;
use CampusSOS\Services\SosDispatchService;
use CampusSOS\Services\IncidentMatcherService;

$baseUrl = 'http://localhost:8000';
$passed = 0;
$failed = 0;

function assertTest(string $name, bool $condition, string $details = ''): void {
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] {$name}\n";
        $passed++;
    } else {
        echo "  [FAIL] {$name}: {$details}\n";
        $failed++;
    }
}

function apiRequest(string $url, string $method = 'GET', ?array $data = null, ?string $cookieFile = null, ?string $csrf = null): array {
    $ch = curl_init($url);
    $headers = ['Accept: application/json'];

    if ($data !== null) {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    if ($csrf) {
        $headers[] = "X-CSRF-Token: {$csrf}";
    }

    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);

    if ($cookieFile) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }

    $body = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'code' => $httpCode,
        'body' => json_decode((string) $body, true) ?? [],
        'raw' => (string) $body,
    ];
}

echo "======================================================================\n";
echo " CampusSOS — Unified Reporting & Navigation Verification Suite\n";
echo "======================================================================\n\n";

// --- 1. Codebase Audit of Navbar.tsx ---
echo "--- 1. Navbar.tsx Link Auditing ---\n";
$navbarCode = file_get_contents(__DIR__ . '/../../frontend/src/components/layout/Navbar.tsx');

assertTest(
    "Navbar desktop contains 'Incident Report' link",
    str_contains($navbarCode, 'Incident Report')
);

assertTest(
    "Navbar highlights 'Incident Report' as active on report tab",
    str_contains($navbarCode, "currentTab === 'report' ? 800 : 600")
);

assertTest(
    "Navbar mobile menu contains 'Incident Report' button",
    preg_match("/handleNavClick\(['\"]report['\"].*>\s*Incident Report\s*<\/button>/s", $navbarCode) === 1
);

assertTest(
    "Navbar desktop link order: Home -> Incident Report -> Contact -> Track Reports",
    preg_match('/Home\s*<\/button>.*Incident Report\s*<\/button>.*Contact\s*<\/button>.*Track Reports\s*<\/button>/s', $navbarCode) === 1
);

assertTest(
    "Admin link strictly gated to verified admin role",
    str_contains($navbarCode, "isAdmin &&") && str_contains($navbarCode, "user.role === 'admin'")
);

// --- 2. Codebase Audit of HomePage.tsx ---
echo "\n--- 2. HomePage.tsx Card Redirection Auditing ---\n";
$homePageCode = file_get_contents(__DIR__ . '/../../frontend/src/pages/HomePage.tsx');

assertTest(
    "Report an Incident card directs to report in standard mode",
    str_contains($homePageCode, "onNavigate('report', { mode: 'standard' })")
);

assertTest(
    "Confidential Complaint card directs to report in confidential mode",
    str_contains($homePageCode, "onNavigate('report', { mode: 'confidential' })")
);

// --- 3. Codebase Audit of ReportIncidentPage.tsx & App.tsx ---
echo "\n--- 3. ReportIncidentPage.tsx & App.tsx Route Sync ---\n";
$reportPageCode = file_get_contents(__DIR__ . '/../../frontend/src/pages/ReportIncidentPage.tsx');
$appCode = file_get_contents(__DIR__ . '/../../frontend/src/App.tsx');

assertTest(
    "App.tsx parses route and mode from URL/searchParams",
    str_contains($appCode, "parseRouteFromLocation") && str_contains($appCode, "mode: 'standard' | 'confidential'")
);

assertTest(
    "App.tsx updates history pushState with /report and /report?mode=confidential",
    str_contains($appCode, "/report?mode=confidential") && str_contains($appCode, "window.history.pushState")
);

assertTest(
    "ReportIncidentPage synchronizes initialMode via useEffect",
    str_contains($reportPageCode, "useEffect") && str_contains($reportPageCode, "initialMode === 'confidential'")
);

assertTest(
    "ReportIncidentPage visibly displays Confidential Reporting Mode banner",
    str_contains($reportPageCode, "Confidential Reporting Mode Active")
);

// --- 4. Live API Incident Submission & Mode Persistence ---
echo "\n--- 4. Live API Incident Submission (Standard vs. Confidential) ---\n";

$cookieFile = sys_get_temp_dir() . '/test_nav_stu.txt';
if (file_exists($cookieFile)) unlink($cookieFile);

$csrfRes = apiRequest("{$baseUrl}/api/auth/csrf", 'GET', null, $cookieFile);
$csrf = $csrfRes['body']['data']['csrfToken'] ?? '';

// Login as student
$loginRes = apiRequest("{$baseUrl}/api/auth/login", 'POST', [
    'email' => 'student@campussos.edu',
    'password' => 'Password@2026',
], $cookieFile, $csrf);
assertTest("Student logged in successfully", $loginRes['code'] === 200);
$authCsrf = $loginRes['body']['data']['csrfToken'] ?? $csrf;

// 4A. Submit Standard Incident Report
$stdTitle = 'Test Standard Water Spill in OAT';
$stdRes = apiRequest("{$baseUrl}/api/incidents", 'POST', [
    'title' => $stdTitle,
    'description' => 'Large puddle near stage creating slip hazard.',
    'category' => 'maintenance',
    'locationName' => 'OAT',
    'isAnonymous' => false,
    'privacyLevel' => 'standard',
], $cookieFile, $authCsrf);

assertTest("Standard incident report submitted (HTTP 201)", $stdRes['code'] === 201);
$stdIncId = (int) ($stdRes['body']['data']['id'] ?? 0);
assertTest("Standard report returned valid incident ID", $stdIncId > 0);

// Verify standard report persistence in DB
$pdo = Database::getConnection();
if ($pdo && $stdIncId > 0) {
    $stmt = $pdo->prepare("SELECT id, privacy_level, category, is_confidential FROM incidents WHERE id = :id");
    $stmt->execute([':id' => $stdIncId]);
    $row = $stmt->fetch();
    assertTest("Standard report persisted with privacy_level='standard'", ($row['privacy_level'] ?? '') === 'standard');
    assertTest("Standard report is_confidential is 0", (int) ($row['is_confidential'] ?? 1) === 0);
    $pdo->exec("DELETE FROM incidents WHERE id = {$stdIncId}");
}

// 4B. Submit Confidential Incident Report
$confTitle = 'Test Confidential Hazing Complaint';
$confRes = apiRequest("{$baseUrl}/api/incidents", 'POST', [
    'title' => $confTitle,
    'description' => 'Senior students demanding junior chores in hostel late at night.',
    'category' => 'anti_ragging',
    'locationName' => 'KBH Hostel — Room 102',
    'roomNumber' => '102',
    'isAnonymous' => false,
    'privacyLevel' => 'confidential',
], $cookieFile, $authCsrf);

assertTest("Confidential incident report submitted (HTTP 201)", $confRes['code'] === 201);
$confIncId = (int) ($confRes['body']['data']['id'] ?? 0);
assertTest("Confidential report returned valid incident ID", $confIncId > 0);

// Verify confidential report persistence in DB
if ($pdo && $confIncId > 0) {
    $stmt = $pdo->prepare("SELECT id, privacy_level, category, is_confidential FROM incidents WHERE id = :id");
    $stmt->execute([':id' => $confIncId]);
    $row = $stmt->fetch();
    assertTest("Confidential report persisted with privacy_level='confidential'", ($row['privacy_level'] ?? '') === 'confidential');
    assertTest("Confidential report is_confidential is 1", (int) ($row['is_confidential'] ?? 0) === 1);
    assertTest("Confidential report persisted category='anti_ragging'", ($row['category'] ?? '') === 'anti_ragging');
    $pdo->exec("DELETE FROM incidents WHERE id = {$confIncId}");
}

// --- 5. Deterministic Emergency SOS Verification (Zero AI Dependency) ---
echo "\n--- 5. Deterministic Emergency SOS Engine (No AI Delay) ---\n";
$sosRes = SosDispatchService::triggerSos([
    'locationName' => 'Basketball Court',
], 1);
assertTest("SOS dispatches deterministically", !empty($sosRes['id']) && $sosRes['status'] === 'triggered');
assertTest("SOS routing specifies instantaneous deterministic latency (12ms)", ($sosRes['dispatchLatencyMs'] ?? 0) === 12);
if ($pdo && !empty($sosRes['id'])) {
    $pdo->exec("DELETE FROM sos_alerts WHERE id = " . (int) $sosRes['id']);
}

// Cleanup student session
if (file_exists($cookieFile)) unlink($cookieFile);

echo "\n======================================================================\n";
echo " Test Summary: {$passed} Passed, {$failed} Failed\n";
echo "======================================================================\n";

exit($failed > 0 ? 1 : 0);
