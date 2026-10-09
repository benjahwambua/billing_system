-- Flexihub legacy database bootstrap
-- Run once after the existing billing_system schema.
-- Brings the original users table into the multi-tenant model.

CREATE TABLE IF NOT EXISTS tenants (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO tenants (id, tenant_code, name, status)
SELECT 1, 'TEN-000001', 'Default Organisation', 'active'
WHERE NOT EXISTS (SELECT 1 FROM tenants WHERE id = 1);

SET @has_tenant_id := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'tenant_id');
SET @sql := IF(@has_tenant_id = 0, 'ALTER TABLE users ADD COLUMN tenant_id BIGINT UNSIGNED NULL AFTER id', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_user_scope := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'user_scope');
SET @sql := IF(@has_user_scope = 0, "ALTER TABLE users ADD COLUMN user_scope ENUM('host','tenant') NOT NULL DEFAULT 'tenant' AFTER tenant_id", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE users SET tenant_id = 1 WHERE tenant_id IS NULL;
UPDATE users SET user_scope = 'tenant' WHERE user_scope IS NULL OR user_scope = '';

SET @has_tenant_index := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'idx_users_tenant');
SET @sql := IF(@has_tenant_index = 0, 'ALTER TABLE users ADD INDEX idx_users_tenant (tenant_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_tenant_fk := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND CONSTRAINT_NAME = 'fk_users_tenant' AND CONSTRAINT_TYPE = 'FOREIGN KEY');
SET @sql := IF(@has_tenant_fk = 0, 'ALTER TABLE users ADD CONSTRAINT fk_users_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE RESTRICT ON UPDATE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
