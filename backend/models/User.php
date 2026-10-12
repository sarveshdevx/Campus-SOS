<?php
declare(strict_types=1);

namespace CampusSOS\Models;

use CampusSOS\Config\Database;
use PDOException;
use InvalidArgumentException;

class User
{
    public static function findById(int $id, bool $onlyActive = true): ?array
    {
        $pdo = Database::getConnection();
        if (!$pdo) return null;

        $sql = "SELECT id, full_name, email, phone_number, student_id, role, account_status, created_at, updated_at
                FROM users
                WHERE id = :id";
        if ($onlyActive) {
            $sql .= " AND account_status = 'active'";
        }
        $sql .= " LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public static function findByEmail(string $email): ?array
    {
        $pdo = Database::getConnection();
        if (!$pdo) return null;

        $stmt = $pdo->prepare("
            SELECT id, full_name, email, password_hash, phone_number, student_id, role, account_status, created_at, updated_at
            FROM users
            WHERE email = :email
            LIMIT 1
        ");
        $stmt->execute([':email' => strtolower(trim($email))]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public static function create(array $data): int
    {
        $pdo = Database::getConnection();
        if (!$pdo) {
            throw new \RuntimeException('Database connection unavailable.');
        }

        $email = strtolower(trim($data['email']));

        try {
            $stmt = $pdo->prepare("
                INSERT INTO users (
                    full_name, email, password_hash, phone_number, student_id,
                    role, account_status, created_at, updated_at
                ) VALUES (
                    :full_name, :email, :password_hash, :phone_number, :student_id,
                    :role, 'active', NOW(), NOW()
                )
            ");

            $stmt->execute([
                ':full_name' => trim($data['fullName']),
                ':email' => $email,
                ':password_hash' => $data['passwordHash'],
                ':phone_number' => !empty($data['phoneNumber']) ? trim($data['phoneNumber']) : null,
                ':student_id' => !empty($data['studentId']) ? trim($data['studentId']) : null,
                ':role' => $data['role'] ?? 'student',
            ]);

            return (int) $pdo->lastInsertId();
        } catch (PDOException $e) {
            // Check for duplicate key constraint violation (SQLSTATE 23000 / Error 1062)
            if ($e->getCode() === '23000' || str_contains($e->getMessage(), '1062')) {
                throw new InvalidArgumentException('An account with this email address already exists.');
            }
            throw $e;
        }
    }
}
