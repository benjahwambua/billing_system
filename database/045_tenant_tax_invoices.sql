-- Flexihub tenant tax configuration and immutable invoice tax snapshots.
-- Additive migration; run only after schema preflight and backup.
CREATE TABLE IF NOT EXISTS tenant_tax_settings (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 tenant_id BIGINT UNSIGNED NOT NULL,
 tax_name VARCHAR(80) NOT NULL DEFAULT 'VAT',
 rate_percent DECIMAL(7,4) NOT NULL DEFAULT 16.0000,
 is_active TINYINT(1) NOT NULL DEFAULT 0,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (id),
 UNIQUE KEY uq_tenant_tax_settings_tenant (tenant_id),
 CONSTRAINT fk_tenant_tax_settings_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoice_tax_lines (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 tenant_id BIGINT UNSIGNED NOT NULL,
 invoice_id BIGINT UNSIGNED NOT NULL,
 tax_name VARCHAR(80) NOT NULL,
 rate_percent DECIMAL(7,4) NOT NULL,
 taxable_amount DECIMAL(14,2) NOT NULL,
 tax_amount DECIMAL(14,2) NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (id),
 UNIQUE KEY uq_invoice_tax_lines_invoice (tenant_id, invoice_id),
 KEY idx_invoice_tax_lines_tenant (tenant_id, created_at),
 KEY idx_invoice_tax_lines_invoice (invoice_id),
 CONSTRAINT fk_invoice_tax_lines_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
