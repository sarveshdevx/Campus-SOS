<?php
declare(strict_types=1);

namespace CampusSOS\Controllers;

use CampusSOS\Services\AuthService;
use CampusSOS\Services\ResponseService;
use CampusSOS\Middleware\CsrfMiddleware;
use InvalidArgumentException;
use RuntimeException;

class AuthController
{
    /**
     * GET /api/auth/csrf
     * Expose endpoint for frontend client to fetch or refresh CSRF token.
     */
    public function csrf(): void
    {
        $token = CsrfMiddleware::getToken();
        ResponseService::json(['csrfToken' => $token], 200, 'CSRF token initialized.');
    }

    /**
     * GET /api/auth/me
     * Return safe public profile of authenticated user or 401.
     */
    public function me(): void
    {
        $user = AuthService::getCurrentUser();
        if (!$user) {
            ResponseService::error('Unauthenticated session.', 401, null, 'AUTH_REQUIRED');
        }

        ResponseService::json([
            'user' => $user,
            'csrfToken' => CsrfMiddleware::getToken(),
        ], 200, 'Authenticated profile retrieved.');
    }

    /**
     * POST /api/auth/login
     * Authenticate credentials, regenerate session, and return user profile.
     */
    public function login(): void
    {
        // Enforce Content-Type application/json
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (!str_contains($contentType, 'application/json')) {
            ResponseService::error('Invalid content type. Expected application/json.', 415);
        }

        $rawInput = file_get_contents('php://input');
        if (strlen($rawInput) > 2 * 1024 * 1024) {
            ResponseService::error('Request payload exceeds size limit.', 413);
        }

        $input = json_decode($rawInput, true);
        if (!is_array($input)) {
            ResponseService::error('Malformed JSON body provided.', 400);
        }

        $email = (string) ($input['email'] ?? '');
        $password = (string) ($input['password'] ?? '');

        try {
            $result = AuthService::login($email, $password);
            ResponseService::json($result, 200, 'Login successful.');
        } catch (InvalidArgumentException $e) {
            $errors = property_exists($e, 'errors') ? $e->errors : null;
            ResponseService::error($e->getMessage(), 422, $errors, 'VALIDATION_FAILED');
        } catch (RuntimeException $e) {
            if (str_contains($e->getMessage(), 'Database service is unavailable')) {
                ResponseService::error($e->getMessage(), 503, null, 'DATABASE_UNAVAILABLE');
            }
            $code = str_contains($e->getMessage(), 'contact campus administration') ? 403 : 401;
            ResponseService::error($e->getMessage(), $code, null, 'INVALID_CREDENTIALS');
        }
    }

    /**
     * POST /api/auth/register
     * Register a new student account. Never trusts client role input.
     */
    public function register(): void
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (!str_contains($contentType, 'application/json')) {
            ResponseService::error('Invalid content type. Expected application/json.', 415);
        }

        $rawInput = file_get_contents('php://input');
        if (strlen($rawInput) > 2 * 1024 * 1024) {
            ResponseService::error('Request payload exceeds size limit.', 413);
        }

        $input = json_decode($rawInput, true);
        if (!is_array($input)) {
            ResponseService::error('Malformed JSON body provided.', 400);
        }

        try {
            $result = AuthService::register($input);
            ResponseService::json($result, 201, 'Student account successfully registered.');
        } catch (InvalidArgumentException $e) {
            $errors = property_exists($e, 'errors') ? $e->errors : null;
            $status = (is_array($errors) && isset($errors['email']) && str_contains($errors['email'][0], 'already exists')) ? 409 : 422;
            ResponseService::error($e->getMessage(), $status, $errors, 'REGISTRATION_FAILED');
        } catch (\Exception $e) {
            ResponseService::error('An unexpected error occurred during registration.', 500);
        }
    }

    /**
     * POST /api/auth/logout
     * Invalidate session and clear session cookies.
     */
    public function logout(): void
    {
        AuthService::logout();
        ResponseService::json(['message' => 'Logged out successfully.'], 200);
    }
}
