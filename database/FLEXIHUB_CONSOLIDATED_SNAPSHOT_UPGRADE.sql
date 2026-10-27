-- Flexihub Billing System: consolidated snapshot upgrade candidate
-- Target snapshot: user-confirmed 2026-10-27 export; MariaDB 10.4.x
-- STATUS: REVIEW / DISPOSABLE-RESTORE ONLY. NOT APPROVED FOR WORKING DATABASE.
--
-- This single file consolidates the core runtime schema candidate for the latest export.
-- Covered: tenant M-Pesa gateway/event storage; hotspot portal settings, packages,
-- access codes, sales and finalization queue; service subscription lineage; platform
-- plans, subscriptions, invoices, payments and gateway settings.
--
-- It does not yet repair arbitrary existing partial tables or cover every optional module.
-- Excluded pending exact contract review: SMS delivery/retry, OLT/ONU, network monitoring,
-- support tickets/chat, vouchers/agent sales/advertising, and M-Pesa receipt claims/backfill.
-- No customer, invoice, payment, receipt or ledger rows are inserted, updated or deleted.
-- No credentials, starter plans, portal tokens or subscriptions are seeded.
--
-- SAFETY: back up the working database and restore the latest export into a NEW disposable
-- schema. Run read-only preflights first. Inspect any existing target table with SHOW CREATE
-- TABLE before proceeding. Stop on any SQL error and inspect partial DDL; do not rerun blindly.
-- Validate post-apply schema, financial reconciliation and second-run behavior on the restore
-- before considering the file for the working database. The SQL has not been executed here.

SELECT DATABASE() AS selected_schema,
       VERSION() AS database_server_version,
       'DISPOSABLE RESTORE ONLY - stop if this is the working database' AS safety_check;

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


-- Optional SMS delivery persistence.
-- The supplied snapshot lacks sms_messages. SMS gateway-specific ALTERs are guarded
-- below so this file does not fail merely because the optional gateway table is absent.
CREATE TABLE IF NOT EXISTS sms_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    gateway_id BIGINT UNSIGNED NULL,
    recipient VARCHAR(40) NOT NULL,
    message TEXT NOT NULL,
    sender_id VARCHAR(80) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'queued',
    provider_message_id VARCHAR(150) NULL,
    response_body TEXT NULL,
    failure_reason VARCHAR(500) NULL,
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    sent_at DATETIME NULL,
    next_attempt_at DATETIME NULL,
    last_attempt_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_sms_messages_tenant_status (tenant_id, status),
    KEY idx_sms_messages_tenant_created (tenant_id, created_at),
    KEY idx_sms_messages_provider (tenant_id, provider_message_id),
    KEY idx_sms_retry_queue (status, next_attempt_at, attempts, tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Conditional additions for known optional runtime columns. These changes are
-- deliberately skipped if the owning table is absent; missing parent tables need
-- their own verified migration rather than guessed table definitions.
SET @has_sms_gateways := (
    SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = @snapshot_schema AND TABLE_NAME = 'sms_gateways'
);
SET @ddl := IF(@has_sms_gateways = 1,
    IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@snapshot_schema AND TABLE_NAME='sms_gateways' AND COLUMN_NAME='api_method')=0,
       'ALTER TABLE sms_gateways ADD COLUMN api_method VARCHAR(10) NOT NULL DEFAULT ''POST''',
       'SELECT 1'), 'SELECT 1');
PREPARE snapshot_stmt FROM @ddl; EXECUTE snapshot_stmt; DEALLOCATE PREPARE snapshot_stmt;
SET @ddl := IF(@has_sms_gateways = 1,
    IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@snapshot_schema AND TABLE_NAME='sms_gateways' AND COLUMN_NAME='auth_type')=0,
       'ALTER TABLE sms_gateways ADD COLUMN auth_type VARCHAR(30) NOT NULL DEFAULT ''bearer''',
       'SELECT 1'), 'SELECT 1');
PREPARE snapshot_stmt FROM @ddl; EXECUTE snapshot_stmt; DEALLOCATE PREPARE snapshot_stmt;
SET @ddl := IF(@has_sms_gateways = 1,
    IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@snapshot_schema AND TABLE_NAME='sms_gateways' AND COLUMN_NAME='request_template')=0,
       'ALTER TABLE sms_gateways ADD COLUMN request_template TEXT NULL',
       'SELECT 1'), 'SELECT 1');
