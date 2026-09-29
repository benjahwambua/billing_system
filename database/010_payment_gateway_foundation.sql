-- Flexihub Billing System
-- Phase 10: Central payment gateway foundation
-- M-Pesa is a payment rail. Customer collections, tenant wallet activity,
-- and platform subscription revenue remain separate accounting flows.
--
-- Tenant IDs use INT UNSIGNED to match the current tenants.id schema.

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
    initiator_name_encrypted TEXT NULL,
    security_credential_encrypted TEXT NULL,
    callback_url VARCHAR(500) NULL,
    confirmation_url VARCHAR(500) NULL,
    validation_url VARCHAR(500) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'inactive',
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    last_verified_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_payment_gateway_name (tenant_id, provider, name),
    KEY idx_payment_gateways_tenant (tenant_id),
    KEY idx_payment_gateways_status (tenant_id, status),
    CONSTRAINT fk_payment_gateways_tenant
        FOREIGN KEY (tenant_id) REFERENCES tenants(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
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
    invoice_id BIGINT UNSIGNED NULL,
    payment_id BIGINT UNSIGNED NULL,
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
    KEY idx_gateway_tx_provider_id (tenant_id, provider_transaction_id),
    KEY idx_gateway_tx_invoice (tenant_id, invoice_id),
    KEY idx_gateway_tx_payment (tenant_id, payment_id),
    KEY idx_gateway_tx_hotspot (tenant_id, hotspot_sale_id),
    CONSTRAINT fk_gateway_tx_tenant
        FOREIGN KEY (tenant_id) REFERENCES tenants(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_gateway_tx_gateway
        FOREIGN KEY (gateway_id) REFERENCES payment_gateways(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
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
    KEY idx_gateway_events_tx (gateway_transaction_id),
    CONSTRAINT fk_gateway_events_tenant
        FOREIGN KEY (tenant_id) REFERENCES tenants(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_gateway_events_gateway
        FOREIGN KEY (gateway_id) REFERENCES payment_gateways(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_gateway_events_tx
        FOREIGN KEY (gateway_transaction_id) REFERENCES payment_gateway_transactions(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;