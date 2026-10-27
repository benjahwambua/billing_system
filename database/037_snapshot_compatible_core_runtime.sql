-- Flexihub Billing System
-- Candidate schema foundation for the user-confirmed 2026-10-27 database export.
--
-- STATUS: REVIEW / DISPOSABLE-RESTORE ONLY. NOT APPROVED FOR THE WORKING DATABASE.
-- Restore the supplied export to a NEW test schema, select that schema, and run the
-- read-only preflights before reviewing this script. Never run against production
-- or the working XAMPP billing_system database.
--
-- Target: MariaDB 10.4.x (the supplied export reports 10.4.32).
-- This file creates only runtime/platform schema objects; it does not insert,
-- update, delete, or backfill customer, invoice, payment, receipt, or ledger rows.
--
-- Important:
-- * It does not replay historical migrations in numeric order.
-- * Tenant IDs referencing tenants.id use BIGINT UNSIGNED.
-- * CREATE TABLE IF NOT EXISTS does not repair an already-existing partial table.
--   If any target table is PRESENT in the preflight, inspect SHOW CREATE TABLE first.
-- * The conditional hotspot_packages change below only adds a column/index if absent.
-- * Do not add receipt claims until duplicate receipt reconciliation has been reviewed.

SELECT DATABASE() AS selected_test_schema,
       VERSION() AS database_server_version,
       'Confirm this is a disposable restored copy before continuing' AS safety_check;

SET @snapshot_schema := DATABASE();

