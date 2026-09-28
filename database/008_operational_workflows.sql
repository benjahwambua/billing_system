-- Flexihub operational workflow extension
-- Run after 004_tenant_isolation.sql and 007_module_foundation.sql.

CREATE TABLE IF NOT EXISTS service_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    account_id INT NOT NULL,
    customer_id INT NOT NULL,
    plan_id INT NOT NULL,
    invoice_id INT NULL,
    payment_id INT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ss_tenant (tenant_id),
    INDEX idx_ss_account (tenant_id, account_id),
    INDEX idx_ss_customer (tenant_id, customer_id),
    INDEX idx_ss_status (tenant_id, status),
    INDEX idx_ss_invoice (tenant_id, invoice_id)
);

CREATE TABLE IF NOT EXISTS hotspot_sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    access_code_id INT NOT NULL,
    package_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    payment_method VARCHAR(30) NOT NULL DEFAULT 'cash',
    reference VARCHAR(100) NULL,
    sold_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(30) NOT NULL DEFAULT 'completed',
    INDEX idx_hs_tenant (tenant_id),
    INDEX idx_hs_code (tenant_id, access_code_id),
    INDEX idx_hs_package (tenant_id, package_id),
    INDEX idx_hs_sold_at (tenant_id, sold_at)
);
