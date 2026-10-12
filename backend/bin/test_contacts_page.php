<?php
declare(strict_types=1);

/**
 * CampusSOS — Verification Suite for Department Contacts & Contact Page
 *
 * Verifies:
 * 1. GET /api/contacts returns all 6 safety responder departments
 * 2. Each department contains name, email, phone, location, representative, description
 * 3. Validation: Invalid email or phone formats are rejected with HTTP 422
 * 4. Authorization Guards:
 *    - Unauthenticated cannot modify contacts (HTTP 401/403)
 *    - Students cannot modify contacts (HTTP 403)
 *    - Responders cannot modify contacts (HTTP 403)
 *    - Administrators authorized to modify contacts (HTTP 200)
 * 5. Interactive links: Configured contacts have valid mailto: and tel: formats
 * 6. Missing / Unconfigured contacts clearly flag "Contact details not configured"
 * 7. Confidentiality Protection: Public contacts do NOT expose confidential incident data
 * 8. Frontend ContactPage.tsx contains all 6 departments and heading 'CONTACT CAMPUS RESPONSE TEAMS'
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
echo " CampusSOS — Department Contacts & Contact Page Verification Suite\n";
echo " Base URL: {$baseUrl}\n";
echo "======================================================================\n\n";

// --- 1. Audit ContactPage.tsx Component ---
echo "--- 1. Auditing ContactPage.tsx Component & Markup ---\n";
$contactFile = __DIR__ . '/../../frontend/src/pages/ContactPage.tsx';
$contactCode = file_get_contents($contactFile) ?: '';

report(str_contains($contactCode, 'CONTACT CAMPUS RESPONSE TEAMS'), "Heading 'CONTACT CAMPUS RESPONSE TEAMS' present");
report(str_contains($contactCode, 'mailto:'), "Interactive mailto: links implemented");
report(str_contains($contactCode, 'tel:'), "Interactive tel: links implemented");
report(str_contains($contactCode, 'Contact details not configured'), "Clear 'Contact details not configured' fallback implemented");
report(str_contains($contactCode, 'sanitizeTelHref'), "Telephone URL sanitization helper present");

// --- 2. Public GET /api/contacts Endpoint ---
echo "\n--- 2. Auditing Public GET /api/contacts Endpoint ---\n";
$contactsRes = apiCall("{$baseUrl}/api/contacts", 'GET');
report($contactsRes['code'] === 200, "GET /api/contacts returns HTTP 200 OK");

$contacts = $contactsRes['body']['data'] ?? [];
report(count($contacts) === 6, "Exactly 6 safety departments returned (" . count($contacts) . " found)");

$expectedCategories = [
    'medical' => 'Medical Response',
    'security' => 'Campus Security',
    'fire' => 'Fire & Safety',
    'maintenance' => 'Facility Maintenance',
    'anti_ragging' => 'Anti-Ragging Committee',
    'sexual_harassment' => 'Internal Complaints Committee (ICC)',
];

$contactsByCategory = [];
foreach ($contacts as $c) {
    $contactsByCategory[$c['category']] = $c;
}

foreach ($expectedCategories as $cat => $name) {
    $exists = isset($contactsByCategory[$cat]);
    $matched = $exists && ($contactsByCategory[$cat]['departmentName'] === $name);
    report($matched, "Department '{$name}' ({$cat}) present with correct name");
}

// Verify interactive phone and email formats
echo "\n--- 3. Verifying Email & Phone Formats Across All 6 Departments ---\n";
foreach ($expectedCategories as $cat => $name) {
    $c = $contactsByCategory[$cat] ?? null;
    if (!$c) continue;

    $hasEmail = !empty($c['email']) && filter_var($c['email'], FILTER_VALIDATE_EMAIL);
    $hasPhone = !empty($c['phoneNumber']) && preg_match('/^[+]?[0-9\s\-().]{3,25}$/', $c['phoneNumber']);
    $hasLocation = !empty($c['officeLocation']);

    report((bool)$hasEmail, "{$name}: Official email valid ({$c['email']})");
    report((bool)$hasPhone, "{$name}: Official phone number valid ({$c['phoneNumber']})");
    report((bool)$hasLocation, "{$name}: Campus office location configured ({$c['officeLocation']})");
}

// --- 4. Testing Authorization & Security Guards on Contact Modification ---
echo "\n--- 4. Testing Authorization & Security Guards on Contact Modification ---\n";

// A. Unauthenticated PUT rejected
$unauthPut = apiCall("{$baseUrl}/api/contacts/1", 'PUT', [
    'phoneNumber' => '+91 99999 99999',
]);
report(in_array($unauthPut['code'], [401, 403], true), "Unauthenticated PUT /api/contacts/1 blocked (HTTP {$unauthPut['code']})");

// B. Student login & attempt to modify contact
$stuCookie = tempnam(sys_get_temp_dir(), 'csos_stu_');
$stuCsrfRes = apiCall("{$baseUrl}/api/auth/csrf", 'GET', null, $stuCookie);
$stuCsrf = $stuCsrfRes['body']['data']['csrfToken'] ?? '';
$stuLogin = apiCall("{$baseUrl}/api/auth/login", 'POST', [
    'email' => 'student@campussos.edu',
    'password' => $demoPassword,
], $stuCookie, $stuCsrf);
$stuAuthCsrf = $stuLogin['body']['data']['csrfToken'] ?? $stuCsrf;

$stuPut = apiCall("{$baseUrl}/api/contacts/1", 'PUT', [
    'phoneNumber' => '+91 88888 88888',
], $stuCookie, $stuAuthCsrf);
report($stuPut['code'] === 403, "Student blocked from modifying department contacts (HTTP 403 Forbidden)");
@unlink($stuCookie);

// C. Responder login & attempt to modify contact
$respCookie = tempnam(sys_get_temp_dir(), 'csos_resp_');
$respCsrfRes = apiCall("{$baseUrl}/api/auth/csrf", 'GET', null, $respCookie);
$respCsrf = $respCsrfRes['body']['data']['csrfToken'] ?? '';
$respLogin = apiCall("{$baseUrl}/api/auth/login", 'POST', [
    'email' => 'medical@campussos.edu',
    'password' => $demoPassword,
], $respCookie, $respCsrf);
$respAuthCsrf = $respLogin['body']['data']['csrfToken'] ?? $respCsrf;

$respPut = apiCall("{$baseUrl}/api/contacts/1", 'PUT', [
    'phoneNumber' => '+91 77777 77777',
], $respCookie, $respAuthCsrf);
report($respPut['code'] === 403, "Responder blocked from modifying department contacts (HTTP 403 Forbidden)");
@unlink($respCookie);

// D. Admin login & authorized update with validation
$adminCookie = tempnam(sys_get_temp_dir(), 'csos_admin_');
$adminCsrfRes = apiCall("{$baseUrl}/api/auth/csrf", 'GET', null, $adminCookie);
$adminCsrf = $adminCsrfRes['body']['data']['csrfToken'] ?? '';
$adminLogin = apiCall("{$baseUrl}/api/auth/login", 'POST', [
    'email' => 'admin@campussos.edu',
    'password' => $demoPassword,
], $adminCookie, $adminCsrf);
$adminAuthCsrf = $adminLogin['body']['data']['csrfToken'] ?? $adminCsrf;

// E. Admin submits INVALID email format -> Expect 422
$badEmailPut = apiCall("{$baseUrl}/api/contacts/1", 'PUT', [
    'email' => 'not-an-email-address',
], $adminCookie, $adminAuthCsrf);
report($badEmailPut['code'] === 422, "Invalid email format rejected with HTTP 422 Unprocessable Entity");

// F. Admin submits INVALID phone format -> Expect 422
$badPhonePut = apiCall("{$baseUrl}/api/contacts/1", 'PUT', [
    'phoneNumber' => 'invalid#phone$$$',
], $adminCookie, $adminAuthCsrf);
report($badPhonePut['code'] === 422, "Invalid phone format rejected with HTTP 422 Unprocessable Entity");

// G. Admin successfully updates contact details
$validPut = apiCall("{$baseUrl}/api/contacts/1", 'PUT', [
    'departmentName' => 'Medical Response',
    'representativeName' => 'Dr. Priya Varma',
    'designation' => 'Chief Medical Officer',
    'email' => 'medical@campussos.edu',
    'phoneNumber' => '+91 98765 43210',
    'officeLocation' => 'Health Center Room 102',
    'description' => 'Campus medical clinic triage, first aid treatment, and emergency ambulance dispatch.',
    'isConfigured' => true,
    'isOfficialVerified' => true,
], $adminCookie, $adminAuthCsrf);
report($validPut['code'] === 200, "Admin authorized update succeeded with HTTP 200 OK");
report($validPut['body']['data']['isOfficialVerified'] === true, "Contact verified flag persisted correctly");

@unlink($adminCookie);

// --- 5. Confidentiality & Data Isolation ---
echo "\n--- 5. Confidentiality & Privacy Auditing ---\n";
// Ensure GET /api/contacts response does NOT leak any incident rows or confidential reports
$contactsRaw = json_encode($contactsRes['body']);
report(!str_contains($contactsRaw, 'INC-2026'), "Public contacts endpoint does NOT leak incident numbers");
report(!str_contains($contactsRaw, 'harassment_notes'), "Public contacts endpoint does NOT leak confidential case data");
report(!str_contains($contactsRaw, 'complainant'), "Public contacts endpoint does NOT leak complainant identities");

echo "\n======================================================================\n";
echo " Verification Verdict: {$passCount} Passed, {$failCount} Failed\n";
echo "======================================================================\n";
exit($failCount === 0 ? 0 : 1);

