-- CampusSOS Migration: Add emergency_type and emergency_details to sos_alerts table
-- Preserves existing records and deterministic SOS dispatch flow

ALTER TABLE `sos_alerts`
  ADD COLUMN IF NOT EXISTS `emergency_type` VARCHAR(100) NULL AFTER `room_number`,
  ADD COLUMN IF NOT EXISTS `emergency_details` TEXT NULL AFTER `emergency_type`;

