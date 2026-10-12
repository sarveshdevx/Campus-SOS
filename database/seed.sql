-- =============================================================================
-- CampusSOS — Demonstration Seed Data (Phase 2)
-- Database: campussos
-- For Local Development & Student Project Expo Evaluation Only
--
-- Security Note:
-- All seed accounts are created with the temporary evaluation password:
-- Password: "Password@2026"
-- Password Hash: $2y$12$R9h/cIPz95b4yM88w8c4U.yO0b76zZlP.1j7aX93p6fG3V5tW8L2K
-- (Generated via PHP: password_hash('Password@2026', PASSWORD_BCRYPT, ['cost' => 12]))
-- In production, replace immediately or reset via user profile.
-- =============================================================================

USE `campussos`;

SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- 1. Campus Landmark Locations (Idempotent)
-- -----------------------------------------------------------------------------
INSERT INTO `locations` (`id`, `campus_zone`, `building_name`, `floor_or_room`, `landmark_description`, `coordinates_lat`, `coordinates_lng`, `is_active`) VALUES
(1, 'Central Zone', 'OAT', NULL, 'Open Air Theatre main stage and seating area', 28.54450000, 77.19300000, 1),
(2, 'Athletics Zone', 'Football Ground', NULL, 'Campus main football turf and pavilion', 28.54200000, 77.19050000, 1),
(3, 'Athletics Zone', 'Basketball Court', NULL, 'Outdoor standard basketball floodlit courts', 28.54230000, 77.19080000, 1),
(4, 'Perimeter', 'Vehicle Parking', NULL, 'Main two-wheeler and four-wheeler campus parking lot', 28.54120000, 77.19220000, 1),
(5, 'Perimeter', 'Gate 1', NULL, 'Campus Main Entrance Gate 1 (North Access)', 28.54100000, 77.19200000, 1),
(6, 'Perimeter', 'Gate 2', NULL, 'Campus Eastern Gate 2 (Sports Complex Access)', 28.54150000, 77.19450000, 1),
(7, 'Perimeter', 'Gate 3', NULL, 'Campus Southern Gate 3 (Hostel Access)', 28.54680000, 77.19580000, 1),
(8, 'Academic Zone', 'Central Library', NULL, 'Central University Library building foyer and study zones', 28.54510000, 77.19260000, 1),
(9, 'Innovation Zone', 'Idea Lab', NULL, 'Student Innovation, Tinkering & Robotics Lab', 28.54350000, 77.19180000, 1),
(10, 'Cultural Zone', 'Gyan Mandir Auditorium', NULL, 'Central Cultural and Academic Auditorium', 28.54470000, 77.19350000, 1),
(11, 'Admin Zone', 'Admin Cell', NULL, 'Central Administrative Dean & Registrar offices', 28.54420000, 77.19150000, 1),
(12, 'Admin Zone', 'TP Cell', NULL, 'Training & Placement Cell interview suites', 28.54430000, 77.19170000, 1),
(13, 'Transit Zone', 'Bus Stop', NULL, 'Campus shuttle and university bus stop station', 28.54080000, 77.19180000, 1),
(14, 'Green Zone', 'D Garden', NULL, 'Central botanical lawn and recreational garden', 28.54490000, 77.19280000, 1),
(15, 'Athletics Zone', 'Volleyball Court', NULL, 'Outdoor sand and synthetic volleyball arena', 28.54250000, 77.19100000, 1),
(16, 'Athletics Zone', 'Sports Complex', NULL, 'Indoor multisport gymnasium and health center', 28.54220000, 77.19010000, 1),
(17, 'Engineering Zone', 'CS Block', 'Academic Building', 'Computer Science and Engineering department block', 28.54380000, 77.19100000, 1),
(18, 'Engineering Zone', 'Civil Block', 'Academic Building', 'Civil Engineering laboratories and classrooms', 28.54360000, 77.19080000, 1),
(19, 'Engineering Zone', 'Mechanical Block', 'Academic Building', 'Mechanical Engineering workshops and CAD centers', 28.54340000, 77.19060000, 1),
(20, 'Engineering Zone', 'Electrical Block', 'Academic Building', 'Electrical & Electronics laboratories block', 28.54320000, 77.19040000, 1),
(21, 'Residential Zone', 'Noran Girls Hostel', 'Student Residence', 'Noran Girls residential block and mess', 28.54660000, 77.19520000, 1),
(22, 'Residential Zone', 'Nirvana Boys Hostel', 'Student Residence', 'Nirvana Boys residential block and courtyard', 28.54630000, 77.19480000, 1),
(23, 'Residential Zone', 'AC Hostel', 'Student Residence', 'Air-Conditioned student residential block', 28.54600000, 77.19450000, 1),
(24, 'Residential Zone', 'KBH Hostel', 'Student Residence', 'KBH Student residential block and study rooms', 28.54580000, 77.19420000, 1),
(25, 'Residential Zone', 'RKS Hostel', 'Student Residence', 'RKS Student residential block and common hall', 28.54560000, 77.19400000, 1)
ON DUPLICATE KEY UPDATE
  `campus_zone` = VALUES(`campus_zone`),
  `building_name` = VALUES(`building_name`),
  `floor_or_room` = VALUES(`floor_or_room`),
  `landmark_description` = VALUES(`landmark_description`),
  `coordinates_lat` = VALUES(`coordinates_lat`),
  `coordinates_lng` = VALUES(`coordinates_lng`),
  `is_active` = VALUES(`is_active`);

