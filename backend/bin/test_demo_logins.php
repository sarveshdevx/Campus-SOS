<?php
declare(strict_types=1);

/**
 * CampusSOS — Comprehensive Demo Account Login & Role Access Verification
 * 
 * Verifies:
 * 1. All 8 demo accounts authenticate successfully with documented credentials
 * 2. Each account receives correct role-specific identity & permissions
 * 3. Invalid credentials remain strictly rejected (401)
 * 4. Role-based access control enforces isolation (Student blocked from Admin endpoints)
 * 5. Unauthenticated requests blocked from protected endpoints (401)
 * 6. Session persistence across subsequent API calls
 * 7. Logout lifecycle destroys session
 */

$baseUrl = 'http://localhost:8000';
$demoPassword = 'Password@2026';

$accounts = [
    ['email' => 'admin@campussos.edu',       'expectedRole' => 'admin'],
    ['email' => 'medical@campussos.edu',     'expectedRole' => 'responder'],
    ['email' => 'security@campussos.edu',    'expectedRole' => 'responder'],
    ['email' => 'fire@campussos.edu',        'expectedRole' => 'responder'],
    ['email' => 'maintenance@campussos.edu', 'expectedRole' => 'responder'],
    ['email' => 'antiragging@campussos.edu', 'expectedRole' => 'responder'],
    ['email' => 'icc@campussos.edu',         'expectedRole' => 'responder'],
    ['email' => 'student@campussos.edu',     'expectedRole' => 'student'],
];

echo "======================================================================\n";
echo " CampusSOS — Demo Accounts & Role Access Verification Suite\n";
echo " Base URL: {$baseUrl}\n";
echo "======================================================================\n\n";

$allPassed = true;

