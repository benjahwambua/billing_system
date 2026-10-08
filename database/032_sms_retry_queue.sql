-- Flexihub Billing System
-- Phase 35: SMS retry queue hardening
USE billing_system;

ALTER TABLE sms_messages
  ADD COLUMN IF NOT EXISTS next_attempt_at DATETIME NULL,
  ADD COLUMN IF NOT EXISTS last_attempt_at DATETIME NULL;

CREATE INDEX IF NOT EXISTS idx_sms_retry_queue
  ON sms_messages(status,next_attempt_at,attempts,tenant_id);
