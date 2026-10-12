<?php
declare(strict_types=1);

/**
 * CampusSOS — Gemini API Connectivity & Integration Diagnostic
 * 
 * Safely tests:
 * 1. Environment variable loading (.env)
 * 2. Real minimal Gemini API connectivity (without exposing API key)
 * 3. Actual CampusSOS classification service integration
 * 4. Deterministic fallback behavior
 * 5. Confidential report privacy protection (zero AI leakage)
 * 
 * DOES NOT modify database or create real incidents.
 */

// 1. Bootstrap environment
require_once __DIR__ . '/../bootstrap.php';

use CampusSOS\Services\GeminiClassificationService;

echo "======================================================================\n";
echo " CampusSOS — Gemini API Connectivity & Functional Diagnostic\n";
echo "======================================================================\n\n";

// --- Step 1: Configuration Inspection ---
echo "--- 1. Configuration Inspection ---\n";
$envEnabled = getenv('GEMINI_ENABLED') ?: ($_ENV['GEMINI_ENABLED'] ?? null);
$rawApiKey  = GeminiClassificationService::getApiKey();
$modelName  = GeminiClassificationService::getModel();

$keyPresent = !empty($rawApiKey);
$keyLength  = strlen($rawApiKey);
$keyMasked  = $keyPresent ? substr($rawApiKey, 0, 4) . '...' . substr($rawApiKey, -4) : '(none)';

echo "GEMINI_ENABLED: " . ($envEnabled ?? '(not set)') . "\n";
echo "GEMINI_MODEL:   " . $modelName . "\n";
echo "GEMINI_API_KEY: " . ($keyPresent ? "Present [Length: {$keyLength}, Format: {$keyMasked}]" : "Missing") . "\n";
echo "isGeminiEnabled(): " . (GeminiClassificationService::isGeminiEnabled() ? "true" : "false") . "\n\n";

if (!$keyPresent) {
    echo "ERROR: Gemini API key is missing. Cannot perform live API test.\n";
    exit(1);
}

// --- Step 2: Minimal Harmless Live API Connectivity Test ---
echo "--- 2. Direct Gemini API Live Connectivity Test ---\n";

$url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:generateContent?key=" . urlencode($rawApiKey);

$minimalPayload = [
    'contents' => [
        [
            'role' => 'user',
            'parts' => [
                ['text' => 'Respond with exactly: CampusSOS Gemini connection successful']
            ]
        ]
    ],
    'generationConfig' => [
        'temperature' => 0.0,
        'maxOutputTokens' => 30,
    ]
];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($minimalPayload),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 8,
    CURLOPT_CONNECTTIMEOUT => 4,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$startTime = microtime(true);
$rawResponse = curl_exec($ch);
$elapsedMs = round((microtime(true) - $startTime) * 1000, 2);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
$curlErrno = curl_errno($ch);
curl_close($ch);

$directSuccess = false;
$sanitizedError = null;
$responseText = null;

if ($curlErrno !== 0) {
    $sanitizedError = "cURL Network Error ({$curlErrno}): {$curlError}";
    echo "Result: Network / Connection Failure\n";
    echo "Elapsed: {$elapsedMs} ms\n";
    echo "Error Detail: {$sanitizedError}\n";
} else {
    echo "HTTP Status Code: {$httpCode}\n";
    echo "Elapsed Time:     {$elapsedMs} ms\n";

    $decoded = json_decode((string) $rawResponse, true);

    if ($httpCode === 200 && is_array($decoded)) {
        $responseText = trim($decoded['candidates'][0]['content']['parts'][0]['text'] ?? '');
        echo "Response Received: \"{$responseText}\"\n";
        echo "Connectivity:     SUCCESSFUL\n";
        $directSuccess = true;
    } else {
        // Sanitize error response: extract API error message without leaking keys
        $apiErrorMsg = $decoded['error']['message'] ?? 'Unknown API error';
        $apiErrorStatus = $decoded['error']['status'] ?? "HTTP_{$httpCode}";
        $sanitizedError = "Status: {$apiErrorStatus} | Message: {$apiErrorMsg}";
        echo "Connectivity:     FAILED\n";
        echo "Sanitized Error:  {$sanitizedError}\n";
    }
}
echo "\n";