function apiRequest(string $url, string $method = 'GET', ?array $data = null, ?string $cookieFile = null, ?string $csrf = null): array {
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

echo "--- 1. Testing Login For All 8 Demo Accounts ---\n";
foreach ($accounts as $acc) {
    $cookieFile = tempnam(sys_get_temp_dir(), 'csos_test_');
    
    // Get fresh CSRF
    $csrfRes = apiRequest("{$baseUrl}/api/auth/csrf", 'GET', null, $cookieFile);
    $csrf = $csrfRes['body']['data']['csrfToken'] ?? '';
    
    if (empty($csrf)) {
        echo "FAIL: Could not obtain CSRF token for {$acc['email']}\n";
        $allPassed = false;
        @unlink($cookieFile);
        continue;
    }
    
    // Attempt Login
    $loginRes = apiRequest("{$baseUrl}/api/auth/login", 'POST', [
        'email' => $acc['email'],
        'password' => $demoPassword
    ], $cookieFile, $csrf);
    
    $code = $loginRes['code'];
    $body = $loginRes['body'];
    $success = ($code === 200 && ($body['success'] ?? false) === true);
    $returnedRole = $body['data']['user']['role'] ?? '';
    $roleMatches = ($returnedRole === $acc['expectedRole']);
    
    if ($success && $roleMatches) {
        echo "PASS: {$acc['email']} authenticated successfully | Role: {$returnedRole}\n";
    } else {
        echo "FAIL: {$acc['email']} (HTTP {$code}) | Error: " . ($body['message'] ?? 'Unknown error') . "\n";
        $allPassed = false;
    }
    
    @unlink($cookieFile);
}

echo "\n--- 2. Testing Invalid Password Rejection ---\n";
$cookieFile = tempnam(sys_get_temp_dir(), 'csos_bad_');
$csrfRes = apiRequest("{$baseUrl}/api/auth/csrf", 'GET', null, $cookieFile);
$csrf = $csrfRes['body']['data']['csrfToken'] ?? '';
$badLogin = apiRequest("{$baseUrl}/api/auth/login", 'POST', [
    'email' => 'admin@campussos.edu',
    'password' => 'WrongPassword!123'
], $cookieFile, $csrf);

if ($badLogin['code'] === 401 && ($badLogin['body']['errorCode'] ?? '') === 'INVALID_CREDENTIALS') {
    echo "PASS: Invalid password correctly rejected with 401 INVALID_CREDENTIALS\n";
} else {
    echo "FAIL: Invalid password not handled correctly (HTTP {$badLogin['code']})\n";
    $allPassed = false;
}
@unlink($cookieFile);

echo "\n--- 3. Testing Role Authorization Guards (Student -> Admin Endpoint) ---\n";
$studentCookie = tempnam(sys_get_temp_dir(), 'csos_stu_');
$csrfRes = apiRequest("{$baseUrl}/api/auth/csrf", 'GET', null, $studentCookie);
$csrf = $csrfRes['body']['data']['csrfToken'] ?? '';
$stuLogin = apiRequest("{$baseUrl}/api/auth/login", 'POST', [
    'email' => 'student@campussos.edu',
    'password' => $demoPassword
], $studentCookie, $csrf);

// Grab the newly regenerated CSRF token returned upon login
$authenticatedCsrf = $stuLogin['body']['data']['csrfToken'] ?? $csrf;

// Student tries to access admin-only endpoint: /api/admin/metrics
$adminProbe = apiRequest("{$baseUrl}/api/admin/metrics", 'GET', null, $studentCookie);
if ($adminProbe['code'] === 403) {
    echo "PASS: Student correctly blocked from Admin endpoint (HTTP 403 Forbidden)\n";
} else {
    echo "FAIL: Student was not blocked from Admin endpoint (HTTP {$adminProbe['code']})\n";
    $allPassed = false;
}

echo "\n--- 4. Testing Unauthenticated Request Guard ---\n";
$unauthProbe = apiRequest("{$baseUrl}/api/admin/metrics", 'GET');
if (in_array($unauthProbe['code'], [401, 403], true)) {
    echo "PASS: Unauthenticated request blocked from protected endpoint (HTTP {$unauthProbe['code']})\n";
} else {
    echo "FAIL: Unauthenticated request allowed through (HTTP {$unauthProbe['code']})\n";
    $allPassed = false;
}

echo "\n--- 5. Testing Session Persistence & Logout Lifecycle ---\n";
// Probe current user with student session
$meRes = apiRequest("{$baseUrl}/api/auth/me", 'GET', null, $studentCookie);
$userEmail = $meRes['body']['data']['user']['email'] ?? ($meRes['body']['data']['email'] ?? '');
if ($meRes['code'] === 200 && $userEmail === 'student@campussos.edu') {
    echo "PASS: Session successfully persisted for subsequent /api/auth/me request\n";
} else {
    echo "FAIL: Session did not persist (HTTP {$meRes['code']})\n";
    $allPassed = false;
}

// Perform Logout using the post-login CSRF token
$logoutRes = apiRequest("{$baseUrl}/api/auth/logout", 'POST', null, $studentCookie, $authenticatedCsrf);
if ($logoutRes['code'] === 200) {
    echo "PASS: Logout successful\n";
} else {
    echo "FAIL: Logout failed (HTTP {$logoutRes['code']})\n";
    $allPassed = false;
}

// Check session after logout
$afterLogout = apiRequest("{$baseUrl}/api/auth/me", 'GET', null, $studentCookie);
if ($afterLogout['code'] === 401) {
    echo "PASS: Post-logout request correctly returns 401 Unauthorized\n";
} else {
    echo "FAIL: Session still valid after logout (HTTP {$afterLogout['code']})\n";
    $allPassed = false;
}
@unlink($studentCookie);

echo "\n======================================================================\n";
echo " Verification Verdict: " . ($allPassed ? "ALL 8 DEMO ACCOUNTS & GUARDS PASSED" : "FAILED") . "\n";
echo "======================================================================\n";
exit($allPassed ? 0 : 1);