-- -----------------------------------------------------------------------------
-- 2. Demonstration Users (Idempotent)
-- -----------------------------------------------------------------------------
INSERT INTO `users` (`id`, `full_name`, `email`, `password_hash`, `phone_number`, `student_id`, `role`, `account_status`) VALUES
(1, 'Campus Chief Administrator', 'admin@campussos.edu', '$2y$12$R.BIA/Nv9R3WFYi5028cSOkeWnmritVfOFYxdLmXbiSoJFtwqM8r.', '+91 98765 00001', 'ADMIN-01', 'admin', 'active'),
(2, 'Dr. Priya Varma', 'medical@campussos.edu', '$2y$12$R.BIA/Nv9R3WFYi5028cSOkeWnmritVfOFYxdLmXbiSoJFtwqM8r.', '+91 98765 43210', 'STAFF-MED01', 'responder', 'active'),
(3, 'Officer Rajesh Kumar', 'security@campussos.edu', '$2y$12$R.BIA/Nv9R3WFYi5028cSOkeWnmritVfOFYxdLmXbiSoJFtwqM8r.', '+91 98765 43211', 'STAFF-SEC01', 'responder', 'active'),
(4, 'Fire Marshal Vikram Singh', 'fire@campussos.edu', '$2y$12$R.BIA/Nv9R3WFYi5028cSOkeWnmritVfOFYxdLmXbiSoJFtwqM8r.', '+91 98765 43215', 'STAFF-FIRE01', 'responder', 'active'),
(5, 'Anil Desai', 'maintenance@campussos.edu', '$2y$12$R.BIA/Nv9R3WFYi5028cSOkeWnmritVfOFYxdLmXbiSoJFtwqM8r.', '+91 98765 43212', 'STAFF-ENG01', 'responder', 'active'),
(6, 'Prof. Sunita Nair', 'antiragging@campussos.edu', '$2y$12$R.BIA/Nv9R3WFYi5028cSOkeWnmritVfOFYxdLmXbiSoJFtwqM8r.', '+91 98765 43213', 'STAFF-PROC01', 'responder', 'active'),
(7, 'Dr. Meera Sen', 'icc@campussos.edu', '$2y$12$R.BIA/Nv9R3WFYi5028cSOkeWnmritVfOFYxdLmXbiSoJFtwqM8r.', '+91 98765 43214', 'STAFF-ICC01', 'responder', 'active'),
(8, 'Aarav Sharma', 'student@campussos.edu', '$2y$12$R.BIA/Nv9R3WFYi5028cSOkeWnmritVfOFYxdLmXbiSoJFtwqM8r.', '+91 98765 88990', '22BCS104', 'student', 'active')
ON DUPLICATE KEY UPDATE
  `full_name` = VALUES(`full_name`),
  `role` = VALUES(`role`),
  `password_hash` = VALUES(`password_hash`),
  `account_status` = VALUES(`account_status`);

