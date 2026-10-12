<?php
declare(strict_types=1);

namespace CampusSOS\Models;

use CampusSOS\Config\Database;
use PDO;

class DepartmentContact
{
    /**
     * Retrieve all department contacts ordered by category.
     *
     * @return array<array<string, mixed>>
     */
    public static function findAll(): array
    {
        $pdo = Database::getConnection();
        if (!$pdo) return [];

        $stmt = $pdo->query("
            SELECT dc.*, u.full_name AS updated_by_name
            FROM department_contacts dc
            LEFT JOIN users u ON dc.updated_by_user_id = u.id
            ORDER BY dc.id ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Retrieve a department contact by ID.
     */
    public static function findById(int $id): ?array
    {
        $pdo = Database::getConnection();
        if (!$pdo) return null;

        $stmt = $pdo->prepare("
            SELECT dc.*, u.full_name AS updated_by_name
            FROM department_contacts dc
            LEFT JOIN users u ON dc.updated_by_user_id = u.id
            WHERE dc.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Retrieve a department contact by category.
     */
    public static function findByCategory(string $category): ?array
    {
        $pdo = Database::getConnection();
        if (!$pdo) return null;

        $stmt = $pdo->prepare("
            SELECT dc.*, u.full_name AS updated_by_name
            FROM department_contacts dc
            LEFT JOIN users u ON dc.updated_by_user_id = u.id
            WHERE dc.category = :category
            LIMIT 1
        ");
        $stmt->execute([':category' => $category]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Validate contact data before updating.
     *
     * @param array<string, mixed> $data
     * @return array<string> List of validation error messages
     */
    public static function validate(array $data): array
    {
        $errors = [];

        if (empty($data['department_name']) || strlen(trim((string)$data['department_name'])) < 2) {
            $errors[] = 'Department name is required and must be at least 2 characters.';
        }

        if (!empty($data['email'])) {
            $email = trim((string)$data['email']);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Invalid email format: '{$email}'.";
            }
        }

        if (!empty($data['phone_number'])) {
            $phone = trim((string)$data['phone_number']);
            // Allow international format, spaces, hyphens, parentheses, or short emergency numbers (e.g. 101)
            if (!preg_match('/^[+]?[0-9\s\-().]{3,25}$/', $phone)) {
                $errors[] = "Invalid phone number format: '{$phone}'. Must contain between 3 and 25 digits/symbols.";
            }
        }

        return $errors;
    }

    /**
     * Update department contact by ID (Admin authorized only).
     */
    public static function update(int $id, array $data, ?int $updatedByUserId): bool
    {
        $pdo = Database::getConnection();
        if (!$pdo) return false;

        $stmt = $pdo->prepare("
            UPDATE department_contacts
            SET department_name = :dept_name,
                representative_name = :rep_name,
                designation = :designation,
                email = :email,
                phone_number = :phone,
                office_location = :office_location,
                description = :description,
                is_configured = :is_configured,
                is_official_verified = :is_official_verified,
                updated_by_user_id = :updated_by,
                updated_at = NOW()
            WHERE id = :id
        ");

        return $stmt->execute([
            ':dept_name' => trim((string)($data['department_name'] ?? '')),
            ':rep_name' => !empty($data['representative_name']) ? trim((string)$data['representative_name']) : null,
            ':designation' => !empty($data['designation']) ? trim((string)$data['designation']) : null,
            ':email' => !empty($data['email']) ? trim((string)$data['email']) : null,
            ':phone' => !empty($data['phone_number']) ? trim((string)$data['phone_number']) : null,
            ':office_location' => !empty($data['office_location']) ? trim((string)$data['office_location']) : null,
            ':description' => !empty($data['description']) ? trim((string)$data['description']) : null,
            ':is_configured' => !empty($data['is_configured']) ? 1 : 0,
            ':is_official_verified' => !empty($data['is_official_verified']) ? 1 : 0,
            ':updated_by' => $updatedByUserId,
            ':id' => $id,
        ]);
    }
}

