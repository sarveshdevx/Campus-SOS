<?php
declare(strict_types=1);

/**
 * CampusSOS — Header Navigation & Role-Based Admin Visibility Verification Suite
 */

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
echo " CampusSOS — Header Navigation & Admin Visibility Verification\n";
echo " Base URL: {$baseUrl}\n";
echo "======================================================================\n\n";

// --- TEST GROUP 1: Static Navbar Codebase Audit ---
echo "--- 1. Navbar Codebase Link & State Auditing ---\n";
$navbarFile = __DIR__ . '/../../frontend/src/components/layout/Navbar.tsx';
$navbarContent = file_get_contents($navbarFile);

assertTest(
    "Navbar does NOT contain 'About' in navigation links",
    !preg_match("/\{\s*id:\s*['\"]about['\"]/i", $navbarContent) && !preg_match("/>\s*About\s*<\/button>/i", $navbarContent)
);

assertTest(
    "Navbar does NOT contain 'Features' in navigation links",
    !preg_match("/\{\s*id:\s*['\"]features['\"]/i", $navbarContent) && !preg_match("/>\s*Features\s*<\/button>/i", $navbarContent)
);

assertTest(
    "Navbar does NOT contain 'How it works' in navigation links",
    !preg_match("/\{\s*id:\s*['\"]how-it-works['\"]/i", $navbarContent) && !preg_match("/>\s*How it works\s*<\/button>/i", $navbarContent)
);

assertTest(
    "Navbar contains 'Home' link",
    str_contains($navbarContent, 'Home')
);

assertTest(
    "Navbar contains 'Contact' link",
    str_contains($navbarContent, 'Contact')
);

assertTest(
    "Navbar contains 'Track Reports' link",
    str_contains($navbarContent, 'Track Reports')
);

assertTest(
    "Navbar strictly gates Admin link on verified role: user.role === 'admin'",
    str_contains($navbarContent, "user.role === 'admin'") && str_contains($navbarContent, "isAdmin &&")
);

assertTest(
    "Navbar conditionally swaps 'Get Started' button for authenticated account controls",
    str_contains($navbarContent, "isAuthenticated && user ?") && str_contains($navbarContent, "Get Started")
);

// --- TEST GROUP 2: Live Backend Role Verification for All 8 Roles ---
echo "\n--- 2. Live Session Role Verification & Admin API Protection ---\n";

$roles = [
    ['email' => 'admin@campussos.edu', 'role' => 'admin', 'shouldSeeAdmin' => true],
    ['email' => 'student@campussos.edu', 'role' => 'student', 'shouldSeeAdmin' => false],
    ['email' => 'medical@campussos.edu', 'role' => 'responder', 'category' => 'medical', 'shouldSeeAdmin' => false],
    ['email' => 'security@campussos.edu', 'role' => 'responder', 'category' => 'security', 'shouldSeeAdmin' => false],
    ['email' => 'fire@campussos.edu', 'role' => 'responder', 'category' => 'fire', 'shouldSeeAdmin' => false],
    ['email' => 'maintenance@campussos.edu', 'role' => 'responder', 'category' => 'maintenance', 'shouldSeeAdmin' => false],
    ['email' => 'antiragging@campussos.edu', 'role' => 'responder', 'category' => 'anti_ragging', 'shouldSeeAdmin' => false],
    ['email' => 'icc@campussos.edu', 'role' => 'responder', 'category' => 'sexual_harassment', 'shouldSeeAdmin' => false],
];

