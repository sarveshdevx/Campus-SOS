<?php
declare(strict_types=1);

namespace CampusSOS\Services;

class LocationService
{
    /**
     * 16 General Campus Locations (do not require room number)
     */
    public const GENERAL_LOCATIONS = [
        'OAT',
        'Football Ground',
        'Basketball Court',
        'Vehicle Parking',
        'Gate 1',
        'Gate 2',
        'Gate 3',
        'Central Library',
        'Idea Lab',
        'Gyan Mandir Auditorium',
        'Admin Cell',
        'TP Cell',
        'Bus Stop',
        'D Garden',
        'Volleyball Court',
        'Sports Complex',
    ];

    /**
     * 9 Buildings that require a room number
     */
    public const BUILDING_LOCATIONS = [
        'CS Block',
        'Civil Block',
        'Mechanical Block',
        'Electrical Block',
        'Noran Girls Hostel',
        'Nirvana Boys Hostel',
        'AC Hostel',
        'KBH Hostel',
        'RKS Hostel',
    ];

    /**
     * Get all 25 allowed campus locations.
     */
    public static function getAllLocations(): array
    {
        return array_merge(self::GENERAL_LOCATIONS, self::BUILDING_LOCATIONS);
    }

    /**
     * Check if a location is in the approved list.
     */
    public static function isAllowedLocation(string $location): bool
    {
        $trimmed = trim($location);
        return in_array($trimmed, self::GENERAL_LOCATIONS, true) || in_array($trimmed, self::BUILDING_LOCATIONS, true);
    }

    /**
     * Check if a location requires a room number.
     */
    public static function requiresRoomNumber(string $location): bool
    {
        return in_array(trim($location), self::BUILDING_LOCATIONS, true);
    }

    /**
     * Validate a room number string.
     * Allows letters, numbers, hyphens, and spaces (e.g., "101", "A-204", "CS-12", "G1-05", "Room B12").
     */
    public static function validateRoomNumber(?string $roomNumber): array
    {
        $clean = trim((string) $roomNumber);
        if ($clean === '') {
            return [
                'valid' => false,
                'error' => 'Room number is required for building locations.',
                'roomNumber' => null,
            ];
        }

        // Must contain only letters, numbers, hyphens, and spaces
        if (!preg_match('/^[a-zA-Z0-9\s-]+$/', $clean)) {
            return [
                'valid' => false,
                'error' => 'Room number may only contain letters, numbers, spaces, and hyphens.',
                'roomNumber' => null,
            ];
        }

        return [
            'valid' => true,
            'error' => null,
            'roomNumber' => $clean,
        ];
    }

    /**
     * Parse an existing location string into base location and room number if composite.
     * Handles formats:
     * - "CS Block — Room A-204" (em-dash)
     * - "CS Block - Room A-204" (en-dash / hyphen)
     * - "CS Block"
     */
    public static function parseLocation(string $locationString): array
    {
        $trimmed = trim($locationString);

        // Check for "Building — Room XYZ" or "Building - XYZ"
        if (preg_match('/^(.+?)\s+(?:—|-)\s+(?:Room\s+)?(.+)$/u', $trimmed, $matches)) {
            $base = trim($matches[1]);
            $room = trim($matches[2]);
            if (self::isAllowedLocation($base)) {
                return [
                    'baseLocation' => $base,
                    'roomNumber' => $room,
                    'isBuilding' => self::requiresRoomNumber($base),
                ];
            }
        }

        // Direct match
        $isBuilding = self::requiresRoomNumber($trimmed);
        return [
            'baseLocation' => $trimmed,
            'roomNumber' => null,
            'isBuilding' => $isBuilding,
        ];
    }

    /**
     * Validate and format location input.
     * Accepts either separate location + room, or pre-combined formatted location.
     */
    public static function validateAndFormat(string $locationName, ?string $roomNumber = null): array
    {
        $rawLoc = trim($locationName);
        $rawRoom = $roomNumber !== null ? trim($roomNumber) : null;

        // If the locationName is already formatted like "CS Block — Room A-204", parse it
        $parsed = self::parseLocation($rawLoc);
        if ($parsed['roomNumber'] !== null) {
            $rawLoc = $parsed['baseLocation'];
            if ($rawRoom === null || $rawRoom === '') {
                $rawRoom = $parsed['roomNumber'];
            }
        }

        // 1. Validate that base location is one of the 25 allowed locations
        if (!self::isAllowedLocation($rawLoc)) {
            return [
                'valid' => false,
                'error' => "Invalid campus location '{$rawLoc}'. Please select from the approved campus locations.",
                'baseLocation' => $rawLoc,
                'roomNumber' => null,
                'formattedLocation' => $rawLoc,
            ];
        }

        // 2. If it is one of the 9 buildings: room number is mandatory
        if (self::requiresRoomNumber($rawLoc)) {
            $roomCheck = self::validateRoomNumber($rawRoom);
            if (!$roomCheck['valid']) {
                return [
                    'valid' => false,
                    'error' => $roomCheck['error'],
                    'baseLocation' => $rawLoc,
                    'roomNumber' => null,
                    'formattedLocation' => $rawLoc,
                ];
            }

            $cleanRoom = $roomCheck['roomNumber'];
            $formatted = "{$rawLoc} \xE2\x80\x94 Room {$cleanRoom}"; // Em-dash "—"

            return [
                'valid' => true,
                'error' => null,
                'baseLocation' => $rawLoc,
                'roomNumber' => $cleanRoom,
                'formattedLocation' => $formatted,
            ];
        }

        // 3. For general campus locations: no room number allowed/needed
        return [
            'valid' => true,
            'error' => null,
            'baseLocation' => $rawLoc,
            'roomNumber' => null,
            'formattedLocation' => $rawLoc,
        ];
    }
}

