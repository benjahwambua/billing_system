-- Flexihub Billing System
-- Phase 16: Hotspot renewal lineage
-- Run after 015_hotspot_sale_credentials.sql.

USE billing_system;

ALTER TABLE hotspot_sales
    ADD COLUMN IF NOT EXISTS renewed_from_sale_id BIGINT UNSIGNED NULL AFTER payment_id;

CREATE INDEX IF NOT EXISTS idx_hotspot_sales_renewed_from
    ON hotspot_sales (tenant_id, renewed_from_sale_id);
