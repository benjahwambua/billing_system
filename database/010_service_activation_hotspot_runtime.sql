-- Flexihub Billing System
-- Phase 10: Service activation and hotspot/PPPoE runtime foundation
-- Run after 009_legacy_tenant_bootstrap.sql.
--
-- This migration is additive. It does not alter or remove existing business tables.
-- It provides the database state required to connect billing/payment events to
-- service activation, hotspot sessions and PPPoE/network authorization.

USE billing_system;

CREATE TABLE IF NOT EXISTS hotspot_sessions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    access_code_id BIGINT UNSIGNED NULL,
    package_id BIGINT UNSIGNED NULL,
    internet_account_id BIGINT UNSIGNED NULL,
    customer_id BIGINT UNSIGNED NULL,
    router_id BIGINT UNSIGNED NULL,
    session_identifier VARCHAR(150) NULL,
    username VARCHAR(150) NULL,
    mac_address VARCHAR(64) NULL,
    ip_address VARCHAR(64) NULL,
    started_at DATETIME NULL,
    expires_at DATETIME NULL,
    ended_at DATETIME NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    disconnect_reason VARCHAR(100) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_hotspot_session_identifier (tenant_id, session_identifier),
    KEY idx_hs_runtime_tenant_status (tenant_id, status),
    KEY idx_hs_runtime_account (tenant_id, internet_account_id),
    KEY idx_hs_runtime_customer (tenant_id, customer_id),
    KEY idx_hs_runtime_code (tenant_id, access_code_id),
    KEY idx_hs_runtime_router (tenant_id, router_id),
    KEY idx_hs_runtime_expiry (tenant_id, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS service_activation_queue (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    service_type VARCHAR(30) NOT NULL,
    account_id BIGINT UNSIGNED NULL,
    customer_id BIGINT UNSIGNED NULL,
    subscription_id BIGINT UNSIGNED NULL,
    payment_id BIGINT UNSIGNED NULL,
    action VARCHAR(30) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME NULL,
    last_error TEXT NULL,
    payload JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_activation_tenant_status (tenant_id, status),
    KEY idx_activation_account (tenant_id, account_id),
    KEY idx_activation_subscription (tenant_id, subscription_id),
    KEY idx_activation_available (status, available_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payment_callbacks (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    provider VARCHAR(40) NOT NULL,
    event_type VARCHAR(80) NULL,
    transaction_reference VARCHAR(150) NULL,
    external_transaction_id VARCHAR(150) NULL,
    phone_number VARCHAR(30) NULL,
    amount DECIMAL(14,2) NULL,
    callback_payload JSON NOT NULL,
    processing_status VARCHAR(30) NOT NULL DEFAULT 'received',
    processed_at DATETIME NULL,
    error_message TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_payment_callback_external (tenant_id, provider, external_transaction_id),
    KEY idx_payment_callback_tenant_status (tenant_id, processing_status),
    KEY idx_payment_callback_reference (tenant_id, transaction_reference),
    KEY idx_payment_callback_created (tenant_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS service_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NULL,
    customer_id BIGINT UNSIGNED NULL,
    subscription_id BIGINT UNSIGNED NULL,
    event_type VARCHAR(50) NOT NULL,
    old_status VARCHAR(30) NULL,
    new_status VARCHAR(30) NULL,
    source VARCHAR(50) NOT NULL DEFAULT 'system',
    reference VARCHAR(150) NULL,
    details JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_service_events_tenant (tenant_id, created_at),
    KEY idx_service_events_account (tenant_id, account_id, created_at),
    KEY idx_service_events_customer (tenant_id, customer_id, created_at),
    KEY idx_service_events_type (tenant_id, event_type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ensure every tenant has a wallet row. Existing balances are untouched.
INSERT INTO tenant_wallets (tenant_id, balance, currency)
SELECT t.id, 0, 'KES'
FROM tenants t
LEFT JOIN tenant_wallets w ON w.tenant_id = t.id
WHERE w.id IS NULL;

SELECT
    'Phase 10 database foundation installed' AS migration_status,
    (SELECT COUNT(*) FROM hotspot_sessions) AS hotspot_sessions,
    (SELECT COUNT(*) FROM service_activation_queue) AS activation_queue,
    (SELECT COUNT(*) FROM payment_callbacks) AS payment_callbacks,
    (SELECT COUNT(*) FROM service_events) AS service_events;
