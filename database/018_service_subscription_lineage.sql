-- Flexihub Billing System
-- Phase 18: Subscription lifecycle and renewal lineage
-- Run after the existing service subscription foundation.

USE billing_system;

ALTER TABLE service_subscriptions
    ADD COLUMN IF NOT EXISTS previous_subscription_id BIGINT UNSIGNED NULL AFTER payment_id;

CREATE INDEX IF NOT EXISTS idx_service_subscriptions_previous
    ON service_subscriptions (tenant_id, previous_subscription_id);

CREATE INDEX IF NOT EXISTS idx_service_subscriptions_account_status
    ON service_subscriptions (tenant_id, account_id, status);
