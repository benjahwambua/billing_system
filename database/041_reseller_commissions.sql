-- Flexihub Billing System
-- Feature 041: tenant-scoped reseller directory and invoice-linked commissions.
-- Review invoice payment accounting and take a backup before applying.

CREATE TABLE IF NOT EXISTS resellers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    reseller_code VARCHAR(50) NOT NULL,
    business_name VARCHAR(180) NOT NULL,
    contact_person VARCHAR(150) NULL,
    phone VARCHAR(40) NULL,
    email VARCHAR(190) NULL,
    commission_rate DECIMAL(6,3) NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_reseller_tenant_code (tenant_id, reseller_code),
    KEY idx_resellers_tenant_status (tenant_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS reseller_commissions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    reseller_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    invoice_id BIGINT UNSIGNED NOT NULL,
    sale_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    commission_rate DECIMAL(6,3) NOT NULL DEFAULT 0,
    commission_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    reference VARCHAR(120) NULL,
    notes TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    approved_by BIGINT UNSIGNED NULL,
    paid_by BIGINT UNSIGNED NULL,
    approved_at DATETIME NULL,
    paid_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_reseller_invoice_commission (tenant_id, reseller_id, invoice_id),
    KEY idx_reseller_commissions_tenant_status (tenant_id, status),
    KEY idx_reseller_commissions_reseller (tenant_id, reseller_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
