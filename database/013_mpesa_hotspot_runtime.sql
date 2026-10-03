-- Flexihub Billing System
-- Phase 13: M-Pesa gateway + public hotspot portal runtime
-- Run after 012_hotspot_router_runtime.sql.

USE billing_system;

CREATE TABLE IF NOT EXISTS payment_gateways (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    provider VARCHAR(40) NOT NULL DEFAULT 'mpesa',
    name VARCHAR(150) NOT NULL,
    environment VARCHAR(20) NOT NULL DEFAULT 'sandbox',
    shortcode_type VARCHAR(30) NULL,
    shortcode VARCHAR(40) NULL,
    consumer_key_encrypted TEXT NULL,
    consumer_secret_encrypted TEXT NULL,
    passkey_encrypted TEXT NULL,
    callback_token VARCHAR(120) NULL,
    callback_url VARCHAR(500) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'inactive',
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    last_verified_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_payment_gateway_name (tenant_id, provider, name),
    UNIQUE KEY uq_payment_gateway_callback_token (callback_token),
    KEY idx_payment_gateways_tenant_status (tenant_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payment_gateway_transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    gateway_id BIGINT UNSIGNED NOT NULL,
    provider VARCHAR(40) NOT NULL DEFAULT 'mpesa',
    flow VARCHAR(30) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'initiated',
    amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    phone_number VARCHAR(30) NULL,
    account_reference VARCHAR(100) NULL,
    transaction_description VARCHAR(255) NULL,
    checkout_request_id VARCHAR(150) NULL,
    merchant_request_id VARCHAR(150) NULL,
    provider_transaction_id VARCHAR(150) NULL,
    provider_receipt VARCHAR(100) NULL,
    result_code VARCHAR(30) NULL,
    result_description VARCHAR(500) NULL,
    failure_reason VARCHAR(500) NULL,
    hotspot_sale_id BIGINT UNSIGNED NULL,
    idempotency_key VARCHAR(180) NOT NULL,
    request_payload JSON NULL,
    callback_payload JSON NULL,
    initiated_at DATETIME NULL,
    confirmed_at DATETIME NULL,
    expires_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_gateway_tx_idempotency (tenant_id, idempotency_key),
    UNIQUE KEY uq_gateway_checkout (tenant_id, checkout_request_id),
    KEY idx_gateway_tx_tenant_status (tenant_id, status),
    KEY idx_gateway_tx_hotspot (tenant_id, hotspot_sale_id),
    KEY idx_gateway_tx_provider_id (tenant_id, provider_transaction_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payment_gateway_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NULL,
    gateway_id BIGINT UNSIGNED NULL,
    gateway_transaction_id BIGINT UNSIGNED NULL,
    event_type VARCHAR(40) NOT NULL,
    event_key VARCHAR(180) NULL,
    payload JSON NULL,
    processed TINYINT(1) NOT NULL DEFAULT 0,
    processed_at DATETIME NULL,
    error_message VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_gateway_event_key (event_key),
    KEY idx_gateway_events_tenant (tenant_id, created_at),
    KEY idx_gateway_events_tx (gateway_transaction_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS hotspot_portal_settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    public_token VARCHAR(128) NOT NULL,
    portal_name VARCHAR(150) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_hotspot_portal_tenant (tenant_id),
    UNIQUE KEY uq_hotspot_portal_token (public_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE hotspot_sales
    ADD COLUMN phone_number VARCHAR(30) NULL,
    ADD COLUMN device_mac VARCHAR(64) NULL,
    ADD COLUMN gateway_transaction_id BIGINT UNSIGNED NULL,
    ADD COLUMN payment_id BIGINT UNSIGNED NULL,
    ADD COLUMN started_at DATETIME NULL,
    ADD COLUMN expires_at DATETIME NULL,
    ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

CREATE INDEX idx_hotspot_sales_gateway ON hotspot_sales (tenant_id, gateway_transaction_id);
CREATE INDEX idx_hotspot_sales_payment ON hotspot_sales (tenant_id, payment_id);

INSERT INTO hotspot_portal_settings (tenant_id, public_token, portal_name, status)
SELECT t.id,
       SHA2(CONCAT('flexihub-portal:', t.id, ':', UUID(), ':', RAND()), 256),
       NULL,
       'active'
FROM tenants t
LEFT JOIN hotspot_portal_settings h ON h.tenant_id=t.id
WHERE h.id IS NULL;