-- -----------------------------------------------------------------------------
-- 3. Responders Duty Profiles (Idempotent)
-- -----------------------------------------------------------------------------
INSERT INTO `responders` (`user_id`, `category`, `department`, `status`, `current_location`, `contact_phone`, `shift_notes`) VALUES
(2, 'medical', 'Campus Health Clinic', 'available', 'Health Center Room 102', '+91 98765 43210', 'Day shift on-call paramedic'),
(3, 'security', 'Campus Safety & Patrol', 'available', 'Hostel Gate 2 Patrol', '+91 98765 43211', 'Motorized mobile quick reaction patrol'),
(4, 'fire', 'Disaster & Fire Safety', 'available', 'Fire Station Post 1', '+91 98765 43215', 'Evacuation & extinguisher team lead'),
(5, 'maintenance', 'Facilities & Civil Works', 'available', 'Workshop Block C', '+91 98765 43212', 'Electrical & plumbing maintenance on duty'),
(6, 'anti_ragging', 'Proctorial Anti-Hazing Cell', 'available', 'Dean Student Welfare Office', '+91 98765 43213', 'Night patrol hostel squad member'),
(7, 'sexual_harassment', 'Internal Complaints Committee (ICC)', 'available', 'Confidential Counseling Center', '+91 98765 43214', 'Confidential liaison officer')
ON DUPLICATE KEY UPDATE
  `status` = VALUES(`status`),
  `current_location` = VALUES(`current_location`),
  `contact_phone` = VALUES(`contact_phone`);

-- -----------------------------------------------------------------------------
-- 4. Emergency Contacts for Student (Idempotent)
-- -----------------------------------------------------------------------------
INSERT INTO `emergency_contacts` (`id`, `user_id`, `contact_name`, `relationship`, `phone_number`, `email`, `is_primary`) VALUES
(1, 8, 'Ramesh Sharma', 'Father', '+91 98111 22334', 'ramesh.sharma@example.com', 1),
(2, 8, 'Sunita Sharma', 'Mother', '+91 98111 22335', 'sunita.sharma@example.com', 0)
ON DUPLICATE KEY UPDATE
  `contact_name` = VALUES(`contact_name`),
  `phone_number` = VALUES(`phone_number`);

-- -----------------------------------------------------------------------------
-- 5. Demonstration Incidents (Idempotent)
-- -----------------------------------------------------------------------------
INSERT INTO `incidents` (`id`, `incident_number`, `reporter_user_id`, `location_id`, `location_name`, `category`, `title`, `description`, `occurred_at`, `is_anonymous`, `is_confidential`, `privacy_level`, `status`, `severity`, `ai_classified`, `ai_confidence`) VALUES
(1, 'INC-2026-0840', NULL, 5, 'Science Complex - Chemistry Labs Wing', 'maintenance', 'Flickering corridor lighting and burnt smell', 'Noticeable burning odor and electrical hum in corridor ceiling near room 202.', DATE_SUB(NOW(), INTERVAL 2 HOUR), 1, 0, 'standard', 'under_review', 'medium', 1, 0.88),
(2, 'INC-2026-0841', 8, 5, 'Science Complex - Chemistry Labs Wing', 'maintenance', 'Damaged electrical conduit and sparks', 'Sparks observed on floor 2 corridor ceiling conduit near lab 204.', DATE_SUB(NOW(), INTERVAL 1 HOUR), 0, 0, 'standard', 'in_progress', 'high', 1, 0.94),
(3, 'INC-2026-0835', 8, 9, 'Sports Complex - Indoor Gymnasium', 'medical', 'Student sprained ankle on wet basketball court', 'First aid applied by peer, wheelchair assistance requested.', DATE_SUB(NOW(), INTERVAL 5 HOUR), 0, 0, 'standard', 'resolved', 'medium', 1, 0.98)
ON DUPLICATE KEY UPDATE
  `status` = VALUES(`status`),
  `severity` = VALUES(`severity`);

-- -----------------------------------------------------------------------------
-- 6. Incident Matches (Candidate Duplicate - Requires Human Review)
-- Note: incident_a_id (1) < incident_b_id (2) enforces pair ordering constraint
-- -----------------------------------------------------------------------------
INSERT INTO `incident_matches` (`id`, `incident_a_id`, `incident_b_id`, `similarity_score`, `rationale`, `status`) VALUES
(1, 1, 2, 0.88, 'Spatial & temporal proximity in Science Complex within 60 minutes with electrical hazard keywords', 'pending_review')
ON DUPLICATE KEY UPDATE
  `similarity_score` = VALUES(`similarity_score`),
  `status` = VALUES(`status`);

SET FOREIGN_KEY_CHECKS = 1;

