-- Flexihub Billing System
-- Phase 29: OLT/ONU operational management metadata
USE billing_system;

ALTER TABLE olt_devices
  ADD COLUMN IF NOT EXISTS protocol VARCHAR(20) NOT NULL DEFAULT 'snmp',
  ADD COLUMN IF NOT EXISTS snmp_version VARCHAR(10) NULL,
  ADD COLUMN IF NOT EXISTS polling_enabled TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE onu_devices
  ADD COLUMN IF NOT EXISTS mac_address VARCHAR(64) NULL,
  ADD COLUMN IF NOT EXISTS vendor VARCHAR(80) NULL,
  ADD COLUMN IF NOT EXISTS model VARCHAR(120) NULL,
  ADD COLUMN IF NOT EXISTS optical_rx_dbm DECIMAL(8,2) NULL,
  ADD COLUMN IF NOT EXISTS optical_tx_dbm DECIMAL(8,2) NULL;

CREATE INDEX IF NOT EXISTS idx_olt_polling ON olt_devices(tenant_id,polling_enabled,status);
CREATE INDEX IF NOT EXISTS idx_onu_mac ON onu_devices(tenant_id,mac_address);
