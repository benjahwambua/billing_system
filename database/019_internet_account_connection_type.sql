USE billing_system;

ALTER TABLE internet_accounts ADD COLUMN IF NOT EXISTS connection_type VARCHAR(30) NOT NULL DEFAULT 'pppoe' AFTER plan_id;

CREATE INDEX IF NOT EXISTS idx_internet_accounts_connection_type ON internet_accounts (tenant_id, connection_type);
