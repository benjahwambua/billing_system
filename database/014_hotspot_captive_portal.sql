-- Flexihub Billing System
-- Phase 14: Hotspot captive-portal login endpoint
-- Run after 013_mpesa_hotspot_runtime.sql.

USE billing_system;

ALTER TABLE mikrotik_routers
    ADD COLUMN IF NOT EXISTS hotspot_login_url VARCHAR(500) NULL AFTER api_port;
