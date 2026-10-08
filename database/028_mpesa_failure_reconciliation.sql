-- Flexihub Billing System
-- Phase 28: M-Pesa failure/reconciliation hardening
USE billing_system;

CREATE TABLE IF NOT EXISTS failed_mpesa_transactions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 tenant_id BIGINT UNSIGNED NOT NULL,
 gateway_transaction_id BIGINT UNSIGNED NULL,
 phone_number VARCHAR(40) NULL,
 amount DECIMAL(14,2) NOT NULL DEFAULT 0,
 account_reference VARCHAR(120) NULL,
 failure_code VARCHAR(50) NULL,
 failure_reason VARCHAR(500) NULL,
 retry_status VARCHAR(30) NOT NULL DEFAULT 'pending',
 retry_count INT UNSIGNED NOT NULL DEFAULT 0,
 last_retry_at DATETIME NULL,
 resolved_at DATETIME NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 KEY idx_failed_mpesa_tenant_status(tenant_id,retry_status),
 KEY idx_failed_mpesa_tx(tenant_id,gateway_transaction_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
