<?php
declare(strict_types=1);

/**
 * Phase 3 Authentication, Validator, and CSRF Test Suite
 */

require_once __DIR__ . '/backend/bootstrap.php';

use CampusSOS\Validators\AuthValidator;
use CampusSOS\Middleware\CsrfMiddleware;

$passed = 0;
$failed = 0;

function assertTest(bool $condition, string $testName): void {
    global $passed, $failed;
    if ($condition) {
        echo "PASS: $testName\n";
        $passed++;
    } else {
        echo "FAIL: $testName\n";
        $failed++;
    }
}

echo "=== Running AuthValidator Tests ===\n";

// Test 1: Empty input returns validation errors
$errors = AuthValidator::validateRegistration([]);
assertTest(isset($errors['fullName']) && isset($errors['email']) && isset($errors['password']), "Detects empty required registration fields");

// Test 2: Invalid email format
$errors = AuthValidator::validateRegistration([
    'fullName' => 'John Doe',
    'email' => 'not-an-email',
    'password' => 'Password123!',
    'password_confirmation' => 'Password123!',
]);
assertTest(isset($errors['email']), "Detects invalid email format");

// Test 3: Password mismatch
$errors = AuthValidator::validateRegistration([
    'fullName' => 'John Doe',
    'email' => 'john@campussos.edu',
    'password' => 'Password123!',
    'password_confirmation' => 'DifferentPassword123!',
]);
assertTest(isset($errors['password_confirmation']), "Detects password mismatch");

// Test 4: Short password (< 8 chars)
$errors = AuthValidator::validateRegistration([
    'fullName' => 'John Doe',
    'email' => 'john@campussos.edu',
    'password' => 'short',
    'password_confirmation' => 'short',
]);
assertTest(isset($errors['password']), "Detects password under 8 characters");

// Test 5: Valid registration input passes validation
$errors = AuthValidator::validateRegistration([
    'fullName' => '  Aarav Sharma  ',
    'email' => '  aarav@campussos.edu  ',
    'password' => 'SecurePass2026!',
    'password_confirmation' => 'SecurePass2026!',
    'phoneNumber' => '+91 9876543210',
]);
assertTest(empty($errors), "Valid registration payload yields zero validation errors");

// Test 6: Login credentials validation
$errors = AuthValidator::validateLogin([
    'email' => 'invalid-email',
    'password' => '',
]);
assertTest(isset($errors['email']) && isset($errors['password']), "Rejects invalid login email and blank password");

$validLoginErrors = AuthValidator::validateLogin([
    'email' => 'student@campussos.edu',
    'password' => 'Password123!',
]);
assertTest(empty($validLoginErrors), "Valid login payload yields zero validation errors");

echo "\n=== Running CSRF Middleware Tests ===\n";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$token1 = CsrfMiddleware::getToken();
assertTest(!empty($token1) && strlen($token1) === 64, "Generates 64-character hex CSRF token");

$token2 = CsrfMiddleware::getToken();
assertTest($token1 === $token2, "Token is persistent per session");

assertTest(CsrfMiddleware::validateToken($token1) === true, "Validates matching token");
assertTest(CsrfMiddleware::validateToken('invalid_token') === false, "Rejects mismatched token");
assertTest(CsrfMiddleware::validateToken('') === false, "Rejects empty token");

echo "\n====================================\n";
echo "Results: $passed Passed, $failed Failed\n";
exit($failed > 0 ? 1 : 0);
