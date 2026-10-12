-- =============================================================================
-- Migration: 2026_10_fix_incident_matches_and_merges.sql
-- Enables cross-record duplicate matching (incident vs incident, sos vs sos, incident vs sos)
-- and safe, reversible transactional merge relationships.
-- =============================================================================

-- 1. Alter incident_matches to support polymorphic pairs
ALTER TABLE `incident_matches`
  DROP FOREIGN KEY `incident_matches_ibfk_1`,
  DROP FOREIGN KEY `incident_matches_ibfk_2`;

ALTER TABLE `incident_matches`
  DROP CONSTRAINT `chk_incident_pair_order`;

ALTER TABLE `incident_matches`
  DROP INDEX `uq_incident_pair`;

ALTER TABLE `incident_matches`
  ADD COLUMN `record_a_type` ENUM('incident', 'sos') NOT NULL DEFAULT 'incident' AFTER `id`,
  ADD COLUMN `record_b_type` ENUM('incident', 'sos') NOT NULL DEFAULT 'incident' AFTER `record_a_type`,
  ADD COLUMN `match_breakdown` JSON NULL AFTER `rationale`,
  ADD UNIQUE KEY `uq_record_pair` (`record_a_type`, `incident_a_id`, `record_b_type`, `incident_b_id`);

-- 2. Alter incidents table for merge tracking
ALTER TABLE `incidents`
  ADD COLUMN `merged_into_incident_id` INT UNSIGNED NULL AFTER `ai_confidence`,
  ADD COLUMN `merged_at` DATETIME NULL AFTER `merged_into_incident_id`,
  ADD COLUMN `merged_by_user_id` INT UNSIGNED NULL AFTER `merged_at`,
  ADD COLUMN `merge_notes` TEXT NULL AFTER `merged_by_user_id`,
  ADD INDEX `idx_incidents_merged_into` (`merged_into_incident_id`),
  ADD CONSTRAINT `fk_incidents_merged_into` FOREIGN KEY (`merged_into_incident_id`) REFERENCES `incidents` (`id`) ON DELETE SET NULL;

-- 3. Alter sos_alerts table for merge tracking
ALTER TABLE `sos_alerts`
  ADD COLUMN `merged_into_incident_id` INT UNSIGNED NULL AFTER `resolution_notes`,
  ADD COLUMN `merged_into_sos_id` INT UNSIGNED NULL AFTER `merged_into_incident_id`,
  ADD COLUMN `merged_at` DATETIME NULL AFTER `merged_into_sos_id`,
  ADD COLUMN `merged_by_user_id` INT UNSIGNED NULL AFTER `merged_at`,
  ADD COLUMN `merge_notes` TEXT NULL AFTER `merged_by_user_id`,
  ADD INDEX `idx_sos_merged_incident` (`merged_into_incident_id`),
  ADD INDEX `idx_sos_merged_sos` (`merged_into_sos_id`);

