-- Flexihub Billing System
-- Phase 12: Hotspot router runtime
-- Run after 011_pppoe_router_runtime.sql.

ALTER TABLE hotspot_packages ADD COLUMN router_id BIGINT UNSIGNED NULL AFTER tenant_id;
CREATE INDEX idx_hotspot_packages_router ON hotspot_packages (tenant_id, router_id);