-- Runtime gateway configuration. Corrects the INT UNSIGNED tenant-key mismatch
-- in historical migration 013 for this snapshot's tenants.id BIGINT UNSIGNED.
CREATE TABLE IF NOT EXISTS payment_gateways (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
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
    PRIMARY KEY (id),
    UNIQUE KEY uq_payment_gateway_name (tenant_id, provider, name),
    UNIQUE KEY uq_payment_gateway_callback_token (callback_token),
    KEY idx_payment_gateways_tenant_status (tenant_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payment_gateway_transactions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
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
    platform_invoice_id BIGINT UNSIGNED NULL,
    idempotency_key VARCHAR(180) NOT NULL,
    request_payload JSON NULL,
    callback_payload JSON NULL,
    initiated_at DATETIME NULL,
    confirmed_at DATETIME NULL,
    expires_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_gateway_tx_idempotency (tenant_id, idempotency_key),
    UNIQUE KEY uq_gateway_checkout (tenant_id, checkout_request_id),
    KEY idx_gateway_tx_tenant_status (tenant_id, status),
    KEY idx_gateway_tx_hotspot (tenant_id, hotspot_sale_id),
    KEY idx_gateway_tx_provider_id (tenant_id, provider_transaction_id),
    KEY idx_pgt_platform_invoice (platform_invoice_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payment_gateway_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NULL,
    gateway_id BIGINT UNSIGNED NULL,
    gateway_transaction_id BIGINT UNSIGNED NULL,
    event_type VARCHAR(40) NOT NULL,
    event_key VARCHAR(180) NULL,
    payload JSON NULL,
    processed TINYINT(1) NOT NULL DEFAULT 0,
    processed_at DATETIME NULL,
    error_message VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_gateway_event_key (event_key),
    KEY idx_gateway_events_tenant (tenant_id, created_at),
    KEY idx_gateway_events_tx (gateway_transaction_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS hotspot_portal_settings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    public_token VARCHAR(128) NOT NULL,
    portal_name VARCHAR(150) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_hotspot_portal_tenant (tenant_id),
    UNIQUE KEY uq_hotspot_portal_token (public_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Hotspot package/access-code foundation from migration 007, with the router
-- linkage from migration 012 included directly because these tables are absent
-- from the supplied export.
CREATE TABLE IF NOT EXISTS hotspot_packages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    router_id BIGINT UNSIGNED NULL,
    name VARCHAR(150) NOT NULL,
    duration_minutes INT UNSIGNED NOT NULL DEFAULT 60,
    price DECIMAL(14,2) NOT NULL DEFAULT 0,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_hotspot_packages_tenant (tenant_id),
    KEY idx_hotspot_packages_router (tenant_id, router_id),
    CONSTRAINT fk_hotspot_packages_tenant
        FOREIGN KEY (tenant_id) REFERENCES tenants(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS hotspot_access_codes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(100) NOT NULL,
    package_id BIGINT UNSIGNED NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'unused',
    expires_at DATETIME NULL,
    sold_at DATETIME NULL,
    activated_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_hotspot_code_tenant (tenant_id, code),
    KEY idx_hotspot_codes_tenant (tenant_id),
    CONSTRAINT fk_hotspot_codes_tenant
        FOREIGN KEY (tenant_id) REFERENCES tenants(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- The original hotspot_sales table was absent from the supplied export.
-- This combines only fields explicitly defined by migrations 008, 013, 015 and 016.
CREATE TABLE IF NOT EXISTS hotspot_sales (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    access_code_id BIGINT UNSIGNED NOT NULL,
    package_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    payment_method VARCHAR(30) NOT NULL DEFAULT 'cash',
    reference VARCHAR(100) NULL,
    sold_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(30) NOT NULL DEFAULT 'completed',
    phone_number VARCHAR(30) NULL,
    device_mac VARCHAR(64) NULL,
    gateway_transaction_id BIGINT UNSIGNED NULL,
    payment_id BIGINT UNSIGNED NULL,
    started_at DATETIME NULL,
    expires_at DATETIME NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    hotspot_username VARCHAR(100) NULL,
    hotspot_password_encrypted TEXT NULL,
    renewed_from_sale_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_hs_tenant (tenant_id),
    KEY idx_hs_code (tenant_id, access_code_id),
    KEY idx_hs_package (tenant_id, package_id),
    KEY idx_hs_sold_at (tenant_id, sold_at),
    KEY idx_hotspot_sales_gateway (tenant_id, gateway_transaction_id),
    KEY idx_hotspot_sales_payment (tenant_id, payment_id),
    KEY idx_hotspot_sales_renewed_from (tenant_id, renewed_from_sale_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
    CONSTRAINT fk_hotspot_finalization_tenant
        FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_hotspot_finalization_sale
        FOREIGN KEY (sale_id) REFERENCES hotspot_sales(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Subscription lineage foundation from migration 008 plus migration 018.
-- BIGINT UNSIGNED keys align with the current tenant key and later lineage columns.
CREATE TABLE IF NOT EXISTS service_subscriptions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    plan_id BIGINT UNSIGNED NOT NULL,
    invoice_id BIGINT UNSIGNED NULL,
    payment_id BIGINT UNSIGNED NULL,
    previous_subscription_id BIGINT UNSIGNED NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ss_tenant (tenant_id),
    KEY idx_ss_account (tenant_id, account_id),
    KEY idx_ss_customer (tenant_id, customer_id),
    KEY idx_ss_status (tenant_id, status),
    KEY idx_ss_invoice (tenant_id, invoice_id),
    KEY idx_service_subscriptions_previous (tenant_id, previous_subscription_id),
    KEY idx_service_subscriptions_account_status (tenant_id, account_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- The platform settings migration explicitly requires these key/value columns.
-- This minimal table definition is grounded in that INSERT contract; review against
-- every platform-settings code path before enabling platform billing.
CREATE TABLE IF NOT EXISTS platform_settings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_platform_settings_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS platform_plans (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(50) NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    price DECIMAL(14,2) NOT NULL DEFAULT 0,
    billing_cycle ENUM('monthly','quarterly','yearly') NOT NULL DEFAULT 'monthly',
    trial_days INT UNSIGNED NOT NULL DEFAULT 0,
    grace_days INT UNSIGNED NOT NULL DEFAULT 3,
    max_users INT UNSIGNED NULL,
    max_customers INT UNSIGNED NULL,
    max_routers INT UNSIGNED NULL,
    max_accounts INT UNSIGNED NULL,
    features JSON NULL,
    status ENUM('active','inactive','archived') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_platform_plans_code (code),
    KEY idx_platform_plans_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tenant_platform_subscriptions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    plan_id BIGINT UNSIGNED NOT NULL,
    status ENUM('trial','active','past_due','suspended','cancelled','expired') NOT NULL DEFAULT 'trial',
    started_at DATETIME NULL,
    trial_ends_at DATETIME NULL,
    current_period_start DATE NULL,
    current_period_end DATE NULL,
    grace_ends_at DATETIME NULL,
    cancelled_at DATETIME NULL,
    cancellation_reason VARCHAR(255) NULL,
    last_invoice_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tenant_current_platform_subscription (tenant_id),
    KEY idx_tps_status_period (status, current_period_end),
    KEY idx_tps_plan (plan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS platform_invoices (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    subscription_id BIGINT UNSIGNED NOT NULL,
    invoice_number VARCHAR(80) NOT NULL,
    period_start DATE NULL,
    period_end DATE NULL,
    issue_date DATE NOT NULL,
    due_date DATE NOT NULL,
    subtotal DECIMAL(14,2) NOT NULL DEFAULT 0,
    tax_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    total_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    paid_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    status ENUM('draft','unpaid','partial','paid','overdue','cancelled') NOT NULL DEFAULT 'unpaid',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_platform_invoice_number (invoice_number),
    UNIQUE KEY uq_platform_invoice_period (subscription_id, period_start, period_end),
    KEY idx_platform_invoice_tenant_status (tenant_id, status),
    KEY idx_platform_invoice_due (due_date, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS platform_payments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    invoice_id BIGINT UNSIGNED NULL,
    gateway_id BIGINT UNSIGNED NULL,
    payment_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    amount DECIMAL(14,2) NOT NULL,
    phone_number VARCHAR(30) NULL,
    payment_method VARCHAR(40) NOT NULL DEFAULT 'manual',
    reference VARCHAR(150) NULL,
    provider VARCHAR(40) NULL,
    external_transaction_id VARCHAR(150) NULL,
    idempotency_key VARCHAR(180) NULL,
    merchant_request_id VARCHAR(150) NULL,
    checkout_request_id VARCHAR(150) NULL,
    result_code VARCHAR(30) NULL,
    result_description VARCHAR(255) NULL,
    request_payload JSON NULL,
    callback_payload JSON NULL,
    failure_reason VARCHAR(500) NULL,
    expires_at DATETIME NULL,
    confirmed_at DATETIME NULL,
    status ENUM('pending','completed','failed','reversed') NOT NULL DEFAULT 'completed',
    metadata JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_platform_payment_provider_external (provider, external_transaction_id),
    UNIQUE KEY uq_platform_payment_idempotency (idempotency_key),
    UNIQUE KEY uq_platform_payment_checkout (checkout_request_id),
    KEY idx_platform_payment_tenant_date (tenant_id, payment_date),
    KEY idx_platform_payment_invoice (invoice_id),
    KEY idx_platform_payment_gateway (gateway_id),
    KEY idx_platform_payment_status_expiry (status, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS platform_payment_gateways (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider VARCHAR(40) NOT NULL DEFAULT 'mpesa',
    name VARCHAR(150) NOT NULL DEFAULT 'Flexihub M-Pesa',
    environment ENUM('sandbox','production') NOT NULL DEFAULT 'sandbox',
    shortcode_type ENUM('paybill','till') NOT NULL DEFAULT 'paybill',
    shortcode VARCHAR(40) NOT NULL,
    consumer_key_encrypted TEXT NULL,
    consumer_secret_encrypted TEXT NULL,
    passkey_encrypted TEXT NULL,
    callback_token VARCHAR(128) NOT NULL,
    callback_url VARCHAR(500) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'inactive',
    is_default TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_platform_gateway_provider (provider),
    KEY idx_platform_gateway_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Compatibility with migration 012: add hotspot_packages.router_id only if missing.
SET @has_hotspot_router_id := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @snapshot_schema
      AND TABLE_NAME = 'hotspot_packages'
      AND COLUMN_NAME = 'router_id'
);
SET @ddl := IF(
    @has_hotspot_router_id = 0,
    'ALTER TABLE hotspot_packages ADD COLUMN router_id BIGINT UNSIGNED NULL AFTER tenant_id',
    'SELECT 1'
);
PREPARE snapshot_stmt FROM @ddl;
EXECUTE snapshot_stmt;
DEALLOCATE PREPARE snapshot_stmt;

SET @has_hotspot_router_index := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @snapshot_schema
      AND TABLE_NAME = 'hotspot_packages'
      AND INDEX_NAME = 'idx_hotspot_packages_router'
);
SET @ddl := IF(
    @has_hotspot_router_index = 0,
    'CREATE INDEX idx_hotspot_packages_router ON hotspot_packages (tenant_id, router_id)',
    'SELECT 1'
);
PREPARE snapshot_stmt FROM @ddl;
EXECUTE snapshot_stmt;
DEALLOCATE PREPARE snapshot_stmt;

-- No settings, starter plans, portal tokens, subscriptions, payments, or receipt claims
-- are seeded here. Those require separate reviewed decisions after the test restore.
