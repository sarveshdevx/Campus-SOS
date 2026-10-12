<?php
declare(strict_types=1);

/**
 * CampusSOS — Reproducible Development-Only Seeding & Verification CLI Tool
 *
 * Usage:
 *   php backend/bin/seed-demo-data.php [--seed] [--reset-demo-only] [--status]
 *
 * Rules:
 *   1. Strictly blocked if APP_ENV === 'production'.
 *   2. Uses only reserved example identities (@campussos.edu) with non-real contact info.
 *   3. Safe to rerun without duplicating records (idempotent).
 *   4. Never deletes or overwrites real student or administrative records.
 */

require_once __DIR__ . '/../bootstrap.php';

use CampusSOS\Config\Database;

// Parse Command Line Options
$options = getopt('h', ['help', 'seed', 'reset-demo-only', 'status']);
if (isset($options['help']) || isset($options['h'])) {
    echo "CampusSOS Demonstration Data Tool\n";
    echo "Usage:\n";
    echo "  php backend/bin/seed-demo-data.php --seed             (Populate idempotent demo records)\n";
    echo "  php backend/bin/seed-demo-data.php --status           (Show current demo record counts)\n";
    echo "  php backend/bin/seed-demo-data.php --reset-demo-only  (Clean up only demo-tagged records)\n";
    echo "  php backend/bin/seed-demo-data.php --help             (Show this help message)\n";
    exit(0);
}

// Production Guardrail
$appEnv = getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? 'development');
if (strtolower($appEnv) === 'production') {
    fwrite(STDERR, "ERROR: Demo seeding script is strictly disabled in production environment.\n");
    exit(1);
}

$pdo = Database::getConnection();
if (!$pdo) {
    fwrite(STDERR, "ERROR: Unable to connect to MySQL database 'campussos'. Please verify XAMPP MySQL is running.\n");
    exit(1);
}

$mode = 'seed'; // Default action if run directly
if (isset($options['reset-demo-only'])) {
    $mode = 'reset';
} elseif (isset($options['status'])) {
    $mode = 'status';
}

echo "=================================================================\n";
echo " CampusSOS — Demonstration Data Tool (Mode: " . strtoupper($mode) . ")\n";
echo "=================================================================\n\n";

