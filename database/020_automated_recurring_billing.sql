-- Flexihub Billing System
-- Phase 20: Automated recurring billing
-- Run after 019_internet_account_connection_type.sql.

USE billing_system;

ALTER TABLE internet_accounts
    ADD COLUMN IF NOT EXISTS next_invoice_date DATE NULL AFTER billing_date;

ALTER TABLE invoices
    ADD COLUMN IF NOT EXISTS account_id BIGINT UNSIGNED NULL AFTER customer_id,
    ADD COLUMN IF NOT EXISTS billing_period_start DATE NULL AFTER due_date,
    ADD COLUMN IF NOT EXISTS billing_period_end DATE NULL AFTER billing_period_start,
    ADD COLUMN IF NOT EXISTS invoice_source VARCHAR(30) NULL AFTER billing_period_end;

CREATE INDEX IF NOT EXISTS idx_internet_accounts_next_invoice
    ON internet_accounts (tenant_id, next_invoice_date, status);

CREATE INDEX IF NOT EXISTS idx_invoices_account_period
    ON invoices (tenant_id, account_id, billing_period_start, billing_period_end);

-- Existing accounts already use billing_date as the next service/billing boundary.
UPDATE internet_accounts
SET next_invoice_date = billing_date
WHERE next_invoice_date IS NULL
  AND billing_date IS NOT NULL;

UPDATE internet_accounts
SET next_invoice_date = expiry_date
WHERE next_invoice_date IS NULL
  AND expiry_date IS NOT NULL;
