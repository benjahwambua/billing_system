-- Hotspot online payment and session foundation
-- Run after 007_module_foundation.sql and 010_payment_gateway_foundation.sql.

-- Normalize hotspot tenant keys to the current tenants.id type where the tables already exist.
ALTER TABLE hotspot_packages MODIFY tenant_id INT UNSIGNED NOT NULL;
ALTER TABLE hotspot_access_codes MODIFY tenant_id INT UNSIGNED NOT NULL;

CREATE TABLE IF NOT EXISTS hotspot_sales (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    package_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    phone_number VARCHAR(30) NULL,
    device_mac VARCHAR(50) NULL,
    payment_method VARCHAR(30) NOT NULL DEFAULT 'mpesa',
    reference VARCHAR(150) NULL,
    gateway_transaction_id BIGINT UNSIGNED NULL,
    payment_id BIGINT UNSIGNED NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    started_at DATETIME NULL,
    expires_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_hs_tenant_created (tenant_id, created_at),
    KEY idx_hs_tenant_status (tenant_id, status),
    KEY idx_hs_gateway (tenant_id, gateway_transaction_id),
    KEY idx_hs_payment (tenant_id, payment_id),
    CONSTRAINT fk_hs_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_hs_package FOREIGN KEY (package_id) REFERENCES hotspot_packages(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_hs_gateway FOREIGN KEY (gateway_transaction_id) REFERENCES payment_gateway_transactions(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS hotspot_sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    sale_id INT UNSIGNED NOT NULL,
    router_id BIGINT UNSIGNED NULL,
    username VARCHAR(100) NULL,
    session_token VARCHAR(180) NOT NULL,
    mac_address VARCHAR(50) NULL,
    ip_address VARCHAR(45) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    started_at DATETIME NULL,
    expires_at DATETIME NULL,
    ended_at DATETIME NULL,
    bytes_in BIGINT UNSIGNED NOT NULL DEFAULT 0,
    bytes_out BIGINT UNSIGNED NOT NULL DEFAULT 0,
    disconnect_reason VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_hotspot_session_token (tenant_id, session_token),
    KEY idx_hotspot_sessions_tenant_status (tenant_id, status),
    KEY idx_hotspot_sessions_sale (tenant_id, sale_id),
    CONSTRAINT fk_hss_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_hss_sale FOREIGN KEY (sale_id) REFERENCES hotspot_sales(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- The gateway transaction needs an explicit target account for future PPPoE/invoice
-- finalization; hotspot sales use hotspot_sale_id instead.
ALTER TABLE payment_gateway_transactions
    ADD COLUMN account_id INT UNSIGNED NULL AFTER invoice_id;

ALTER TABLE payment_gateway_transactions
    ADD KEY idx_gateway_tx_account (tenant_id, account_id);

ALTER TABLE payment_gateway_transactions
    ADD CONSTRAINT fk_gateway_tx_account
    FOREIGN KEY (account_id) REFERENCES internet_accounts(id)
    ON UPDATE CASCADE ON DELETE SET NULL;
