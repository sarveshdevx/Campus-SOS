-- Migration: Add SOS alerts support and uploader ID to incident_attachments table
-- CampusSOS Visual Evidence for Emergency Response & Incident Reporting

ALTER TABLE `incident_attachments`
  MODIFY `incident_id` INT UNSIGNED NULL,
  ADD COLUMN `sos_alert_id` INT UNSIGNED NULL AFTER `incident_id`,
  ADD COLUMN `uploaded_by_user_id` INT UNSIGNED NULL AFTER `file_size`;

ALTER TABLE `incident_attachments`
  ADD CONSTRAINT `fk_attachments_sos` FOREIGN KEY (`sos_alert_id`) REFERENCES `sos_alerts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_attachments_user` FOREIGN KEY (`uploaded_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD INDEX `idx_attachments_sos` (`sos_alert_id`);
