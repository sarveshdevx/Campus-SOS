<?php
declare(strict_types=1);

/**
 * CampusSOS — Comprehensive Test Suite for Approved Campus Locations and Conditional Room Numbers
 */

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/Incident.php';
require_once __DIR__ . '/../models/SosAlert.php';
require_once __DIR__ . '/../models/AuditLog.php';
require_once __DIR__ . '/../models/Responder.php';
require_once __DIR__ . '/../services/LocationService.php';
require_once __DIR__ . '/../services/SosDispatchService.php';
require_once __DIR__ . '/../services/IncidentMatcherService.php';

use CampusSOS\Config\Database;
use CampusSOS\Models\Incident;
use CampusSOS\Models\SosAlert;
use CampusSOS\Services\LocationService;
use CampusSOS\Services\SosDispatchService;
use CampusSOS\Services\IncidentMatcherService;

$passed = 0;
$failed = 0;

function assertTest(string $name, bool $condition, string $details = ''): void {
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] {$name}\n";
        $passed++;
    } else {
        echo "  [FAIL] {$name}: {$details}\n";
        $failed++;
    }
}

echo "=================================================================\n";
echo " CampusSOS — Location & Room Number Verification Suite\n";
echo "=================================================================\n\n";

// TEST GROUP 1: Location Lists and Constants
echo "--- 1. Location Constants & Whitelist Audit ---\n";
$general = LocationService::GENERAL_LOCATIONS;
$buildings = LocationService::BUILDING_LOCATIONS;
$all = LocationService::getAllLocations();

assertTest("Exactly 16 general campus locations defined", count($general) === 16, "Got " . count($general));
assertTest("Exactly 9 building locations defined", count($buildings) === 9, "Got " . count($buildings));
assertTest("Total 25 approved campus locations", count($all) === 25, "Got " . count($all));

$expectedGeneral = [
    'OAT', 'Football Ground', 'Basketball Court', 'Vehicle Parking',
    'Gate 1', 'Gate 2', 'Gate 3', 'Central Library', 'Idea Lab',
    'Gyan Mandir Auditorium', 'Admin Cell', 'TP Cell', 'Bus Stop',
    'D Garden', 'Volleyball Court', 'Sports Complex'
];
$expectedBuildings = [
    'CS Block', 'Civil Block', 'Mechanical Block', 'Electrical Block',
    'Noran Girls Hostel', 'Nirvana Boys Hostel', 'AC Hostel', 'KBH Hostel', 'RKS Hostel'
];

$genDiff = array_diff($expectedGeneral, $general);
assertTest("All 16 general locations match exact required names", empty($genDiff), "Diff: " . implode(', ', $genDiff));

$bldDiff = array_diff($expectedBuildings, $buildings);
assertTest("All 9 building locations match exact required names", empty($bldDiff), "Diff: " . implode(', ', $bldDiff));

// TEST GROUP 2: Validation and Room Requirement Checks
echo "\n--- 2. Location Classification & Room Validation ---\n";
foreach ($expectedGeneral as $loc) {
    assertTest("General location '{$loc}' does NOT require room", LocationService::requiresRoomNumber($loc) === false);
}
foreach ($expectedBuildings as $loc) {
    assertTest("Building location '{$loc}' DOES require room", LocationService::requiresRoomNumber($loc) === true);
}

assertTest("Rejects unknown location 'Invalid Campus Arena'", LocationService::isAllowedLocation('Invalid Campus Arena') === false);
assertTest("Rejects empty location", LocationService::isAllowedLocation('') === false);

// TEST GROUP 3: Room Number Format Validation
echo "\n--- 3. Room Number Input Validation Rules ---\n";
$validRooms = ['101', 'A-204', 'CS-12', 'G1-05', 'Room B12', '201-B', 'Lab 3'];
foreach ($validRooms as $room) {
    $res = LocationService::validateRoomNumber($room);
    assertTest("Valid room number '{$room}' accepted", $res['valid'] === true);
}

$invalidRooms = ['', '   ', '101#@', '<script>', 'Room!'];
foreach ($invalidRooms as $room) {
    $res = LocationService::validateRoomNumber($room);
    assertTest("Invalid room number '{$room}' rejected", $res['valid'] === false);
}

// TEST GROUP 4: validateAndFormat Helper
echo "\n--- 4. Location Formatting & Parsing ---\n";
$fmtGeneral = LocationService::validateAndFormat('Football Ground');
assertTest("General location formatted correctly", $fmtGeneral['valid'] && $fmtGeneral['formattedLocation'] === 'Football Ground' && $fmtGeneral['roomNumber'] === null);

$fmtBuildingNoRoom = LocationService::validateAndFormat('CS Block', '');
assertTest("Building location without room rejected", !$fmtBuildingNoRoom['valid']);

$fmtBuildingWithRoom = LocationService::validateAndFormat('CS Block', 'A-204');
assertTest("Building location with room formatted with em-dash", $fmtBuildingWithRoom['valid'] && $fmtBuildingWithRoom['formattedLocation'] === "CS Block \xE2\x80\x94 Room A-204" && $fmtBuildingWithRoom['roomNumber'] === 'A-204');

$parsed = LocationService::parseLocation("CS Block \xE2\x80\x94 Room A-204");
assertTest("parseLocation parses em-dash composite location", $parsed['baseLocation'] === 'CS Block' && $parsed['roomNumber'] === 'A-204');

// TEST GROUP 5: Incident Matching Logic with Room Distinction
echo "\n--- 5. Incident Matching Logic with Room Differentiation ---\n";
$inc1 = [
    'title' => 'Water leakage from ceiling pipe',
    'description' => 'Severe water dripping onto floor and electrical sockets in room.',
    'category' => 'maintenance',
    'location_name' => "CS Block \xE2\x80\x94 Room 101",
    'room_number' => '101',
    'occurred_at' => date('Y-m-d H:i:s'),
];