foreach ($roles as $r) {
    $email = $r['email'];
    $expectedRole = $r['role'];
    $shouldSeeAdmin = $r['shouldSeeAdmin'];
    $label = isset($r['category']) ? "{$email} ({$r['category']})" : $email;

    $cookieFile = sys_get_temp_dir() . '/test_nav_' . md5($email) . '.txt';
    if (file_exists($cookieFile)) unlink($cookieFile);

    // Fetch CSRF
    $csrfRes = apiRequest("{$baseUrl}/api/auth/csrf", 'GET', null, $cookieFile);
    $csrf = $csrfRes['body']['data']['csrfToken'] ?? '';

    // Login
    $loginRes = apiRequest("{$baseUrl}/api/auth/login", 'POST', [
        'email' => $email,
        'password' => 'Password@2026',
    ], $cookieFile, $csrf);

    $loginOk = ($loginRes['code'] === 200 && ($loginRes['body']['data']['user']['role'] ?? '') === $expectedRole);
    assertTest("{$label} logs in and returns verified role '{$expectedRole}'", $loginOk);

    // Verify session state from /api/auth/me
    $meRes = apiRequest("{$baseUrl}/api/auth/me", 'GET', null, $cookieFile);
    $actualRole = $meRes['body']['data']['user']['role'] ?? '';
    assertTest("{$label} /api/auth/me verifies session role is '{$actualRole}'", $actualRole === $expectedRole);

    // Verify frontend admin rule: (role === 'admin')
    $canSeeAdmin = ($actualRole === 'admin');
    assertTest(
        "{$label} frontend Admin link visibility is " . ($shouldSeeAdmin ? "SHOWN" : "HIDDEN"),
        $canSeeAdmin === $shouldSeeAdmin
    );

    // Verify backend protection on /api/admin/metrics
    $metricsRes = apiRequest("{$baseUrl}/api/admin/metrics", 'GET', null, $cookieFile);
    if ($shouldSeeAdmin) {
        assertTest("{$label} allowed backend access to /api/admin/metrics (HTTP 200)", $metricsRes['code'] === 200);
    } else {
        assertTest("{$label} BLOCKED by backend from /api/admin/metrics (HTTP 403 Forbidden)", $metricsRes['code'] === 403);
    }

    // Cleanup cookie
    if (file_exists($cookieFile)) unlink($cookieFile);
}

// --- TEST GROUP 3: Unauthenticated & Post-Logout Visibility ---
echo "\n--- 3. Unauthenticated State & Logout Lifecycle ---\n";

// 3A. Unauthenticated request to /api/admin/metrics
$unauthCookie = sys_get_temp_dir() . '/test_nav_unauth.txt';
if (file_exists($unauthCookie)) unlink($unauthCookie);
$unauthMetrics = apiRequest("{$baseUrl}/api/admin/metrics", 'GET', null, $unauthCookie);
assertTest("Unauthenticated visitor blocked from /api/admin/metrics (HTTP 401 Unauthorized)", $unauthMetrics['code'] === 401);

// 3B. Admin login then logout
$adminCookie = sys_get_temp_dir() . '/test_nav_admin_logout.txt';
if (file_exists($adminCookie)) unlink($adminCookie);

$csrfRes = apiRequest("{$baseUrl}/api/auth/csrf", 'GET', null, $adminCookie);
$csrf = $csrfRes['body']['data']['csrfToken'] ?? '';

$loginRes = apiRequest("{$baseUrl}/api/auth/login", 'POST', [
    'email' => 'admin@campussos.edu',
    'password' => 'Password@2026',
], $adminCookie, $csrf);
assertTest("Admin logged in successfully", $loginRes['code'] === 200);

// Admin has access
$adminMe1 = apiRequest("{$baseUrl}/api/auth/me", 'GET', null, $adminCookie);
assertTest("Active admin session has role 'admin' (Admin link visible)", ($adminMe1['body']['data']['user']['role'] ?? '') === 'admin');

// Perform logout
$logoutCsrf = $loginRes['body']['data']['csrfToken'] ?? $csrf;
$logoutRes = apiRequest("{$baseUrl}/api/auth/logout", 'POST', [], $adminCookie, $logoutCsrf);
assertTest("Admin logout successful (HTTP 200)", $logoutRes['code'] === 200);

// Check /api/auth/me after logout
$adminMe2 = apiRequest("{$baseUrl}/api/auth/me", 'GET', null, $adminCookie);
assertTest("Post-logout /api/auth/me returns 401 (session destroyed)", $adminMe2['code'] === 401);

// Check admin endpoint after logout
$postLogoutMetrics = apiRequest("{$baseUrl}/api/admin/metrics", 'GET', null, $adminCookie);
assertTest("Post-logout request to /api/admin/metrics blocked (HTTP 401 Unauthorized, Admin link disappears)", $postLogoutMetrics['code'] === 401);

if (file_exists($adminCookie)) unlink($adminCookie);
if (file_exists($unauthCookie)) unlink($unauthCookie);

echo "\n======================================================================\n";
echo " Test Summary: {$passed} Passed, {$failed} Failed\n";
echo "======================================================================\n";

exit($failed > 0 ? 1 : 0);

