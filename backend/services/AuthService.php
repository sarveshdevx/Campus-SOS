<?php
declare(strict_types=1);

namespace CampusSOS\Services;

use CampusSOS\Models\User;
use CampusSOS\Models\Responder;
use CampusSOS\Models\AuditLog;
use CampusSOS\Validators\AuthValidator;
use CampusSOS\Middleware\CsrfMiddleware;
use InvalidArgumentException;
use RuntimeException;

class AuthService
{
    /**
     * Retrieve the currently authenticated user's safe profile.
     * Verifies account status against MySQL on each invocation.
     */
    public static function getCurrentUser(): ?array
    {
        if (session_status() === PHP_SESSION_NONE) {
            \CampusSOS\Config\App::initSession();
        }

        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            return null;
        }

        $user = User::findById((int) $userId, false);

        if (!$user) {
            // User no longer exists in database
            self::logout();
            return null;
        }

        // Check if account has been suspended or deactivated since login
        if (($user['account_status'] ?? 'active') !== 'active') {
            self::logout();
            return null;
        }

        // Keep session role synced with database
        $_SESSION['role'] = $user['role'];
        $_SESSION['account_status'] = $user['account_status'];

        unset($user['password_hash']);

        // Attach responder profile if applicable
        if ($user['role'] === 'responder') {
            $user['responderProfile'] = Responder::findByUserId((int) $user['id']);
        }

        return self::formatSafeUser($user);
    }

    /**
     * Authenticate user with email and password.
     * Returns safe user profile and new CSRF token.
     *
     * @throws InvalidArgumentException When validation fails
     * @throws RuntimeException When credentials are invalid or account is disabled
     */
    public static function login(string $email, string $password): array
    {
        $errors = AuthValidator::validateLogin(['email' => $email, 'password' => $password]);
        if (!empty($errors)) {
            $err = new InvalidArgumentException('Please provide valid login credentials.');
            $err->errors = $errors;
            throw $err;
        }

        $pdo = \CampusSOS\Config\Database::getConnection();
        if (!$pdo) {
            throw new RuntimeException('Database service is unavailable. Please verify MySQL service in XAMPP.');
        }

        $normalizedEmail = strtolower(trim($email));
        $user = User::findByEmail($normalizedEmail);

        // Constant-time check: use generic error to prevent user enumeration
        if (!$user || !password_verify($password, (string) $user['password_hash'])) {
            AuditLog::log(null, 'LOGIN_FAILED', 'auth', null, [
                'email' => $normalizedEmail,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            ]);
            throw new RuntimeException('Invalid email or password.');
        }

        // Check account status
        $status = $user['account_status'] ?? 'active';
        if ($status !== 'active') {
            AuditLog::log((int) $user['id'], 'LOGIN_BLOCKED_STATUS', 'user', (int) $user['id'], ['status' => $status]);
            throw new RuntimeException("Your account is {$status}. Please contact campus administration.");
        }

        // Regenerate session ID to prevent session fixation attacks
        if (session_status() === PHP_SESSION_NONE) {
            \CampusSOS\Config\App::initSession();
        }
        session_regenerate_id(true);

        // Store minimal identity in server session
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['role'] = (string) $user['role'];
        $_SESSION['account_status'] = (string) $user['account_status'];

        // Generate fresh CSRF token for the authenticated session
        $csrfToken = CsrfMiddleware::regenerateToken();

        AuditLog::log((int) $user['id'], 'LOGIN_SUCCESS', 'user', (int) $user['id']);

        unset($user['password_hash']);

        if ($user['role'] === 'responder') {
            $user['responderProfile'] = Responder::findByUserId((int) $user['id']);
        }

        return [
            'user' => self::formatSafeUser($user),
            'csrfToken' => $csrfToken,
        ];
    }

    /**
     * Register a new student account.
     * Never trusts client-supplied role: unconditionally sets role='student'.
     *
     * @throws InvalidArgumentException When validation fails or email already exists
     */
    public static function register(array $data): array
    {
        $errors = AuthValidator::validateRegistration($data);
        if (!empty($errors)) {
            $err = new InvalidArgumentException('Registration data validation failed.');
            $err->errors = $errors;
            throw $err;
        }

        $normalizedEmail = strtolower(trim((string) $data['email']));

        // Check for existing account
        $existing = User::findByEmail($normalizedEmail);
        if ($existing) {
            $err = new InvalidArgumentException('An account with this email address already exists.');
            $err->errors = ['email' => ['An account with this email address already exists.']];
            throw $err;
        }

        // Hash password securely (PASSWORD_DEFAULT uses modern bcrypt/argon2)
        $passwordHash = password_hash((string) $data['password'], PASSWORD_DEFAULT, ['cost' => 12]);

        // Security rule: Public registration ONLY creates 'student' accounts
        $userId = User::create([
            'fullName' => trim((string) $data['fullName']),
            'email' => $normalizedEmail,
            'passwordHash' => $passwordHash,
            'phoneNumber' => !empty($data['phoneNumber']) ? trim((string) $data['phoneNumber']) : null,
            'studentId' => !empty($data['studentId']) ? trim((string) $data['studentId']) : null,
            'role' => 'student',
        ]);

        // Initialize session for newly registered student
        if (session_status() === PHP_SESSION_NONE) {
            \CampusSOS\Config\App::initSession();
        }
        session_regenerate_id(true);

        $_SESSION['user_id'] = $userId;
        $_SESSION['role'] = 'student';
        $_SESSION['account_status'] = 'active';

        $csrfToken = CsrfMiddleware::regenerateToken();

        AuditLog::log($userId, 'USER_REGISTERED', 'user', $userId, ['role' => 'student']);

        $user = User::findById($userId);
        unset($user['password_hash']);

        return [
            'user' => self::formatSafeUser($user),
            'csrfToken' => $csrfToken,
        ];
    }

    /**
     * Terminate user session and clear authentication cookies.
     */
    public static function logout(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            \CampusSOS\Config\App::initSession();
        }

        $userId = $_SESSION['user_id'] ?? null;
        if ($userId) {
            AuditLog::log((int) $userId, 'LOGOUT', 'user', (int) $userId);
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                [
                    'expires' => time() - 42000,
                    'path' => $params['path'],
                    'domain' => $params['domain'],
                    'secure' => $params['secure'],
                    'httponly' => $params['httponly'],
                    'samesite' => $params['samesite'] ?? 'Lax',
                ]
            );
        }

        @session_destroy();
    }

    /**
     * Normalize user array to consistent camelCase JSON structure.
     */
    private static function formatSafeUser(array $user): array
    {
        return [
            'id' => (int) $user['id'],
            'fullName' => $user['full_name'],
            'email' => $user['email'],
            'phoneNumber' => $user['phone_number'] ?? null,
            'studentId' => $user['student_id'] ?? null,
            'role' => $user['role'],
            'accountStatus' => $user['account_status'] ?? 'active',
            'createdAt' => $user['created_at'],
            'responderProfile' => $user['responderProfile'] ?? null,
        ];
    }
}