// Same building, SAME room: should match strongly
$incSameRoom = [
    'title' => 'Ceiling pipe leak flooding floor',
    'description' => 'Water pouring down from the ceiling near lights in room.',
    'category' => 'maintenance',
    'location_name' => "CS Block \xE2\x80\x94 Room 101",
    'room_number' => '101',
    'occurred_at' => date('Y-m-d H:i:s'),
];
$matchSame = IncidentMatcherService::calculateSimilarity($inc1, $incSameRoom);
assertTest("Same building AND same room gives full location score (0.35)", $matchSame['breakdown']['location'] == 0.35, "Got: " . $matchSame['breakdown']['location']);
assertTest("Same building AND same room marked as duplicate candidate", $matchSame['isCandidate'] === true, "Score: " . $matchSame['score']);

// Same building, DIFFERENT room: MUST NOT match as duplicate candidate
$incDiffRoom = [
    'title' => 'Ceiling pipe leak flooding floor',
    'description' => 'Water pouring down from the ceiling near lights in room.',
    'category' => 'maintenance',
    'location_name' => "CS Block \xE2\x80\x94 Room 204",
    'room_number' => '204',
    'occurred_at' => date('Y-m-d H:i:s'),
];
$matchDiff = IncidentMatcherService::calculateSimilarity($inc1, $incDiffRoom);
assertTest("Same building but DIFFERENT room gets discounted location score (0.05)", $matchDiff['breakdown']['location'] == 0.05, "Got: " . $matchDiff['breakdown']['location']);
assertTest("Same building but DIFFERENT room NOT marked as duplicate candidate (score < 0.60)", $matchDiff['isCandidate'] === false, "Score: " . $matchDiff['score']);

// TEST GROUP 6: Database Persistence & Retrieval
echo "\n--- 6. Database Persistence & Model Verification ---\n";
$pdo = Database::getConnection();
if ($pdo) {
    // 6A. Incident with room_number
    $incNum = 'INC-' . date('Y') . '-' . rand(10000, 99999);
    $incId = Incident::create([
        'incident_number' => $incNum,
        'reporter_user_id' => 1,
        'category' => 'maintenance',
        'title' => 'Test Broken AC in Mechanical Block',
        'description' => 'AC unit is humming loudly and blowing hot air.',
        'location_name' => "Mechanical Block \xE2\x80\x94 Room 205",
        'room_number' => '205',
        'privacy_level' => 'standard',
        'status' => 'reported',
    ]);
    assertTest("Incident with room_number created in database", $incId !== null && $incId > 0);

    if ($incId) {
        $fetched = Incident::findById($incId);
        assertTest("Incident retrieved with correct room_number '205'", ($fetched['room_number'] ?? null) === '205');
        assertTest("Incident retrieved with formatted location_name", str_contains($fetched['location_name'] ?? '', 'Mechanical Block'));
        
        // Cleanup test record
        $pdo->exec("DELETE FROM incidents WHERE id = {$incId}");
    }

    // 6B. SOS with room_number
    $sosData = [
        'locationName' => 'Civil Block',
        'roomNumber' => 'B-104',
        'coordinatesLat' => null,
        'coordinatesLng' => null,
    ];
    $sosResult = SosDispatchService::triggerSos($sosData, 1);
    assertTest("SOS triggered successfully with Civil Block Room B-104", !empty($sosResult['id']));
    
    if (!empty($sosResult['id'])) {
        $sosId = (int) $sosResult['id'];
        $fetchedSos = SosAlert::findById($sosId);
        assertTest("SosAlert retrieved with correct room_number 'B-104'", ($fetchedSos['room_number'] ?? null) === 'B-104');
        assertTest("SosAlert retrieved with formatted location_name", str_contains($fetchedSos['location_name'] ?? '', 'Civil Block'));
        
        // Cleanup test SOS
        $pdo->exec("DELETE FROM sos_alerts WHERE id = {$sosId}");
    }

    // 6C. SOS with general location (no room)
    $sosGenData = [
        'locationName' => 'Basketball Court',
        'coordinatesLat' => null,
        'coordinatesLng' => null,
    ];
    $sosGenResult = SosDispatchService::triggerSos($sosGenData, 1);
    assertTest("SOS triggered successfully with Basketball Court (no room)", !empty($sosGenResult['id']));
    
    if (!empty($sosGenResult['id'])) {
        $sosGenId = (int) $sosGenResult['id'];
        $fetchedGenSos = SosAlert::findById($sosGenId);
        assertTest("SosAlert for general area has room_number NULL", empty($fetchedGenSos['room_number']));
        assertTest("SosAlert has location_name 'Basketball Court'", ($fetchedGenSos['location_name'] ?? '') === 'Basketball Court');
        
        // Cleanup test SOS
        $pdo->exec("DELETE FROM sos_alerts WHERE id = {$sosGenId}");
    }

    // 6D. Verify building SOS fails without room number
    $threwException = false;
    try {
        SosDispatchService::triggerSos(['locationName' => 'Noran Girls Hostel'], 1);
    } catch (\InvalidArgumentException $e) {
        $threwException = true;
    }
    assertTest("SOS trigger throws InvalidArgumentException when building has no room number", $threwException);
} else {
    echo "  [WARN] Database connection not available for Group 6 tests.\n";
}

echo "\n=================================================================\n";
echo " Test Summary: {$passed} Passed, {$failed} Failed\n";
echo "=================================================================\n";

exit($failed > 0 ? 1 : 0);

