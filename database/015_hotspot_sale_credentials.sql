-- Flexihub Billing System
-- Phase 15: Hotspot sale credential/runtime repair
-- Run after 014_hotspot_captive_portal.sql.

USE billing_system;

ALTER TABLE hotspot_sales
    ADD COLUMN IF NOT EXISTS hotspot_username VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS hotspot_password_encrypted TEXT NULL;
