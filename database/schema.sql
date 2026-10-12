-- =============================================================================
-- CampusSOS — Relational Database Schema (Phase 2)
-- Database Engine: MySQL 8.0+ / MariaDB 10.4+ (InnoDB Engine)
-- Character Set: utf8mb4 / Collation: utf8mb4_unicode_ci
-- Administered through phpMyAdmin in local XAMPP on macOS
-- =============================================================================

CREATE DATABASE IF NOT EXISTS `campussos`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `campussos`;

SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- 1. Table: users
-- Core authentication and identity table for students, staff, and admins.
-- Privacy rule: Passwords are NEVER stored in plaintext (bcrypt cost 12).
-- Account status supports soft suspension without data loss.
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `full_name` VARCHAR(120) NOT NULL,
  `email` VARCHAR(191) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `phone_number` VARCHAR(25) NULL,
  `student_id` VARCHAR(50) NULL,
  `role` ENUM('student', 'responder', 'admin') NOT NULL DEFAULT 'student',
  `account_status` ENUM('active', 'suspended', 'pending') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_users_role` (`role`),
  INDEX `idx_users_status` (`account_status`),
  INDEX `idx_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2. Table: emergency_contacts
-- Next-of-kin / emergency contacts for students.
-- Safety rule: Cascades on user deletion because contacts belong exclusively to the user.
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `emergency_contacts`;
CREATE TABLE `emergency_contacts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `contact_name` VARCHAR(120) NOT NULL,
  `relationship` VARCHAR(50) NOT NULL,
  `phone_number` VARCHAR(25) NOT NULL,
  `email` VARCHAR(191) NULL,
  `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  INDEX `idx_emergency_contacts_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 3. Table: locations
-- Standardized campus landmarks, blocks, and buildings.
-- Flexibility: Coordinates (lat/lng) are strictly optional, allowing indoors/floors
-- without requiring GPS triangulation.
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `locations`;
CREATE TABLE `locations` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `campus_zone` VARCHAR(80) NOT NULL,
  `building_name` VARCHAR(120) NOT NULL,
  `floor_or_room` VARCHAR(80) NULL,
  `landmark_description` VARCHAR(255) NOT NULL,
  `coordinates_lat` DECIMAL(10, 8) NULL,
  `coordinates_lng` DECIMAL(11, 8) NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_locations_zone` (`campus_zone`),
  INDEX `idx_locations_building` (`building_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 4. Table: responders
-- Authorized campus safety personnel duty profiles.
-- Safety rule: Responders must map to an active user account with role='responder'.
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `responders`;
CREATE TABLE `responders` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL UNIQUE,
  `category` ENUM('medical', 'security', 'fire', 'maintenance', 'anti_ragging', 'sexual_harassment') NOT NULL,
  `department` VARCHAR(100) NOT NULL,
  `status` ENUM('available', 'busy', 'offline') NOT NULL DEFAULT 'available',
  `current_location` VARCHAR(120) NULL,
  `contact_phone` VARCHAR(25) NOT NULL,
  `shift_notes` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  INDEX `idx_responders_category` (`category`),
  INDEX `idx_responders_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 4b. Table: department_contacts
-- Official institution-managed public contact information for 6 safety departments.
-- Public read access; authorized administrator updates only.
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `department_contacts`;
CREATE TABLE `department_contacts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `category` ENUM('medical', 'security', 'fire', 'maintenance', 'anti_ragging', 'sexual_harassment') NOT NULL UNIQUE,
  `department_name` VARCHAR(120) NOT NULL,
  `representative_name` VARCHAR(120) NULL,
  `designation` VARCHAR(120) NULL,
  `email` VARCHAR(191) NULL,
  `phone_number` VARCHAR(25) NULL,
  `office_location` VARCHAR(150) NULL,
  `description` VARCHAR(255) NULL,
  `is_configured` TINYINT(1) NOT NULL DEFAULT 1,
  `is_official_verified` TINYINT(1) NOT NULL DEFAULT 0,
  `updated_by_user_id` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`updated_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  INDEX `idx_dept_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 5. Table: incidents
-- Routine safety concern intake and tracking.
-- Privacy & Safety Rules:
-- 1. reporter_user_id is NULLable. For anonymous reports (is_anonymous=1),
--    API queries MUST NOT return reporter_user_id or reporter identity.
-- 2. ON DELETE SET NULL: Deleting a student user never erases public safety history.
-- 3. is_confidential=1: Restricts viewing exclusively to authorized investigation units
--    (e.g., ICC / Title IX for sexual harassment, Chief Proctor for anti-ragging).
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `incidents`;
CREATE TABLE `incidents` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `incident_number` VARCHAR(30) NOT NULL UNIQUE,
  `reporter_user_id` INT UNSIGNED NULL,
  `location_id` INT UNSIGNED NULL,
  `location_name` VARCHAR(150) NOT NULL,
  `room_number` VARCHAR(50) NULL,
  `coordinates_lat` DECIMAL(10, 8) NULL,
  `coordinates_lng` DECIMAL(11, 8) NULL,
  `category` ENUM('medical', 'security', 'fire', 'maintenance', 'anti_ragging', 'sexual_harassment', 'other') NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT NOT NULL,
  `occurred_at` DATETIME NOT NULL,
  `is_anonymous` TINYINT(1) NOT NULL DEFAULT 0,
  `is_confidential` TINYINT(1) NOT NULL DEFAULT 0,
  `privacy_level` ENUM('standard', 'confidential', 'sensitive') NOT NULL DEFAULT 'standard',
  `status` ENUM('reported', 'under_review', 'dispatched', 'in_progress', 'resolved', 'closed', 'rejected') NOT NULL DEFAULT 'reported',
  `severity` ENUM('low', 'medium', 'high', 'critical') NOT NULL DEFAULT 'medium',
  `ai_classified` TINYINT(1) NOT NULL DEFAULT 0,
  `ai_confidence` DECIMAL(4, 2) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`reporter_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL,
  INDEX `idx_incidents_category` (`category`),
  INDEX `idx_incidents_status` (`status`),
  INDEX `idx_incidents_severity` (`severity`),
  INDEX `idx_incidents_occurred` (`occurred_at`),
  INDEX `idx_incidents_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 6. Table: sos_alerts
-- Urgent life-safety emergency signals.
-- Safety rule: Zero-latency deterministic dispatch (No AI dependency).
-- History protection: user_id ON DELETE SET NULL retains audit of life-safety events.
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `sos_alerts`;
CREATE TABLE `sos_alerts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `alert_code` VARCHAR(30) NOT NULL UNIQUE,
  `user_id` INT UNSIGNED NULL,
  `incident_id` INT UNSIGNED NULL,
  `location_id` INT UNSIGNED NULL,
  `location_name` VARCHAR(150) NOT NULL,
  `room_number` VARCHAR(50) NULL,
  `emergency_type` VARCHAR(100) NULL,
  `emergency_details` TEXT NULL,
  `coordinates_lat` DECIMAL(10, 8) NULL,
  `coordinates_lng` DECIMAL(11, 8) NULL,
  `alert_type` ENUM('sos', 'medical_emergency', 'silent_alarm') NOT NULL DEFAULT 'sos',
  `status` ENUM('triggered', 'acknowledged', 'dispatched', 'resolved', 'false_alarm') NOT NULL DEFAULT 'triggered',
  `triggered_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `acknowledged_at` DATETIME NULL,
  `resolved_at` DATETIME NULL,
  `resolution_notes` TEXT NULL,
  `resolved_by_user_id` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`resolved_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL,
  INDEX `idx_sos_status` (`status`),
  INDEX `idx_sos_triggered` (`triggered_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 7. Table: sos_assignments (Justified extension table)
-- Multi-team dispatch tracking for life-safety emergency alerts.
-- Reason: Allows assigning multiple responders (e.g., Medical + Security) to one SOS
-- while individually tracking each unit's acknowledgement and on-scene time.
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `sos_assignments`;
CREATE TABLE `sos_assignments` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `sos_alert_id` INT UNSIGNED NOT NULL,
  `responder_id` INT UNSIGNED NOT NULL,
  `assignment_status` ENUM('dispatched', 'acknowledged', 'en_route', 'on_scene', 'cleared') NOT NULL DEFAULT 'dispatched',
  `dispatched_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `acknowledged_at` DATETIME NULL,
  `arrived_at` DATETIME NULL,
  `notes` VARCHAR(255) NULL,
  FOREIGN KEY (`sos_alert_id`) REFERENCES `sos_alerts` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`responder_id`) REFERENCES `responders` (`id`) ON DELETE CASCADE,
  UNIQUE KEY `uq_sos_responder` (`sos_alert_id`, `responder_id`),
  INDEX `idx_sos_assign_status` (`assignment_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 8. Table: incident_assignments (Justified extension table)
-- Multi-responder assignments for routine incidents.
-- Reason: Many incidents require multi-disciplinary handling (e.g. Facilities + Safety).
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `incident_assignments`;
CREATE TABLE `incident_assignments` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `incident_id` INT UNSIGNED NOT NULL,
  `responder_id` INT UNSIGNED NOT NULL,
  `assignment_status` ENUM('assigned', 'in_progress', 'completed', 'reassigned') NOT NULL DEFAULT 'assigned',
  `assigned_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` DATETIME NULL,
  `notes` TEXT NULL,
  FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`responder_id`) REFERENCES `responders` (`id`) ON DELETE CASCADE,
  UNIQUE KEY `uq_incident_responder` (`incident_id`, `responder_id`),
  INDEX `idx_inc_assign_status` (`assignment_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 9. Table: incident_attachments (Justified extension table)
-- Evidence photos (max 2 per incident).
-- Sensitive decision: Photos are visible only to authorized dispatchers/responders.
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `incident_attachments`;
CREATE TABLE `incident_attachments` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `incident_id` INT UNSIGNED NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `file_name` VARCHAR(150) NOT NULL,
  `file_type` VARCHAR(50) NOT NULL,
  `file_size` INT UNSIGNED NOT NULL,
  `uploaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`) ON DELETE CASCADE,
  INDEX `idx_attachments_incident` (`incident_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 10. Table: incident_matches
-- Duplicate detection with mandatory human-in-the-loop review.
-- Integrity rules:
-- 1. incident_a_id < incident_b_id prevents duplicate reversed pairs (A,B) vs (B,A).
-- 2. Prevents self-matching (incident matched with itself).
-- 3. Unique key uq_incident_pair guarantees no duplicate candidate rows.
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `incident_matches`;
CREATE TABLE `incident_matches` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `incident_a_id` INT UNSIGNED NOT NULL,
  `incident_b_id` INT UNSIGNED NOT NULL,
  `similarity_score` DECIMAL(4, 2) NOT NULL,
  `rationale` VARCHAR(255) NOT NULL,
  `status` ENUM('pending_review', 'confirmed_duplicate', 'dismissed') NOT NULL DEFAULT 'pending_review',
  `reviewed_by_user_id` INT UNSIGNED NULL,
  `reviewed_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `chk_incident_pair_order` CHECK (`incident_a_id` < `incident_b_id`),
  FOREIGN KEY (`incident_a_id`) REFERENCES `incidents` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`incident_b_id`) REFERENCES `incidents` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`reviewed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  UNIQUE KEY `uq_incident_pair` (`incident_a_id`, `incident_b_id`),
  INDEX `idx_matches_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 11. Table: incident_updates
-- Chronological status transitions, investigator notes, and public updates.
-- actor_user_id ON DELETE SET NULL preserves audit trail if a staff member departs.
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `incident_updates`;
CREATE TABLE `incident_updates` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `incident_id` INT UNSIGNED NOT NULL,
  `actor_user_id` INT UNSIGNED NULL,
  `previous_status` ENUM('reported', 'under_review', 'dispatched', 'in_progress', 'resolved', 'closed', 'rejected') NULL,
  `new_status` ENUM('reported', 'under_review', 'dispatched', 'in_progress', 'resolved', 'closed', 'rejected') NOT NULL,
  `update_text` TEXT NOT NULL,
  `is_internal` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  INDEX `idx_updates_incident` (`incident_id`),
  INDEX `idx_updates_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 12. Table: notifications
-- In-app notifications for students and responders.
-- Privacy rule: Notification messages must be concise summaries without leaking
-- confidential details or reporter identities.
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `recipient_user_id` INT UNSIGNED NOT NULL,
  `notification_type` ENUM('sos_dispatch', 'incident_status', 'assignment', 'system') NOT NULL,
  `message` VARCHAR(255) NOT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `link_url` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`recipient_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  INDEX `idx_notifications_user_read` (`recipient_user_id`, `is_read`),
  INDEX `idx_notifications_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 13. Table: audit_logs
-- Immutable security audit log.
-- Privacy rule: NEVER stores passwords, session tokens, or API secrets.
-- Only records user action, resource identifier, IP address, and timestamp.
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `actor_user_id` INT UNSIGNED NULL,
  `action` VARCHAR(60) NOT NULL,
  `resource_type` VARCHAR(40) NOT NULL,
  `resource_id` INT UNSIGNED NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `user_agent` VARCHAR(255) NULL,
  `details` JSON NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  INDEX `idx_audit_action` (`action`),
  INDEX `idx_audit_resource` (`resource_type`, `resource_id`),
  INDEX `idx_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
