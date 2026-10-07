-- Flexihub Billing System
-- Phase 17: Hotspot payment finalization retry queue
-- Run after 016_hotspot_renewals.sql.

USE billing_system;

CREATE TABLE IF NOT EXISTS hotspot_finalization_queue (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    sale_id BIGINT UNSIGNED NOT NULL,
    gateway_transaction_id BIGINT UNSIGNED NULL,
    status ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME NULL,
    last_error VARCHAR(1000) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_hotspot_finalization_sale (tenant_id, sale_id),
    KEY idx_hotspot_finalization_queue (tenant_id, status, available_at),
    KEY idx_hotspot_finalization_tx (tenant_id, gateway_transaction_id),
    CONSTRAINT fk_hotspot_finalization_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_hotspot_finalization_sale FOREIGN KEY (sale_id) REFERENCES hotspot_sales(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
