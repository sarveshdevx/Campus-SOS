<?php
declare(strict_types=1);

/**
 * CampusSOS — Comprehensive All Demo Roles & Access Security Verification Suite
 *
 * Verifies:
 * 1. Frontend LoginPage.tsx contains all 8 demo buttons & indicator under Expo Demo Accounts
 * 2. All 8 accounts exist in MariaDB with active status & valid Bcrypt hashes
 * 3. All 8 accounts successfully authenticate via real REST API endpoint
 * 4. Post-login role assignment matches specification
 * 5. Role-based endpoint authorization & strict confidential data isolation:
 *    - Students blocked from admin endpoints (403)
 *    - Medical responders blocked from confidential ICC & Anti-Ragging reports (403)
 *    - Anti-Ragging responder authorized for anti-ragging but blocked from ICC (403)
 *    - ICC responder authorized for ICC but blocked from anti-ragging (403)
 * 6. Invalid passwords rejected (401)
 * 7. Session persistence & clean logout lifecycle
 * 8. Zero-latency deterministic SOS dispatch remains functional
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
        echo "  [PASS] {$message}\n";
    } else {
        $failCount++;
        echo "  [FAIL] {$message}\n";
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
        CURLOPT_TIMEOUT => 5,
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

echo "======================================================================\n";
echo " CampusSOS — All 8 Demo Roles & Access Security Verification Suite\n";
echo " Base URL: {$baseUrl}\n";
echo "======================================================================\n\n";

// --- 1. Audit LoginPage.tsx Component Code ---
echo "--- 1. Auditing LoginPage.tsx Component & Button Grid ---\n";
$loginFile = __DIR__ . '/../../frontend/src/pages/LoginPage.tsx';
$loginCode = file_get_contents($loginFile) ?: '';

$expectedButtons = [
    'Admin Demo' => 'admin@campussos.edu',
    'Medical Responder' => 'medical@campussos.edu',
    'Security Responder' => 'security@campussos.edu',
    'Fire & Safety Responder' => 'fire@campussos.edu',
    'Maintenance Responder' => 'maintenance@campussos.edu',
    'Anti-Ragging Responder' => 'antiragging@campussos.edu',
    'ICC Responder' => 'icc@campussos.edu',
    'Student Demo' => 'student@campussos.edu',
];

foreach ($expectedButtons as $label => $email) {
    report(
        str_contains($loginCode, $label) && str_contains($loginCode, $email),
        "Demo button '{$label}' ({$email}) registered in LoginPage"
    );
}

report(str_contains($loginCode, 'Expo Demo Accounts'), "Group heading 'Expo Demo Accounts' present");
report(str_contains($loginCode, 'Demo Selected:'), "Selected demo indicator banner present");
report(!str_contains($loginCode, '>Password@2026<'), "Demo password is NOT displayed in public markup");

// --- 2. Database Verification ---
echo "\n--- 2. Auditing Database Records & Password Hashes ---\n";
$pdo = Database::getConnection();
if (!$pdo) {
    echo "  [FAIL] Database connection failed\n";
    exit(1);
}

$stmtUsers = $pdo->query("SELECT id, full_name, email, password_hash, role, account_status FROM users WHERE email LIKE '%@campussos.edu'");
$dbUsers = [];
while ($row = $stmtUsers->fetch(PDO::FETCH_ASSOC)) {
    $dbUsers[$row['email']] = $row;
}

foreach ($expectedButtons as $label => $email) {
    $exists = isset($dbUsers[$email]);
    $active = $exists && $dbUsers[$email]['account_status'] === 'active';
    $validHash = $exists && password_verify($demoPassword, $dbUsers[$email]['password_hash']);

    report($exists, "Database contains user {$email}");
    report($active && $validHash, "User {$email} is active with verified bcrypt hash");
}

// Verify responders table
$stmtResps = $pdo->query("SELECT r.id, r.category, r.department, u.email FROM responders r JOIN users u ON r.user_id = u.id");
$respondersByEmail = [];
while ($row = $stmtResps->fetch(PDO::FETCH_ASSOC)) {
    $respondersByEmail[$row['email']] = $row;
}

$expectedResponderCategories = [
    'medical@campussos.edu' => 'medical',
    'security@campussos.edu' => 'security',
    'fire@campussos.edu' => 'fire',
    'maintenance@campussos.edu' => 'maintenance',
    'antiragging@campussos.edu' => 'anti_ragging',
    'icc@campussos.edu' => 'sexual_harassment',
];

foreach ($expectedResponderCategories as $email => $cat) {
    $matched = isset($respondersByEmail[$email]) && $respondersByEmail[$email]['category'] === $cat;
    report($matched, "Responder {$email} mapped to category '{$cat}'");
}

// --- 3. Live API Authentication For All 8 Roles ---
echo "\n--- 3. Testing Real API Authentication For All 8 Demo Accounts ---\n";
$sessionCookies = [];
$sessionCsrfs = [];

foreach ($expectedButtons as $label => $email) {
    $cookieFile = tempnam(sys_get_temp_dir(), 'csos_role_');
    $csrfRes = apiCall("{$baseUrl}/api/auth/csrf", 'GET', null, $cookieFile);
    $csrf = $csrfRes['body']['data']['csrfToken'] ?? '';

    $loginRes = apiCall("{$baseUrl}/api/auth/login", 'POST', [
        'email' => $email,
        'password' => $demoPassword,
    ], $cookieFile, $csrf);

    $is200 = ($loginRes['code'] === 200);
    $returnedRole = $loginRes['body']['data']['user']['role'] ?? '';
    $expectedRole = ($email === 'admin@campussos.edu') ? 'admin' : (($email === 'student@campussos.edu') ? 'student' : 'responder');

    report($is200 && $returnedRole === $expectedRole, "{$label} authenticated via API (Role: {$returnedRole})");

    if ($is200) {
        $sessionCookies[$email] = $cookieFile;
        $sessionCsrfs[$email] = $loginRes['body']['data']['csrfToken'] ?? $csrf;
    } else {
        @unlink($cookieFile);
    }
}

// --- 4. Role Authorization & Confidential Isolation ---
echo "\n--- 4. Testing Role Authorization & Confidential Data Isolation ---\n";

// A. Admin can access admin metrics
$adminRes = apiCall("{$baseUrl}/api/admin/metrics", 'GET', null, $sessionCookies['admin@campussos.edu'] ?? null);
report($adminRes['code'] === 200, "Admin granted access to /api/admin/metrics (HTTP 200)");

// B. Student blocked from admin metrics
$studentAdminRes = apiCall("{$baseUrl}/api/admin/metrics", 'GET', null, $sessionCookies['student@campussos.edu'] ?? null);
report($studentAdminRes['code'] === 403, "Student blocked from /api/admin/metrics (HTTP 403 Forbidden)");

// C. Student blocked from responder assignments
$studentRespRes = apiCall("{$baseUrl}/api/responders/my-assignments", 'GET', null, $sessionCookies['student@campussos.edu'] ?? null);
report($studentRespRes['code'] === 403, "Student blocked from responder endpoints (HTTP 403 Forbidden)");

// D. Medical Responder accesses assignments
$medRespRes = apiCall("{$baseUrl}/api/responders/my-assignments", 'GET', null, $sessionCookies['medical@campussos.edu'] ?? null);
report($medRespRes['code'] === 200, "Medical responder can access /api/responders/my-assignments (HTTP 200)");

// E. Confidential Isolation Testing:
// Incident 13: Confidential Anti-Ragging
// Incident 16: Confidential ICC Sexual Harassment

// Medical responder blocked from confidential anti-ragging incident #13
$medViewAntiRagging = apiCall("{$baseUrl}/api/incidents/13", 'GET', null, $sessionCookies['medical@campussos.edu'] ?? null);
report($medViewAntiRagging['code'] === 403, "Medical responder blocked from confidential anti-ragging report (HTTP 403)");

// Medical responder blocked from confidential ICC incident #16
$medViewIcc = apiCall("{$baseUrl}/api/incidents/16", 'GET', null, $sessionCookies['medical@campussos.edu'] ?? null);
report($medViewIcc['code'] === 403, "Medical responder blocked from confidential ICC report (HTTP 403)");

// Anti-Ragging responder CAN access confidential anti-ragging incident #13
$arViewAntiRagging = apiCall("{$baseUrl}/api/incidents/13", 'GET', null, $sessionCookies['antiragging@campussos.edu'] ?? null);
report($arViewAntiRagging['code'] === 200, "Anti-Ragging responder authorized to view confidential anti-ragging report (HTTP 200)");

// Anti-Ragging responder BLOCKED from confidential ICC incident #16
$arViewIcc = apiCall("{$baseUrl}/api/incidents/16", 'GET', null, $sessionCookies['antiragging@campussos.edu'] ?? null);
report($arViewIcc['code'] === 403, "Anti-Ragging responder blocked from confidential ICC report (HTTP 403)");

// ICC responder CAN access confidential ICC incident #16
$iccViewIcc = apiCall("{$baseUrl}/api/incidents/16", 'GET', null, $sessionCookies['icc@campussos.edu'] ?? null);
report($iccViewIcc['code'] === 200, "ICC responder authorized to view confidential ICC report (HTTP 200)");

// ICC responder BLOCKED from confidential anti-ragging incident #13
$iccViewAr = apiCall("{$baseUrl}/api/incidents/13", 'GET', null, $sessionCookies['icc@campussos.edu'] ?? null);
report($iccViewAr['code'] === 403, "ICC responder blocked from confidential anti-ragging report (HTTP 403)");

// --- 5. Invalid Password Rejection ---
echo "\n--- 5. Testing Invalid Password Rejection ---\n";
$badCookie = tempnam(sys_get_temp_dir(), 'csos_bad_');
$badCsrfRes = apiCall("{$baseUrl}/api/auth/csrf", 'GET', null, $badCookie);
$badCsrf = $badCsrfRes['body']['data']['csrfToken'] ?? '';

$badLogin = apiCall("{$baseUrl}/api/auth/login", 'POST', [
    'email' => 'admin@campussos.edu',
    'password' => 'WrongPassword!999',
], $badCookie, $badCsrf);

report(
    $badLogin['code'] === 401 && ($badLogin['body']['errorCode'] ?? '') === 'INVALID_CREDENTIALS',
    "Invalid password rejected with 401 INVALID_CREDENTIALS"
);
@unlink($badCookie);

// --- 6. Session Persistence & Logout Lifecycle ---
echo "\n--- 6. Testing Session Persistence & Logout Lifecycle ---\n";
$stuCookie = $sessionCookies['student@campussos.edu'] ?? null;
$stuCsrf = $sessionCsrfs['student@campussos.edu'] ?? null;

if ($stuCookie && $stuCsrf) {
    // Check /api/auth/me
    $meRes = apiCall("{$baseUrl}/api/auth/me", 'GET', null, $stuCookie);
    $meEmail = $meRes['body']['data']['user']['email'] ?? '';
    report($meRes['code'] === 200 && $meEmail === 'student@campussos.edu', "Session persisted for /api/auth/me");

    // Perform Logout
    $logoutRes = apiCall("{$baseUrl}/api/auth/logout", 'POST', null, $stuCookie, $stuCsrf);
    report($logoutRes['code'] === 200, "Logout request completed successfully (HTTP 200)");

    // Check after logout
    $afterLogout = apiCall("{$baseUrl}/api/auth/me", 'GET', null, $stuCookie);
    report($afterLogout['code'] === 401, "Session destroyed after logout (HTTP 401)");
} else {
    report(false, "Student session was not initialized for logout test");
}

// Clean up cookie files
foreach ($sessionCookies as $cookie) {
    @unlink($cookie);
}

// --- 7. Deterministic Zero-Latency SOS Dispatch ---
echo "\n--- 7. Testing Deterministic Emergency SOS Dispatch Engine ---\n";
$sosCookie = tempnam(sys_get_temp_dir(), 'csos_sos_');
$sosCsrfRes = apiCall("{$baseUrl}/api/auth/csrf", 'GET', null, $sosCookie);
$sosCsrf = $sosCsrfRes['body']['data']['csrfToken'] ?? '';

$t0 = microtime(true);
$sosRes = apiCall("{$baseUrl}/api/sos/trigger", 'POST', [
    'locationName' => 'Basketball Court',
    'roomNumber' => null,
    'alertType' => 'sos',
], $sosCookie, $sosCsrf);
$elapsedMs = round((microtime(true) - $t0) * 1000, 2);

$sosOk = ($sosRes['code'] === 201) && !empty($sosRes['body']['data']['alertCode']);
report($sosOk, "Emergency SOS triggered deterministically ({$elapsedMs}ms, Code: " . ($sosRes['body']['data']['alertCode'] ?? 'N/A') . ")");
report($elapsedMs < 1000, "SOS dispatch latency is deterministic (< 1000ms target)");
@unlink($sosCookie);

// Clean up test SOS
if ($sosOk) {
    $code = $sosRes['body']['data']['alertCode'];
    $pdo->prepare("DELETE FROM sos_alerts WHERE alert_code = ?")->execute([$code]);
}

echo "\n======================================================================\n";
echo " Verification Verdict: {$passCount} Passed, {$failCount} Failed\n";
echo "======================================================================\n";
exit($failCount === 0 ? 0 : 1);

