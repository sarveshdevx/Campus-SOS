<?php
declare(strict_types=1);

namespace CampusSOS\Models;

use CampusSOS\Config\Database;

class Responder
{
    public static function findByUserId(int $userId): ?array
    {
        $pdo = Database::getConnection();
        if (!$pdo) return null;

        $stmt = $pdo->prepare("
            SELECT r.id, r.user_id, r.category, r.department, r.status,
                   r.current_location, r.contact_phone, r.shift_notes
            FROM responders r
            WHERE r.user_id = :user_id
            LIMIT 1
        ");
        $stmt->execute([':user_id' => $userId]);
        $profile = $stmt->fetch();

        return $profile ?: null;
    }

    public static function findByCategory(string $category): array
    {
        $pdo = Database::getConnection();
        if (!$pdo) return [];

        $stmt = $pdo->prepare("
            SELECT r.*, u.full_name, u.email
            FROM responders r
            JOIN users u ON r.user_id = u.id
            WHERE r.category = :category
            ORDER BY r.status ASC
        ");
        $stmt->execute([':category' => $category]);

        return $stmt->fetchAll();
    }

    public static function findAll(): array
    {
        $pdo = Database::getConnection();
        if (!$pdo) return [];

        $stmt = $pdo->prepare("
            SELECT r.*, u.full_name, u.email
            FROM responders r
            JOIN users u ON r.user_id = u.id
            ORDER BY r.category ASC, r.status ASC
        ");
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
