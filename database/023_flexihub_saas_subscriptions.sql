-- Flexihub Billing System
-- Platform SaaS subscription model
-- Keeps Flexihub hosting subscriptions separate from tenant/customer ISP billing.

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
    KEY idx_tps_status_period (status,current_period_end),
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
    UNIQUE KEY uq_platform_invoice_period (subscription_id,period_start,period_end),
    KEY idx_platform_invoice_tenant_status (tenant_id,status),
    KEY idx_platform_invoice_due (due_date,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS platform_payments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    invoice_id BIGINT UNSIGNED NULL,
    payment_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    amount DECIMAL(14,2) NOT NULL,
    payment_method VARCHAR(40) NOT NULL DEFAULT 'manual',
    reference VARCHAR(150) NULL,
    provider VARCHAR(40) NULL,
    external_transaction_id VARCHAR(150) NULL,
    status ENUM('pending','completed','failed','reversed') NOT NULL DEFAULT 'completed',
    metadata JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_platform_payment_provider_external (provider,external_transaction_id),
    KEY idx_platform_payment_tenant_date (tenant_id,payment_date),
    KEY idx_platform_payment_invoice (invoice_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO platform_plans (code,name,description,price,billing_cycle,trial_days,grace_days,status)
SELECT 'STARTER','Flexihub Starter','Starter ISP SaaS hosting plan',0,'monthly',14,3,'active'
WHERE NOT EXISTS (SELECT 1 FROM platform_plans WHERE code='STARTER');

INSERT INTO platform_settings (setting_key,setting_value)
VALUES ('saas_billing_enabled','0')
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);
