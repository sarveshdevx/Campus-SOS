<?php
declare(strict_types=1);

namespace CampusSOS\Validators;

class AuthValidator
{
    /**
     * Validate registration payload.
     *
     * @param array $data Input payload
     * @return array<string, string[]> Associative array of field error messages
     */
    public static function validateRegistration(array $data): array
    {
        $errors = [];

        // 1. Full Name
        $fullName = trim($data['fullName'] ?? ($data['full_name'] ?? ''));
        if ($fullName === '') {
            $errors['fullName'][] = 'Full name is required.';
        } elseif (mb_strlen($fullName) < 2) {
            $errors['fullName'][] = 'Full name must be at least 2 characters.';
        } elseif (mb_strlen($fullName) > 120) {
            $errors['fullName'][] = 'Full name cannot exceed 120 characters.';
        }

        // 2. Email Address
        $email = trim($data['email'] ?? '');
        if ($email === '') {
            $errors['email'][] = 'Campus email address is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'][] = 'Please provide a valid email address.';
        } elseif (strlen($email) > 191) {
            $errors['email'][] = 'Email address cannot exceed 191 characters.';
        }

        // 3. Password
        $password = (string) ($data['password'] ?? '');
        if ($password === '') {
            $errors['password'][] = 'Password is required.';
        } elseif (strlen($password) < 8) {
            $errors['password'][] = 'Password must be at least 8 characters in length.';
        } elseif (strlen($password) > 128) {
            $errors['password'][] = 'Password cannot exceed 128 characters.';
        }

        // 4. Password Confirmation (if provided by client)
        $confirm = $data['password_confirmation'] ?? ($data['passwordConfirmation'] ?? ($data['confirmPassword'] ?? null));
        if ($confirm !== null) {
            if ($password !== (string) $confirm) {
                $errors['password_confirmation'][] = 'Passwords do not match.';
            }
        }

        // 5. Optional Phone Number
        if (!empty($data['phoneNumber']) || !empty($data['phone_number'])) {
            $phone = trim((string) ($data['phoneNumber'] ?? ($data['phone_number'] ?? '')));
            if (!preg_match('/^[0-9+\s\-()]{7,25}$/', $phone)) {
                $errors['phoneNumber'][] = 'Please provide a valid contact telephone number.';
            }
        }

        return $errors;
    }

    /**
     * Validate login payload.
     *
     * @param array $data Input payload
     * @return array<string, string[]> Associative array of field error messages
     */
    public static function validateLogin(array $data): array
    {
        $errors = [];

        $email = trim($data['email'] ?? '');
        if ($email === '') {
            $errors['email'][] = 'Email address is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'][] = 'Invalid email address format.';
        }

        $password = (string) ($data['password'] ?? '');
        if ($password === '') {
            $errors['password'][] = 'Password is required.';
        }

        return $errors;
    }
}
