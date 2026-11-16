-- Flexihub Billing System
-- Feature 037: tenant-scoped customer groups.
-- Review against the installed schema before applying to an existing database.

CREATE TABLE IF NOT EXISTS customer_groups (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(500) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_customer_groups_tenant_name (tenant_id, name),
    UNIQUE KEY uq_customer_groups_tenant_id (tenant_id, id),
    KEY idx_customer_groups_tenant_status (tenant_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP PROCEDURE IF EXISTS flexihub_add_customer_group_link;
DELIMITER $$
CREATE PROCEDURE flexihub_add_customer_group_link()
BEGIN
    DECLARE v_column INT DEFAULT 0;
    DECLARE v_index INT DEFAULT 0;
    DECLARE v_fk INT DEFAULT 0;

    SELECT COUNT(*) INTO v_column
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'customers'
      AND COLUMN_NAME = 'customer_group_id';

    IF v_column = 0 THEN
        ALTER TABLE customers ADD COLUMN customer_group_id BIGINT UNSIGNED NULL AFTER tenant_id;
    END IF;

    SELECT COUNT(*) INTO v_index
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'customers'
      AND INDEX_NAME = 'idx_customers_tenant_group';

    IF v_index = 0 THEN
        ALTER TABLE customers ADD INDEX idx_customers_tenant_group (tenant_id, customer_group_id);
    END IF;

    SELECT COUNT(*) INTO v_fk
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'customers'
      AND CONSTRAINT_NAME = 'fk_customers_tenant_group'
      AND REFERENCED_TABLE_NAME = 'customer_groups';

    IF v_fk = 0 THEN
        ALTER TABLE customers
            ADD CONSTRAINT fk_customers_tenant_group
            FOREIGN KEY (tenant_id, customer_group_id)
            REFERENCES customer_groups (tenant_id, id)
            ON UPDATE CASCADE
            ON DELETE RESTRICT;
    END IF;
END$$
DELIMITER ;

CALL flexihub_add_customer_group_link();
DROP PROCEDURE IF EXISTS flexihub_add_customer_group_link();
