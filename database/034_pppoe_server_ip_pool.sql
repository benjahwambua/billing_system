-- Flexihub Billing System
-- Phase 37: Associate tenant IP pools with PPPoE server records.
-- This is an application-enforced tenant relationship; no cross-tenant FK is introduced.
USE billing_system;

ALTER TABLE pppoe_servers
  ADD COLUMN IF NOT EXISTS ip_pool_id BIGINT UNSIGNED NULL;