PREPARE snapshot_stmt FROM @ddl; EXECUTE snapshot_stmt; DEALLOCATE PREPARE snapshot_stmt;
SET @ddl := IF(@has_sms_gateways = 1,
    IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@snapshot_schema AND TABLE_NAME='sms_gateways' AND COLUMN_NAME='balance_endpoint')=0,
       'ALTER TABLE sms_gateways ADD COLUMN balance_endpoint VARCHAR(500) NULL',
       'SELECT 1'), 'SELECT 1');
PREPARE snapshot_stmt FROM @ddl; EXECUTE snapshot_stmt; DEALLOCATE PREPARE snapshot_stmt;
SET @ddl := IF(@has_sms_gateways = 1,
    IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@snapshot_schema AND TABLE_NAME='sms_gateways' AND COLUMN_NAME='last_error')=0,
       'ALTER TABLE sms_gateways ADD COLUMN last_error VARCHAR(500) NULL',
       'SELECT 1'), 'SELECT 1');
PREPARE snapshot_stmt FROM @ddl; EXECUTE snapshot_stmt; DEALLOCATE PREPARE snapshot_stmt;

-- Optional monitoring fields: only add columns when the owning tables already exist.
SET @monitor_tables := 'mikrotik_routers,olt_devices,onu_devices';
SET @ddl := IF((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=@snapshot_schema AND TABLE_NAME='mikrotik_routers')=1,
    IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@snapshot_schema AND TABLE_NAME='mikrotik_routers' AND COLUMN_NAME='last_poll_at')=0,
       'ALTER TABLE mikrotik_routers ADD COLUMN last_poll_at DATETIME NULL', 'SELECT 1'), 'SELECT 1');
PREPARE snapshot_stmt FROM @ddl; EXECUTE snapshot_stmt; DEALLOCATE PREPARE snapshot_stmt;
SET @ddl := IF((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=@snapshot_schema AND TABLE_NAME='mikrotik_routers')=1,
    IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@snapshot_schema AND TABLE_NAME='mikrotik_routers' AND COLUMN_NAME='last_seen_at')=0,
       'ALTER TABLE mikrotik_routers ADD COLUMN last_seen_at DATETIME NULL', 'SELECT 1'), 'SELECT 1');
PREPARE snapshot_stmt FROM @ddl; EXECUTE snapshot_stmt; DEALLOCATE PREPARE snapshot_stmt;
SET @ddl := IF((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=@snapshot_schema AND TABLE_NAME='mikrotik_routers')=1,
    IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@snapshot_schema AND TABLE_NAME='mikrotik_routers' AND COLUMN_NAME='last_poll_error')=0,
       'ALTER TABLE mikrotik_routers ADD COLUMN last_poll_error VARCHAR(500) NULL', 'SELECT 1'), 'SELECT 1');
PREPARE snapshot_stmt FROM @ddl; EXECUTE snapshot_stmt; DEALLOCATE PREPARE snapshot_stmt;
SET @ddl := IF((SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=@snapshot_schema AND TABLE_NAME='mikrotik_routers')=1,
    IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@snapshot_schema AND TABLE_NAME='mikrotik_routers' AND COLUMN_NAME='latency_ms')=0,
       'ALTER TABLE mikrotik_routers ADD COLUMN latency_ms DECIMAL(10,1) NULL', 'SELECT 1'), 'SELECT 1');
PREPARE snapshot_stmt FROM @ddl; EXECUTE snapshot_stmt; DEALLOCATE PREPARE snapshot_stmt;




