-- Flexihub Billing System
-- Phase 36: OLT/ONU live reachability polling
USE billing_system;

ALTER TABLE olt_devices
  ADD COLUMN IF NOT EXISTS last_poll_at DATETIME NULL,
  ADD COLUMN IF NOT EXISTS last_poll_error VARCHAR(500) NULL;

ALTER TABLE onu_devices
  ADD COLUMN IF NOT EXISTS last_poll_at DATETIME NULL,
  ADD COLUMN IF NOT EXISTS last_poll_error VARCHAR(500) NULL;
