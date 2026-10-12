<?php
declare(strict_types=1);

namespace CampusSOS\Models;

use CampusSOS\Config\Database;

class Location
{
    public static function findAllActive(): array
    {
        $pdo = Database::getConnection();
        if (!$pdo) return [];

        $stmt = $pdo->prepare("
            SELECT id, campus_zone, building_name, floor_or_room, landmark_description,
                   coordinates_lat, coordinates_lng
            FROM locations
            WHERE is_active = 1
            ORDER BY campus_zone ASC, building_name ASC
        ");
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function findById(int $id): ?array
    {
        $pdo = Database::getConnection();
        if (!$pdo) return null;

        $stmt = $pdo->prepare("
            SELECT * FROM locations
            WHERE id = :id AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $location = $stmt->fetch();

        return $location ?: null;
    }
}