-- Additional module foundations consolidated from 007, 022, 027, 028, 029 and 035.
-- All are CREATE IF NOT EXISTS; existing partial definitions are not repaired by this.
CREATE TABLE IF NOT EXISTS network_sites (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL,
 name VARCHAR(150) NOT NULL, location VARCHAR(255) NULL, status VARCHAR(30) NOT NULL DEFAULT 'active',
 description TEXT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_network_sites_tenant(tenant_id),
 CONSTRAINT fk_network_sites_tenant FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS communication_templates (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL,
 name VARCHAR(150) NOT NULL, channel VARCHAR(30) NOT NULL DEFAULT 'sms', subject VARCHAR(255) NULL,
 message TEXT NOT NULL, status VARCHAR(30) NOT NULL DEFAULT 'active', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 KEY idx_comm_templates_tenant(tenant_id),
 CONSTRAINT fk_comm_templates_tenant FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tenant_wallets (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL,
 balance DECIMAL(14,2) NOT NULL DEFAULT 0, currency VARCHAR(10) NOT NULL DEFAULT 'KES',
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_tenant_wallet(tenant_id),
 CONSTRAINT fk_tenant_wallet_tenant FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS wallet_transactions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL,
 transaction_type VARCHAR(30) NOT NULL, amount DECIMAL(14,2) NOT NULL, reference VARCHAR(100) NULL,
 description VARCHAR(255) NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 KEY idx_wallet_tx_tenant(tenant_id),
 CONSTRAINT fk_wallet_tx_tenant FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notification_templates (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 tenant_id BIGINT UNSIGNED NOT NULL,
 event_type VARCHAR(60) NOT NULL,
 channel VARCHAR(20) NOT NULL DEFAULT 'sms',
 subject VARCHAR(255) NULL,
 body TEXT NOT NULL,
 status ENUM('active','inactive') NOT NULL DEFAULT 'active',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (id),
 UNIQUE KEY uq_notification_template (tenant_id,event_type,channel),
 KEY idx_notification_templates_tenant (tenant_id,status),
 CONSTRAINT fk_notification_templates_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notification_queue (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 tenant_id BIGINT UNSIGNED NOT NULL,
 customer_id BIGINT UNSIGNED NULL,
 account_id BIGINT UNSIGNED NULL,
 invoice_id BIGINT UNSIGNED NULL,
 event_type VARCHAR(60) NOT NULL,
 channel VARCHAR(20) NOT NULL DEFAULT 'sms',
 recipient VARCHAR(255) NOT NULL,
 subject VARCHAR(255) NULL,
 message TEXT NOT NULL,
 status ENUM('pending','processing','sent','failed','cancelled') NOT NULL DEFAULT 'pending',
 attempts INT UNSIGNED NOT NULL DEFAULT 0,
 available_at DATETIME NULL,
 sent_at DATETIME NULL,
 last_error TEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (id),
 KEY idx_notification_queue_worker (tenant_id,status,available_at),
 KEY idx_notification_queue_customer (tenant_id,customer_id),
 KEY idx_notification_queue_invoice (tenant_id,invoice_id),
 CONSTRAINT fk_notification_queue_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS olt_devices (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL,
 name VARCHAR(150) NOT NULL, vendor VARCHAR(80) NULL, model VARCHAR(120) NULL,
 host VARCHAR(255) NULL, api_port INT UNSIGNED NOT NULL DEFAULT 161, username VARCHAR(120) NULL,
 status VARCHAR(30) NOT NULL DEFAULT 'active', site_id BIGINT UNSIGNED NULL,
 description TEXT NULL, last_seen_at DATETIME NULL, protocol VARCHAR(20) NOT NULL DEFAULT 'snmp',
 snmp_version VARCHAR(10) NULL, polling_enabled TINYINT(1) NOT NULL DEFAULT 0,
 last_poll_at DATETIME NULL, last_poll_error VARCHAR(500) NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_olt_tenant_status(tenant_id,status), KEY idx_olt_site(tenant_id,site_id),
 KEY idx_olt_polling(tenant_id,polling_enabled,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS onu_devices (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL,
 olt_id BIGINT UNSIGNED NULL, serial_number VARCHAR(120) NULL, name VARCHAR(150) NULL,
 pon_port VARCHAR(50) NULL, customer_id BIGINT UNSIGNED NULL, service_account_id BIGINT UNSIGNED NULL,
 ip_address VARCHAR(64) NULL, status VARCHAR(30) NOT NULL DEFAULT 'unknown',
 signal_rx VARCHAR(40) NULL, signal_tx VARCHAR(40) NULL, last_seen_at DATETIME NULL,
 notes TEXT NULL, mac_address VARCHAR(64) NULL, vendor VARCHAR(80) NULL, model VARCHAR(120) NULL,
 optical_rx_dbm DECIMAL(8,2) NULL, optical_tx_dbm DECIMAL(8,2) NULL,
 last_poll_at DATETIME NULL, last_poll_error VARCHAR(500) NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_onu_serial_tenant(tenant_id,serial_number), KEY idx_onu_tenant_status(tenant_id,status),
 KEY idx_onu_customer(tenant_id,customer_id), KEY idx_onu_olt(tenant_id,olt_id), KEY idx_onu_mac(tenant_id,mac_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS network_map_nodes (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL,
 node_type VARCHAR(40) NOT NULL, node_id BIGINT UNSIGNED NULL, label VARCHAR(150) NOT NULL,
 latitude DECIMAL(10,7) NULL, longitude DECIMAL(10,7) NULL, metadata JSON NULL,
 status VARCHAR(30) NOT NULL DEFAULT 'active', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_map_nodes_tenant(tenant_id), KEY idx_map_nodes_type(tenant_id,node_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS network_map_links (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL,
 source_node_id BIGINT UNSIGNED NOT NULL, target_node_id BIGINT UNSIGNED NOT NULL,
 link_type VARCHAR(40) NOT NULL DEFAULT 'ethernet', status VARCHAR(30) NOT NULL DEFAULT 'active',
 bandwidth_mbps DECIMAL(12,2) NULL, metadata JSON NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 KEY idx_map_links_tenant(tenant_id), KEY idx_map_links_source(tenant_id,source_node_id),
 KEY idx_map_links_target(tenant_id,target_node_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS hotspot_vouchers (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL,
 code VARCHAR(100) NOT NULL, package_id BIGINT UNSIGNED NULL, batch_reference VARCHAR(100) NULL,
 price DECIMAL(14,2) NOT NULL DEFAULT 0, status VARCHAR(30) NOT NULL DEFAULT 'unused',
 sold_at DATETIME NULL, activated_at DATETIME NULL, expires_at DATETIME NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_voucher_code_tenant(tenant_id,code), KEY idx_voucher_tenant_status(tenant_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS agent_sales (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL,
 agent_name VARCHAR(150) NOT NULL, agent_phone VARCHAR(40) NULL, product_type VARCHAR(50) NOT NULL,
 reference VARCHAR(120) NULL, amount DECIMAL(14,2) NOT NULL DEFAULT 0,
 commission DECIMAL(14,2) NOT NULL DEFAULT 0, status VARCHAR(30) NOT NULL DEFAULT 'completed',
 sold_at DATETIME DEFAULT CURRENT_TIMESTAMP, notes TEXT NULL,
 KEY idx_agent_sales_tenant_date(tenant_id,sold_at), KEY idx_agent_sales_agent(tenant_id,agent_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS adverts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL,
 title VARCHAR(200) NOT NULL, message TEXT NOT NULL, image_url VARCHAR(500) NULL,
 target VARCHAR(50) NOT NULL DEFAULT 'all', status VARCHAR(30) NOT NULL DEFAULT 'draft',
 starts_at DATETIME NULL, ends_at DATETIME NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_adverts_tenant_status(tenant_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS customer_chats (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL,
 customer_id BIGINT UNSIGNED NULL, channel VARCHAR(30) NOT NULL DEFAULT 'web',
 subject VARCHAR(200) NULL, status VARCHAR(30) NOT NULL DEFAULT 'open',
 assigned_user_id BIGINT UNSIGNED NULL, last_message_at DATETIME NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_customer_chat_tenant_status(tenant_id,status), KEY idx_customer_chat_customer(tenant_id,customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS customer_chat_messages (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL,
 chat_id BIGINT UNSIGNED NOT NULL, sender_type VARCHAR(30) NOT NULL, sender_id BIGINT UNSIGNED NULL,
 message TEXT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 KEY idx_chat_messages(tenant_id,chat_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS failed_mpesa_transactions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL,
 gateway_transaction_id BIGINT UNSIGNED NULL, phone_number VARCHAR(40) NULL,
 amount DECIMAL(14,2) NOT NULL DEFAULT 0, account_reference VARCHAR(120) NULL,
 failure_code VARCHAR(50) NULL, failure_reason VARCHAR(500) NULL,
 retry_status VARCHAR(30) NOT NULL DEFAULT 'pending', retry_count INT UNSIGNED NOT NULL DEFAULT 0,
 last_retry_at DATETIME NULL, resolved_at DATETIME NULL, resolution_note VARCHAR(500) NULL,
 resolved_by BIGINT UNSIGNED NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 KEY idx_failed_mpesa_tenant_status(tenant_id,retry_status), KEY idx_failed_mpesa_tx(tenant_id,gateway_transaction_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tenant_theme_settings (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL,
 theme_name VARCHAR(80) NOT NULL DEFAULT 'Flexihub Blue', primary_color VARCHAR(20) NULL,
 accent_color VARCHAR(20) NULL, logo_url VARCHAR(500) NULL, dark_mode TINYINT(1) NOT NULL DEFAULT 1,
 custom_css TEXT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_theme_tenant(tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS support_tickets (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 tenant_id BIGINT UNSIGNED NOT NULL, created_by BIGINT UNSIGNED NOT NULL,
 subject VARCHAR(200) NOT NULL, category VARCHAR(30) NOT NULL DEFAULT 'general',
 priority VARCHAR(20) NOT NULL DEFAULT 'normal', status VARCHAR(30) NOT NULL DEFAULT 'open',
 assigned_to BIGINT UNSIGNED NULL, resolution TEXT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_support_tickets_tenant_status (tenant_id, status),
 KEY idx_support_tickets_tenant_updated (tenant_id, updated_at),
 KEY idx_support_tickets_assignee_status (assigned_to, status),
 KEY idx_support_tickets_created_by (tenant_id, created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS support_ticket_messages (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 ticket_id BIGINT UNSIGNED NOT NULL, tenant_id BIGINT UNSIGNED NOT NULL,
 sender_user_id BIGINT UNSIGNED NOT NULL, sender_type VARCHAR(20) NOT NULL, message TEXT NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_support_messages_ticket (tenant_id, ticket_id, id),
 KEY idx_support_messages_sender (tenant_id, sender_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Optional columns on tables that may already exist; never alter a missing parent table.
SET @has_mikrotik_routers := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=@snapshot_schema AND TABLE_NAME='mikrotik_routers');
SET @ddl := IF(@has_mikrotik_routers=1, IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@snapshot_schema AND TABLE_NAME='mikrotik_routers' AND COLUMN_NAME='hotspot_login_url')=0, 'ALTER TABLE mikrotik_routers ADD COLUMN hotspot_login_url VARCHAR(500) NULL', 'SELECT 1'), 'SELECT 1');
PREPARE snapshot_stmt FROM @ddl; EXECUTE snapshot_stmt; DEALLOCATE PREPARE snapshot_stmt;

SET @has_invoices := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=@snapshot_schema AND TABLE_NAME='invoices');
SET @ddl := IF(@has_invoices=1, IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@snapshot_schema AND TABLE_NAME='invoices' AND INDEX_NAME='idx_invoices_customer_due')=0, 'CREATE INDEX idx_invoices_customer_due ON invoices(tenant_id,customer_id,due_date,status)', 'SELECT 1'), 'SELECT 1');
PREPARE snapshot_stmt FROM @ddl; EXECUTE snapshot_stmt; DEALLOCATE PREPARE snapshot_stmt;


CREATE TABLE IF NOT EXISTS sms_gateways (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL,
 name VARCHAR(150) NOT NULL, provider VARCHAR(80) NOT NULL, sender_id VARCHAR(80) NULL,
 endpoint_url VARCHAR(500) NULL, credentials_encrypted TEXT NULL,
 status VARCHAR(30) NOT NULL DEFAULT 'inactive', balance DECIMAL(14,2) NULL,
 last_verified_at DATETIME NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_sms_gateway_tenant_status(tenant_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;




-- Router authorization and sync audit tables from 011_pppoe_router_runtime.sql.
CREATE TABLE IF NOT EXISTS router_service_authorizations (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 tenant_id BIGINT UNSIGNED NOT NULL,
 pppoe_account_id BIGINT UNSIGNED NULL,
 hotspot_session_id BIGINT UNSIGNED NULL,
 router_id BIGINT UNSIGNED NOT NULL,
 service_type VARCHAR(30) NOT NULL,
 external_username VARCHAR(150) NULL,
 desired_status VARCHAR(30) NOT NULL DEFAULT 'active',
 applied_status VARCHAR(30) NULL,
 last_synced_at DATETIME NULL,
 last_error TEXT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_rsa_tenant(tenant_id), KEY idx_rsa_account(tenant_id,pppoe_account_id),
 KEY idx_rsa_router(tenant_id,router_id), KEY idx_rsa_status(tenant_id,desired_status),
 UNIQUE KEY uq_rsa_pppoe(tenant_id,pppoe_account_id),
 CONSTRAINT fk_rsa_tenant FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS router_sync_logs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 tenant_id BIGINT UNSIGNED NOT NULL,
 router_id BIGINT UNSIGNED NOT NULL,
 service_type VARCHAR(30) NOT NULL,
 external_username VARCHAR(150) NULL,
 action VARCHAR(30) NOT NULL,
 status VARCHAR(30) NOT NULL,
 message TEXT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 KEY idx_rsl_tenant(tenant_id,created_at), KEY idx_rsl_router(tenant_id,router_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Service runtime tables from 010_service_activation_hotspot_runtime.sql.
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




-- Final inventory of the one intentionally deferred data-integrity operation.
-- Receipt claims must not be backfilled until duplicate receipts have been reconciled.
SELECT 'mpesa_receipt_claims' AS table_name,
       CASE WHEN TABLE_NAME IS NULL THEN 'DEFERRED - run duplicate receipt preflight first'
            ELSE 'EXISTS - verify rows and uniqueness before relying on it' END AS status
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = @snapshot_schema
  AND TABLE_NAME = 'mpesa_receipt_claims'
UNION ALL
SELECT 'mpesa_receipt_claims',
       'DEFERRED - run duplicate receipt preflight first'
WHERE NOT EXISTS (
    SELECT 1 FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = @snapshot_schema AND TABLE_NAME = 'mpesa_receipt_claims'
);
