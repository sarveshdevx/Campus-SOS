-- =============================================================================
-- Migration: Add department_contacts table
-- Authoritative public contact information for 6 safety responder departments
-- Safe & Idempotent: Does not affect existing users, incidents, or SOS records
-- =============================================================================

CREATE TABLE IF NOT EXISTS `department_contacts` (
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

-- Populate default department records matching the 6 responder categories
INSERT INTO `department_contacts`
  (`id`, `category`, `department_name`, `representative_name`, `designation`, `email`, `phone_number`, `office_location`, `description`, `is_configured`, `is_official_verified`)
VALUES
  (1, 'medical', 'Medical Response', 'Dr. Priya Varma', 'Chief Medical Officer', 'medical@campussos.edu', '+91 98765 43210', 'Health Center Room 102', 'Campus medical triage, first aid treatment, and emergency ambulance dispatch.', 1, 0),
  (2, 'security', 'Campus Security', 'Officer Rajesh Kumar', 'Security Superintendent', 'security@campussos.edu', '+91 98765 43211', 'Hostel Gate 2 Patrol / Security Booth', '24/7 campus safety patrol, perimeter surveillance, and rapid incident response.', 1, 0),
  (3, 'fire', 'Fire & Safety', 'Fire Marshal Vikram Singh', 'Fire Marshal', 'fire@campussos.edu', '+91 98765 43215', 'Fire Station Post 1', 'Disaster management, fire hazard mitigation, emergency evacuation, and suppression.', 1, 0),
  (4, 'maintenance', 'Facility Maintenance', 'Anil Desai', 'Chief Works Engineer', 'maintenance@campussos.edu', '+91 98765 43212', 'Workshop Block C', 'Repairs and emergency maintenance for electrical, civil, water, and building infrastructure.', 1, 0),
  (5, 'anti_ragging', 'Anti-Ragging Committee', 'Prof. Sunita Nair', 'Proctorial Committee Lead', 'antiragging@campussos.edu', '+91 98765 43213', 'Dean Student Welfare Office', 'Proctorial anti-hazing cell, campus welfare inspections, and confidential intake.', 1, 0),
  (6, 'sexual_harassment', 'Internal Complaints Committee (ICC)', 'Dr. Meera Sen', 'ICC Presiding Officer', 'icc@campussos.edu', '+91 98765 43214', 'Confidential Counseling Center', 'Statutory confidential grievance cell handling sexual harassment and Title IX complaints.', 1, 0)
ON DUPLICATE KEY UPDATE
  `department_name` = VALUES(`department_name`),
  `representative_name` = VALUES(`representative_name`),
  `designation` = VALUES(`designation`),
  `email` = VALUES(`email`),
  `phone_number` = VALUES(`phone_number`),
  `office_location` = VALUES(`office_location`),
  `description` = VALUES(`description`);

