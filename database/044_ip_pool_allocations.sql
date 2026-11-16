-- Flexihub: tenant-scoped IPv4 allocation ledger.
-- Additive migration. Apply only after the normal migration preflight.
CREATE TABLE IF NOT EXISTS ip_pool_allocations (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 tenant_id BIGINT UNSIGNED NOT NULL,
 pool_id BIGINT UNSIGNED NOT NULL,
 ip_address VARCHAR(15) NOT NULL,
 internet_account_id BIGINT UNSIGNED NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'allocated',
 allocated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 released_at DATETIME NULL,
 notes VARCHAR(500) NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (id),
 UNIQUE KEY uq_ip_pool_allocations_tenant_ip (tenant_id, ip_address),
 KEY idx_ip_pool_allocations_pool_status (tenant_id, pool_id, status),
 KEY idx_ip_pool_allocations_account (tenant_id, internet_account_id),
 CONSTRAINT fk_ip_pool_allocations_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON UPDATE CASCADE ON DELETE RESTRICT,
 CONSTRAINT fk_ip_pool_allocations_pool FOREIGN KEY (pool_id) REFERENCES ip_pools(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
