-- Flexihub Billing System
-- Phase 4: Tenant Isolation
-- Run 003_tenant_isolation_audit.sql first.
-- Existing legacy records are assigned to tenant TEN-000001 (id 1).

USE billing_system;

SET @tenant_id := (SELECT id FROM tenants WHERE tenant_code = 'TEN-000001' LIMIT 1);
SET @tenant_id := COALESCE(@tenant_id, 1);

SET @tenant_exists := (SELECT COUNT(*) FROM tenants WHERE id = @tenant_id);
SELECT CASE WHEN @tenant_exists = 1 THEN 'Target tenant exists. Migration may proceed.' ELSE 'ERROR: target tenant TEN-000001 does not exist.' END AS migration_status;

DROP PROCEDURE IF EXISTS flexihub_add_tenant_column;
DELIMITER $$

CREATE PROCEDURE flexihub_add_tenant_column(IN p_table VARCHAR(64))
BEGIN
    DECLARE v_count INT DEFAULT 0;
    SELECT COUNT(*) INTO v_count
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND COLUMN_NAME = 'tenant_id';

    IF v_count = 0 THEN
        SET @sql = CONCAT('ALTER TABLE ', p_table, ' ADD COLUMN tenant_id BIGINT UNSIGNED NULL AFTER id');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;

CALL flexihub_add_tenant_column('customers');
CALL flexihub_add_tenant_column('internet_plans');
CALL flexihub_add_tenant_column('mikrotik_routers');
CALL flexihub_add_tenant_column('pppoe_servers');
CALL flexihub_add_tenant_column('ip_pools');
CALL flexihub_add_tenant_column('internet_accounts');
CALL flexihub_add_tenant_column('pppoe_accounts');
CALL flexihub_add_tenant_column('active_sessions');
CALL flexihub_add_tenant_column('products');
CALL flexihub_add_tenant_column('orders');
CALL flexihub_add_tenant_column('order_items');
CALL flexihub_add_tenant_column('invoices');
CALL flexihub_add_tenant_column('invoice_items');
CALL flexihub_add_tenant_column('payments');
CALL flexihub_add_tenant_column('receipts');
CALL flexihub_add_tenant_column('account_transactions');
CALL flexihub_add_tenant_column('service_status_logs');
CALL flexihub_add_tenant_column('expenses');
CALL flexihub_add_tenant_column('communications');

UPDATE customers SET tenant_id = @tenant_id WHERE tenant_id IS NULL;
UPDATE internet_plans SET tenant_id = @tenant_id WHERE tenant_id IS NULL;
UPDATE mikrotik_routers SET tenant_id = @tenant_id WHERE tenant_id IS NULL;
UPDATE pppoe_servers SET tenant_id = @tenant_id WHERE tenant_id IS NULL;
UPDATE ip_pools SET tenant_id = @tenant_id WHERE tenant_id IS NULL;
UPDATE internet_accounts SET tenant_id = @tenant_id WHERE tenant_id IS NULL;
UPDATE pppoe_accounts SET tenant_id = @tenant_id WHERE tenant_id IS NULL;
UPDATE active_sessions SET tenant_id = @tenant_id WHERE tenant_id IS NULL;
UPDATE products SET tenant_id = @tenant_id WHERE tenant_id IS NULL;
UPDATE orders SET tenant_id = @tenant_id WHERE tenant_id IS NULL;
UPDATE order_items SET tenant_id = @tenant_id WHERE tenant_id IS NULL;
UPDATE invoices SET tenant_id = @tenant_id WHERE tenant_id IS NULL;
UPDATE invoice_items SET tenant_id = @tenant_id WHERE tenant_id IS NULL;
UPDATE payments SET tenant_id = @tenant_id WHERE tenant_id IS NULL;
UPDATE receipts SET tenant_id = @tenant_id WHERE tenant_id IS NULL;
UPDATE account_transactions SET tenant_id = @tenant_id WHERE tenant_id IS NULL;
UPDATE service_status_logs SET tenant_id = @tenant_id WHERE tenant_id IS NULL;
UPDATE expenses SET tenant_id = @tenant_id WHERE tenant_id IS NULL;
UPDATE communications SET tenant_id = @tenant_id WHERE tenant_id IS NULL;

DROP PROCEDURE IF EXISTS flexihub_add_tenant_index;
DELIMITER $$

CREATE PROCEDURE flexihub_add_tenant_index(IN p_table VARCHAR(64))
BEGIN
    DECLARE v_count INT DEFAULT 0;
    SELECT COUNT(*) INTO v_count
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND COLUMN_NAME = 'tenant_id' AND INDEX_NAME <> 'PRIMARY';

    IF v_count = 0 THEN
        SET @sql = CONCAT('ALTER TABLE ', p_table, ' ADD INDEX idx_', p_table, '_tenant_id (tenant_id)');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;

