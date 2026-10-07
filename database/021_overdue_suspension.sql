-- Flexihub Billing System
-- Phase 21: Overdue billing, grace periods and automatic suspension
-- Run after 020_automated_recurring_billing.sql.

USE billing_system;

ALTER TABLE internet_plans
    ADD COLUMN IF NOT EXISTS grace_period_days INT UNSIGNED NOT NULL DEFAULT 3 AFTER billing_days;

ALTER TABLE invoices
    ADD COLUMN IF NOT EXISTS overdue_since DATE NULL AFTER invoice_source,
    ADD COLUMN IF NOT EXISTS suspension_triggered_at DATETIME NULL AFTER overdue_since;

CREATE INDEX IF NOT EXISTS idx_invoices_overdue
    ON invoices (tenant_id, due_date, status);

CREATE INDEX IF NOT EXISTS idx_internet_plans_grace
    ON internet_plans (tenant_id, grace_period_days);
