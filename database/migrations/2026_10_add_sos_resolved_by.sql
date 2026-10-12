-- Migration: Add resolved_by_user_id to sos_alerts table
ALTER TABLE `sos_alerts`
  ADD COLUMN IF NOT EXISTS `resolved_by_user_id` INT UNSIGNED NULL AFTER `resolution_notes`,
  ADD CONSTRAINT `fk_sos_resolved_by_user` FOREIGN KEY IF NOT EXISTS (`resolved_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

