-- Flexihub: PPPoE and MikroTik runtime integration foundation
USE billing_system;

SET @db := DATABASE();

-- Link each PPPoE credential to the exact server/router that should authorize it.
SET @has_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='pppoe_accounts' AND COLUMN_NAME='pppoe_server_id');
SET @sql := IF(@has_col=0,'ALTER TABLE pppoe_accounts ADD COLUMN pppoe_server_id BIGINT UNSIGNED NULL AFTER internet_account_id','SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has_idx := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='pppoe_accounts' AND INDEX_NAME='idx_pppoe_accounts_server');
SET @sql := IF(@has_idx=0,'ALTER TABLE pppoe_accounts ADD INDEX idx_pppoe_accounts_server (tenant_id,pppoe_server_id)','SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has_fk := (SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=@db AND TABLE_NAME='pppoe_accounts' AND COLUMN_NAME='pppoe_server_id' AND REFERENCED_TABLE_NAME='pppoe_servers');
SET @sql := IF(@has_fk=0,'ALTER TABLE pppoe_accounts ADD CONSTRAINT fk_pppoe_accounts_server FOREIGN KEY (pppoe_server_id) REFERENCES pppoe_servers(id) ON UPDATE CASCADE ON DELETE SET NULL','SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

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