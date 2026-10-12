-- CampusSOS Migration: Add routing and triage fields to sos_alerts table
-- Supports automatic emergency category routing, AI-assisted classification, and Admin triage

ALTER TABLE `sos_alerts`
  ADD COLUMN IF NOT EXISTS `assigned_responder_id` INT UNSIGNED NULL AFTER `emergency_details`,
  ADD COLUMN IF NOT EXISTS `assigned_department` VARCHAR(50) NULL AFTER `assigned_responder_id`,
  ADD COLUMN IF NOT EXISTS `detected_category` VARCHAR(100) NULL AFTER `assigned_department`,
  ADD COLUMN IF NOT EXISTS `routing_decision` VARCHAR(255) NULL AFTER `detected_category`,
  ADD COLUMN IF NOT EXISTS `triage_status` VARCHAR(50) NOT NULL DEFAULT 'routed' AFTER `routing_decision`;