// --- Step 3: Actual CampusSOS Classification Integration Test ---
echo "--- 3. CampusSOS Incident Classification Integration Test ---\n";
$sampleTitle = "Water pipe leakage in Library corridor";
$sampleDesc = "Water is dripping from ceiling near the 2nd floor computer lab corridor.";

$classified = GeminiClassificationService::classifyIncident(
    $sampleTitle,
    $sampleDesc,
    'other',
    'standard'
);

echo "Input: \"{$sampleTitle}\" (category: other)\n";
echo "Output Source:     " . ($classified['source'] ?? 'unknown') . "\n";
echo "Suggested Category:" . ($classified['suggestedCategory'] ?? 'unknown') . "\n";
echo "Urgency:           " . ($classified['urgency'] ?? 'unknown') . "\n";
echo "Confidence:        " . ($classified['confidence'] ?? 'unknown') . "\n";
echo "Rationale:         " . ($classified['rationale'] ?? 'unknown') . "\n";

$integrationWorks = ($classified['source'] === 'gemini');
echo "Gemini Integration Active: " . ($integrationWorks ? "YES" : "NO (Used Fallback)") . "\n\n";

// --- Step 4: Deterministic Fallback Verification ---
echo "--- 4. Deterministic Fallback Verification ---\n";
$deterministicTest = GeminiClassificationService::classifyDeterministic(
    "Active electrical fire alarm sounding in hostel",
    "Visible smoke coming from electrical board on floor 3"
);
$fallbackValid = ($deterministicTest['suggestedCategory'] === 'fire' && $deterministicTest['urgency'] === 'critical');
echo "Deterministic Rule Match: " . ($fallbackValid ? "PASS (Category: fire, Urgency: critical)" : "FAIL") . "\n\n";

// --- Step 5: Confidential Privacy Protection Verification ---
echo "--- 5. Confidentiality & AI Suppression Guard ---\n";
$confidentialResult = GeminiClassificationService::classifyIncident(
    "Confidential student inquiry",
    "Sensitive student complaint concerning peer intimidation",
    'anti_ragging',
    'confidential'
);
$privacyGuarded = ($confidentialResult['source'] === 'deterministic_privacy_rule');
echo "Confidential Bypass: " . ($privacyGuarded ? "PASS (Strictly bypassed external AI)" : "FAIL") . "\n\n";

// --- Step 6: Emergency SOS Independence Verification ---
echo "--- 6. Emergency SOS Independence Verification ---\n";
$sosCode1 = file_get_contents(__DIR__ . '/../services/SosDispatchService.php') ?: '';
$sosCode2 = file_get_contents(__DIR__ . '/../controllers/SosController.php') ?: '';
$sosMentionsGemini = str_contains($sosCode1, 'Gemini') || str_contains($sosCode1, 'classify') || str_contains($sosCode2, 'Gemini');
echo "SosDispatchService AI Reference: " . ($sosMentionsGemini ? "DETECTED (Violation!)" : "NONE (PASS: Strictly independent)") . "\n\n";

echo "======================================================================\n";
echo " Diagnostic Summary\n";
echo "======================================================================\n";
echo "Live Connectivity:           " . ($directSuccess ? "PASS" : "FAIL") . "\n";
echo "Classification Integration:  " . ($integrationWorks ? "PASS" : "FALLBACK") . "\n";
echo "Deterministic Fallback:      " . ($fallbackValid ? "PASS" : "FAIL") . "\n";
echo "Confidential AI Suppression: " . ($privacyGuarded ? "PASS" : "FAIL") . "\n";
echo "SOS AI Independence:         " . (!$sosMentionsGemini ? "PASS" : "FAIL") . "\n";
echo "======================================================================\n";