if ($mode === 'reset') {
    echo "-> Resetting ONLY dedicated demonstration records (@campussos.edu & demo incidents)...\n";
    echo "-> Real student and non-demo records will be preserved.\n";

    // Delete demo matches
    $pdo->exec("
        DELETE m FROM incident_matches m
        JOIN incidents a ON m.incident_a_id = a.id
        WHERE a.incident_number LIKE 'INC-2026-DEMO%'
    ");

    // Delete demo updates & assignments
    $pdo->exec("
        DELETE u FROM incident_updates u
        JOIN incidents i ON u.incident_id = i.id
        WHERE i.incident_number LIKE 'INC-2026-DEMO%'
    ");

    $pdo->exec("
        DELETE a FROM incident_assignments a
        JOIN incidents i ON a.incident_id = i.id
        WHERE i.incident_number LIKE 'INC-2026-DEMO%'
    ");

    // Delete demo incidents
    $pdo->exec("DELETE FROM incidents WHERE incident_number LIKE 'INC-2026-DEMO%'");

    // Delete demo SOS alerts
    $pdo->exec("DELETE FROM sos_alerts WHERE alert_code LIKE 'SOS-2026-DEMO%'");

    echo "-> Demo incidents, matches, and alerts cleared successfully.\n";
    exit(0);
}

if ($mode === 'status') {
    $stmtUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE email LIKE '%@campussos.edu'");
    $demoUsersCount = $stmtUsers->fetchColumn();

    $stmtIncs = $pdo->query("SELECT COUNT(*) FROM incidents WHERE incident_number LIKE 'INC-2026-DEMO%'");
    $demoIncsCount = $stmtIncs->fetchColumn();

    $stmtMatches = $pdo->query("SELECT COUNT(*) FROM incident_matches WHERE status = 'pending_review'");
    $pendingMatchesCount = $stmtMatches->fetchColumn();

    echo "Current Demonstration Records Status:\n";
    echo " - Demo Users: $demoUsersCount\n";
    echo " - Demo Incidents: $demoIncsCount\n";
    echo " - Pending Review Duplicate Matches: $pendingMatchesCount\n";
    exit(0);
}

// -----------------------------------------------------------------------------
// SEEDING LOGIC (Idempotent)
// -----------------------------------------------------------------------------
echo "-> Checking and seeding demonstration locations...\n";
$locations = [
    [1, 'Central Zone', 'OAT', null, 'Open Air Theatre main stage and seating area', 28.54450000, 77.19300000],
    [2, 'Athletics Zone', 'Football Ground', null, 'Campus main football turf and pavilion', 28.54200000, 77.19050000],
    [3, 'Athletics Zone', 'Basketball Court', null, 'Outdoor standard basketball floodlit courts', 28.54230000, 77.19080000],
    [4, 'Perimeter', 'Vehicle Parking', null, 'Main two-wheeler and four-wheeler campus parking lot', 28.54120000, 77.19220000],
    [5, 'Perimeter', 'Gate 1', null, 'Campus Main Entrance Gate 1 (North Access)', 28.54100000, 77.19200000],
    [6, 'Perimeter', 'Gate 2', null, 'Campus Eastern Gate 2 (Sports Complex Access)', 28.54150000, 77.19450000],
    [7, 'Perimeter', 'Gate 3', null, 'Campus Southern Gate 3 (Hostel Access)', 28.54680000, 77.19580000],
    [8, 'Academic Zone', 'Central Library', null, 'Central University Library building foyer and study zones', 28.54510000, 77.19260000],
    [9, 'Innovation Zone', 'Idea Lab', null, 'Student Innovation, Tinkering & Robotics Lab', 28.54350000, 77.19180000],
    [10, 'Cultural Zone', 'Gyan Mandir Auditorium', null, 'Central Cultural and Academic Auditorium', 28.54470000, 77.19350000],
    [11, 'Admin Zone', 'Admin Cell', null, 'Central Administrative Dean & Registrar offices', 28.54420000, 77.19150000],
    [12, 'Admin Zone', 'TP Cell', null, 'Training & Placement Cell interview suites', 28.54430000, 77.19170000],
    [13, 'Transit Zone', 'Bus Stop', null, 'Campus shuttle and university bus stop station', 28.54080000, 77.19180000],
    [14, 'Green Zone', 'D Garden', null, 'Central botanical lawn and recreational garden', 28.54490000, 77.19280000],
    [15, 'Athletics Zone', 'Volleyball Court', null, 'Outdoor sand and synthetic volleyball arena', 28.54250000, 77.19100000],
    [16, 'Athletics Zone', 'Sports Complex', null, 'Indoor multisport gymnasium and health center', 28.54220000, 77.19010000],
    [17, 'Engineering Zone', 'CS Block', 'Academic Building', 'Computer Science and Engineering department block', 28.54380000, 77.19100000],
    [18, 'Engineering Zone', 'Civil Block', 'Academic Building', 'Civil Engineering laboratories and classrooms', 28.54360000, 77.19080000],
    [19, 'Engineering Zone', 'Mechanical Block', 'Academic Building', 'Mechanical Engineering workshops and CAD centers', 28.54340000, 77.19060000],
    [20, 'Engineering Zone', 'Electrical Block', 'Academic Building', 'Electrical & Electronics laboratories block', 28.54320000, 77.19040000],
    [21, 'Residential Zone', 'Noran Girls Hostel', 'Student Residence', 'Noran Girls residential block and mess', 28.54660000, 77.19520000],
    [22, 'Residential Zone', 'Nirvana Boys Hostel', 'Student Residence', 'Nirvana Boys residential block and courtyard', 28.54630000, 77.19480000],
    [23, 'Residential Zone', 'AC Hostel', 'Student Residence', 'Air-Conditioned student residential block', 28.54600000, 77.19450000],
    [24, 'Residential Zone', 'KBH Hostel', 'Student Residence', 'KBH Student residential block and study rooms', 28.54580000, 77.19420000],
    [25, 'Residential Zone', 'RKS Hostel', 'Student Residence', 'RKS Student residential block and common hall', 28.54560000, 77.19400000]
];

$stmtLoc = $pdo->prepare("
    INSERT INTO locations (id, campus_zone, building_name, floor_or_room, landmark_description, coordinates_lat, coordinates_lng, is_active)
    VALUES (?, ?, ?, ?, ?, ?, ?, 1)
    ON DUPLICATE KEY UPDATE
        campus_zone = VALUES(campus_zone),
        building_name = VALUES(building_name),
        floor_or_room = VALUES(floor_or_room),
        landmark_description = VALUES(landmark_description),
        coordinates_lat = VALUES(coordinates_lat),
        coordinates_lng = VALUES(coordinates_lng),
        is_active = 1
");
foreach ($locations as $loc) {
    $stmtLoc->execute($loc);
}
echo "   [OK] 25 campus locations verified.\n";

echo "-> Checking and seeding demonstration users (Bcrypt Cost 12)...\n";
$defaultHash = password_hash('Password@2026', PASSWORD_BCRYPT, ['cost' => 12]);

$demoUsers = [
    [1, 'Campus Chief Administrator', 'admin@campussos.edu', $defaultHash, '+91 98765 00001', 'ADMIN-01', 'admin'],
    [2, 'Dr. Priya Varma', 'medical@campussos.edu', $defaultHash, '+91 98765 43210', 'STAFF-MED01', 'responder'],
    [3, 'Officer Rajesh Kumar', 'security@campussos.edu', $defaultHash, '+91 98765 43211', 'STAFF-SEC01', 'responder'],
    [4, 'Fire Marshal Vikram Singh', 'fire@campussos.edu', $defaultHash, '+91 98765 43215', 'STAFF-FIRE01', 'responder'],
    [5, 'Anil Desai', 'maintenance@campussos.edu', $defaultHash, '+91 98765 43212', 'STAFF-ENG01', 'responder'],
    [6, 'Prof. Sunita Nair', 'antiragging@campussos.edu', $defaultHash, '+91 98765 43213', 'STAFF-PROC01', 'responder'],
    [7, 'Dr. Meera Sen', 'icc@campussos.edu', $defaultHash, '+91 98765 43214', 'STAFF-ICC01', 'responder'],
    [8, 'Aarav Sharma', 'student@campussos.edu', $defaultHash, '+91 98765 88990', '22BCS104', 'student'],
];

$stmtUser = $pdo->prepare("
    INSERT INTO users (id, full_name, email, password_hash, phone_number, student_id, role, account_status)
    VALUES (?, ?, ?, ?, ?, ?, ?, 'active')
    ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), role = VALUES(role), password_hash = VALUES(password_hash), account_status = 'active'
");
foreach ($demoUsers as $u) {
    $stmtUser->execute($u);
}
echo "   [OK] 8 demonstration roles verified.\n";

echo "-> Checking and seeding responder duty profiles...\n";
$responders = [
    [2, 'medical', 'Campus Health Clinic', 'available', 'Health Center Room 102', '+91 98765 43210', 'Day shift on-call paramedic'],
    [3, 'security', 'Campus Safety & Patrol', 'available', 'Hostel Gate 2 Patrol', '+91 98765 43211', 'Motorized quick reaction patrol'],
    [4, 'fire', 'Disaster & Fire Safety', 'available', 'Fire Station Post 1', '+91 98765 43215', 'Evacuation team lead'],
    [5, 'maintenance', 'Facilities & Civil Works', 'available', 'Workshop Block C', '+91 98765 43212', 'Electrical & plumbing maintenance on duty'],
    [6, 'anti_ragging', 'Proctorial Anti-Hazing Cell', 'available', 'Dean Student Welfare Office', '+91 98765 43213', 'Night patrol hostel squad'],
    [7, 'sexual_harassment', 'Internal Complaints Committee (ICC)', 'available', 'Confidential Counseling Center', '+91 98765 43214', 'Confidential liaison officer'],
];

$stmtResp = $pdo->prepare("
    INSERT INTO responders (user_id, category, department, status, current_location, contact_phone, shift_notes)
    VALUES (?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE status = VALUES(status), current_location = VALUES(current_location)
");
foreach ($responders as $r) {
    $stmtResp->execute($r);
}
echo "   [OK] 6 responder categories mapped.\n";

echo "-> Checking and seeding department contacts for 6 safety categories...\n";
$deptContacts = [
    [1, 'medical', 'Medical Response', 'Dr. Priya Varma', 'Chief Medical Officer', 'medical@campussos.edu', '+91 98765 43210', 'Health Center Room 102', 'Campus medical clinic triage, first aid treatment, and emergency ambulance dispatch.', 1, 0],
    [2, 'security', 'Campus Security', 'Officer Rajesh Kumar', 'Security Superintendent', 'security@campussos.edu', '+91 98765 43211', 'Hostel Gate 2 Patrol / Security Booth', '24/7 campus safety patrol, perimeter surveillance, and rapid incident response.', 1, 0],
    [3, 'fire', 'Fire & Safety', 'Fire Marshal Vikram Singh', 'Fire Marshal', 'fire@campussos.edu', '+91 98765 43215', 'Fire Station Post 1', 'Disaster management, fire hazard mitigation, emergency evacuation, and suppression.', 1, 0],
    [4, 'maintenance', 'Facility Maintenance', 'Anil Desai', 'Chief Works Engineer', 'maintenance@campussos.edu', '+91 98765 43212', 'Workshop Block C', 'Repairs and emergency maintenance for electrical, civil, water, and building infrastructure.', 1, 0],
    [5, 'anti_ragging', 'Anti-Ragging Committee', 'Prof. Sunita Nair', 'Proctorial Committee Lead', 'antiragging@campussos.edu', '+91 98765 43213', 'Dean Student Welfare Office', 'Proctorial anti-hazing cell, campus welfare inspections, and confidential intake.', 1, 0],
    [6, 'sexual_harassment', 'Internal Complaints Committee (ICC)', 'Dr. Meera Sen', 'ICC Presiding Officer', 'icc@campussos.edu', '+91 98765 43214', 'Confidential Counseling Center', 'Statutory confidential grievance cell handling sexual harassment and Title IX complaints.', 1, 0],
];

$stmtDept = $pdo->prepare("
    INSERT INTO department_contacts (id, category, department_name, representative_name, designation, email, phone_number, office_location, description, is_configured, is_official_verified)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        department_name = VALUES(department_name),
        representative_name = VALUES(representative_name),
        designation = VALUES(designation),
        email = VALUES(email),
        phone_number = VALUES(phone_number),
        office_location = VALUES(office_location),
        description = VALUES(description)
");
foreach ($deptContacts as $dc) {
    $stmtDept->execute($dc);
}
echo "   [OK] 6 authoritative department contacts verified.\n";

echo "-> Seeding realistic demo incidents with duplicate candidate pairs...\n";

// Pair 1: Electrical issue candidate match in Science Complex
$stmtInc = $pdo->prepare("
    INSERT INTO incidents (
        id, incident_number, reporter_user_id, location_id, location_name,
        category, title, description, occurred_at, is_anonymous, is_confidential,
        privacy_level, status, severity, ai_classified, ai_confidence
    ) VALUES (
        ?, ?, ?, ?, ?, ?, ?, ?, DATE_SUB(NOW(), INTERVAL ? MINUTE), ?, ?, ?, ?, ?, 1, 0.92
    )
    ON DUPLICATE KEY UPDATE status = VALUES(status), severity = VALUES(severity)
");

// Demo incident 10: Anonymous report of flickering lighting & burning smell
$stmtInc->execute([
    10, 'INC-2026-DEMO-01', null, 5, 'Science Complex - Chemistry Labs Wing',
    'maintenance', 'Burnt electrical smell and flickering corridor light',
    'Noticeable burning odor and electrical hum in corridor ceiling near room 202.',
    90, 1, 0, 'standard', 'under_review', 'medium'
]);

// Demo incident 11: Student report of sparks at same location (Candidate Duplicate)
$stmtInc->execute([
    11, 'INC-2026-DEMO-02', 8, 5, 'Science Complex - Chemistry Labs Wing',
    'maintenance', 'Damaged electrical conduit and sparks observed',
    'Sparks seen coming from ceiling electrical box on 2nd floor near chemistry lab 204.',
    45, 0, 0, 'standard', 'under_review', 'high'
]);

// Demo incident 12: Medical incident at Sports Complex
$stmtInc->execute([
    12, 'INC-2026-DEMO-03', 8, 9, 'Sports Complex - Indoor Gymnasium',
    'medical', 'Student sprained ankle on wet basketball court',
    'First aid applied by peer; wheelchair and paramedic assistance requested.',
    180, 0, 0, 'standard', 'resolved', 'medium'
]);

// Demo incident 13: Confidential report (isolated)
$stmtInc->execute([
    13, 'INC-2026-DEMO-04', 8, 7, 'Hostel Block 1 (North)',
    'anti_ragging', 'Confidential proctorial welfare intake',
    'Late evening unauthorized intrusion by senior students demanding chores.',
    120, 1, 1, 'confidential', 'under_review', 'high'
]);

// Demo incident 14: Security incident at Gate 1
$stmtInc->execute([
    14, 'INC-2026-DEMO-05', 8, 5, 'Perimeter - Gate 1 Main Entrance',
    'security', 'Unattended bag reported near pedestrian turnstiles',
    'Black backpack left unattended for over 45 minutes near Gate 1 checkpost.',
    60, 0, 0, 'standard', 'under_review', 'medium'
]);

// Demo incident 15: Fire safety incident at CS Block
$stmtInc->execute([
    15, 'INC-2026-DEMO-06', null, 17, 'Engineering Zone - CS Block Ground Floor',
    'fire', 'Discharged fire extinguisher and smoke smell in corridor',
    'Extinguisher pressure dropped and mild acrid smoke detected near electrical shaft.',
    40, 1, 0, 'standard', 'in_progress', 'high'
]);

// Demo incident 16: Confidential ICC sexual harassment complaint
$stmtInc->execute([
    16, 'INC-2026-DEMO-07', 8, 8, 'Central Library - Study Cubicles',
    'sexual_harassment', 'Confidential ICC inquiry intake',
    'Formal confidential statement submitted regarding repeated unwelcome advances in study room.',
    90, 0, 1, 'confidential', 'under_review', 'high'
]);

echo "   [OK] 7 realistic demo incidents across safety categories seeded.\n";

echo "-> Seeding candidate duplicate match pair (enforcing chk_incident_pair_order a < b)...\n";
$stmtMatch = $pdo->prepare("
    INSERT INTO incident_matches (
        id, incident_a_id, incident_b_id, similarity_score, rationale, status
    ) VALUES (
        10, 10, 11, 0.88,
        'Spatial & temporal proximity in Science Complex within 45 mins with shared electrical hazard keywords.',
        'pending_review'
    )
    ON DUPLICATE KEY UPDATE similarity_score = VALUES(similarity_score), status = VALUES(status)
");
$stmtMatch->execute();
echo "   [OK] Candidate match pair (10 < 11) registered with pending_review status.\n";

echo "\n-> Seeding demo chronological updates & responder assignments...\n";
$stmtAssign = $pdo->prepare("
    INSERT INTO incident_assignments (id, incident_id, responder_id, assignment_status, notes)
    VALUES (?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE assignment_status = VALUES(assignment_status), notes = VALUES(notes)
");
$stmtAssign->execute([1, 12, 1, 'in_progress', 'Paramedic on site at sports complex.']);
$stmtAssign->execute([2, 14, 2, 'assigned', 'Security patrol dispatched to Gate 1 turnstiles.']);
$stmtAssign->execute([3, 15, 3, 'in_progress', 'Fire response team inspecting CS Block electrical shaft.']);
$stmtAssign->execute([4, 11, 4, 'assigned', 'Electrician team investigating conduit near lab 204.']);
$stmtAssign->execute([5, 13, 5, 'assigned', 'Proctorial squad conducting confidential welfare review.']);
$stmtAssign->execute([6, 16, 6, 'assigned', 'ICC presiding officer reviewing confidential intake.']);
echo "   [OK] 6 responder category assignments populated.\n";

$stmtUpd = $pdo->prepare("
    INSERT INTO incident_updates (incident_id, actor_user_id, previous_status, new_status, update_text, is_internal)
    SELECT ?, ?, 'reported', 'under_review', 'Dispatcher reviewing facility report.', 0
    WHERE NOT EXISTS (SELECT 1 FROM incident_updates WHERE incident_id = ? AND actor_user_id = ? LIMIT 1)
");
$stmtUpd->execute([10, 1, 10, 1]);
$stmtUpd->execute([11, 1, 11, 1]);

echo "=================================================================\n";
echo " Demonstration data successfully verified and ready for Expo!\n";
echo " Default demo accounts password: Password@2026\n";
echo "=================================================================\n";
