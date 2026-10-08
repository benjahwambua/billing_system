-- Flexihub Billing System
-- MikroTik network monitoring persistence
USE billing_system;

ALTER TABLE mikrotik_routers
  ADD COLUMN IF NOT EXISTS last_poll_at DATETIME NULL,
  ADD COLUMN IF NOT EXISTS last_seen_at DATETIME NULL,
  ADD COLUMN IF NOT EXISTS last_poll_error VARCHAR(500) NULL,
  ADD COLUMN IF NOT EXISTS latency_ms DECIMAL(10,1) NULL;
