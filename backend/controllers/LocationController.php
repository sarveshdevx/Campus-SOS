<?php
declare(strict_types=1);

namespace CampusSOS\Controllers;

use CampusSOS\Services\LocationService;
use CampusSOS\Services\ResponseService;

class LocationController
{
    public function index(): void
    {
        $locations = [];
        foreach (LocationService::GENERAL_LOCATIONS as $loc) {
            $locations[] = [
                'name' => $loc,
                'requiresRoomNumber' => false,
                'type' => 'general',
            ];
        }
        foreach (LocationService::BUILDING_LOCATIONS as $bld) {
            $locations[] = [
                'name' => $bld,
                'requiresRoomNumber' => true,
                'type' => 'building',
            ];
        }

        ResponseService::json($locations, 200);
    }
}