CALL flexihub_add_tenant_index('customers');
CALL flexihub_add_tenant_index('internet_plans');
CALL flexihub_add_tenant_index('mikrotik_routers');
CALL flexihub_add_tenant_index('pppoe_servers');
CALL flexihub_add_tenant_index('ip_pools');
CALL flexihub_add_tenant_index('internet_accounts');
CALL flexihub_add_tenant_index('pppoe_accounts');
CALL flexihub_add_tenant_index('active_sessions');
CALL flexihub_add_tenant_index('products');
CALL flexihub_add_tenant_index('orders');
CALL flexihub_add_tenant_index('order_items');
CALL flexihub_add_tenant_index('invoices');
CALL flexihub_add_tenant_index('invoice_items');
CALL flexihub_add_tenant_index('payments');
CALL flexihub_add_tenant_index('receipts');
CALL flexihub_add_tenant_index('account_transactions');
CALL flexihub_add_tenant_index('service_status_logs');
CALL flexihub_add_tenant_index('expenses');
CALL flexihub_add_tenant_index('communications');

ALTER TABLE customers MODIFY tenant_id BIGINT UNSIGNED NOT NULL;
ALTER TABLE internet_plans MODIFY tenant_id BIGINT UNSIGNED NOT NULL;
ALTER TABLE mikrotik_routers MODIFY tenant_id BIGINT UNSIGNED NOT NULL;
ALTER TABLE pppoe_servers MODIFY tenant_id BIGINT UNSIGNED NOT NULL;
ALTER TABLE ip_pools MODIFY tenant_id BIGINT UNSIGNED NOT NULL;
ALTER TABLE internet_accounts MODIFY tenant_id BIGINT UNSIGNED NOT NULL;
ALTER TABLE pppoe_accounts MODIFY tenant_id BIGINT UNSIGNED NOT NULL;
ALTER TABLE active_sessions MODIFY tenant_id BIGINT UNSIGNED NOT NULL;
ALTER TABLE products MODIFY tenant_id BIGINT UNSIGNED NOT NULL;
ALTER TABLE orders MODIFY tenant_id BIGINT UNSIGNED NOT NULL;
ALTER TABLE order_items MODIFY tenant_id BIGINT UNSIGNED NOT NULL;
ALTER TABLE invoices MODIFY tenant_id BIGINT UNSIGNED NOT NULL;
ALTER TABLE invoice_items MODIFY tenant_id BIGINT UNSIGNED NOT NULL;
ALTER TABLE payments MODIFY tenant_id BIGINT UNSIGNED NOT NULL;
ALTER TABLE receipts MODIFY tenant_id BIGINT UNSIGNED NOT NULL;
ALTER TABLE account_transactions MODIFY tenant_id BIGINT UNSIGNED NOT NULL;
ALTER TABLE service_status_logs MODIFY tenant_id BIGINT UNSIGNED NOT NULL;
ALTER TABLE expenses MODIFY tenant_id BIGINT UNSIGNED NOT NULL;
ALTER TABLE communications MODIFY tenant_id BIGINT UNSIGNED NOT NULL;

DROP PROCEDURE IF EXISTS flexihub_add_tenant_fk;
DELIMITER $$

CREATE PROCEDURE flexihub_add_tenant_fk(IN p_table VARCHAR(64))
BEGIN
    DECLARE v_count INT DEFAULT 0;
    SELECT COUNT(*) INTO v_count
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = p_table
      AND COLUMN_NAME = 'tenant_id'
      AND REFERENCED_TABLE_NAME = 'tenants';

    IF v_count = 0 THEN
        SET @sql = CONCAT('ALTER TABLE ', p_table, ' ADD CONSTRAINT fk_', p_table, '_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON UPDATE CASCADE ON DELETE RESTRICT');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;

CALL flexihub_add_tenant_fk('customers');
CALL flexihub_add_tenant_fk('internet_plans');
CALL flexihub_add_tenant_fk('mikrotik_routers');
CALL flexihub_add_tenant_fk('pppoe_servers');
CALL flexihub_add_tenant_fk('ip_pools');
CALL flexihub_add_tenant_fk('internet_accounts');
CALL flexihub_add_tenant_fk('pppoe_accounts');
CALL flexihub_add_tenant_fk('active_sessions');
CALL flexihub_add_tenant_fk('products');
CALL flexihub_add_tenant_fk('orders');
CALL flexihub_add_tenant_fk('order_items');
CALL flexihub_add_tenant_fk('invoices');
CALL flexihub_add_tenant_fk('invoice_items');
CALL flexihub_add_tenant_fk('payments');
CALL flexihub_add_tenant_fk('receipts');
CALL flexihub_add_tenant_fk('account_transactions');
CALL flexihub_add_tenant_fk('service_status_logs');
CALL flexihub_add_tenant_fk('expenses');
CALL flexihub_add_tenant_fk('communications');

DROP PROCEDURE IF EXISTS flexihub_add_tenant_column;
DROP PROCEDURE IF EXISTS flexihub_add_tenant_index;
DROP PROCEDURE IF EXISTS flexihub_add_tenant_fk;

SELECT TABLE_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'tenant_id'
ORDER BY TABLE_NAME;
