-- Flexihub Billing System
-- Migration 010: Repair tenant key type mismatch and complete tenant foreign keys.
-- BACK UP billing_system before running this migration.
-- Intended for the legacy schema where tenants.id/users.tenant_id are INT UNSIGNED
-- while business-table tenant_id columns are BIGINT UNSIGNED.
-- Run once, after 009_legacy_tenant_bootstrap.sql and after checking for orphan rows.

USE billing_system;

-- The existing users FK must be removed before changing the referenced key type.
ALTER TABLE users DROP FOREIGN KEY fk_users_tenant;

ALTER TABLE tenants
    MODIFY COLUMN id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE users
    MODIFY COLUMN tenant_id BIGINT UNSIGNED NULL;

ALTER TABLE users
    ADD CONSTRAINT fk_users_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants(id)
    ON DELETE RESTRICT ON UPDATE CASCADE;

-- The orphan audit must return zero rows for every table before this section runs.
ALTER TABLE account_transactions
    ADD CONSTRAINT fk_account_transactions_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants(id)
    ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE active_sessions
    ADD CONSTRAINT fk_active_sessions_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants(id)
    ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE communications
    ADD CONSTRAINT fk_communications_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants(id)
    ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE customers
    ADD CONSTRAINT fk_customers_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants(id)
    ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE expenses
    ADD CONSTRAINT fk_expenses_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants(id)
    ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE hotspot_sessions
    ADD CONSTRAINT fk_hotspot_sessions_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants(id)
    ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE internet_accounts
    ADD CONSTRAINT fk_internet_accounts_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants(id)
    ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE internet_plans
    ADD CONSTRAINT fk_internet_plans_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants(id)
    ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE invoices
    ADD CONSTRAINT fk_invoices_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants(id)
    ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE invoice_items
    ADD CONSTRAINT fk_invoice_items_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants(id)
    ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE ip_pools
    ADD CONSTRAINT fk_ip_pools_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants(id)
    ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE mikrotik_routers
    ADD CONSTRAINT fk_mikrotik_routers_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants(id)
    ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE orders
    ADD CONSTRAINT fk_orders_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants(id)
    ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE order_items
    ADD CONSTRAINT fk_order_items_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants(id)
    ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE payments
    ADD CONSTRAINT fk_payments_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants(id)
    ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE payment_callbacks
    ADD CONSTRAINT fk_payment_callbacks_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants(id)
    ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE pppoe_accounts
    ADD CONSTRAINT fk_pppoe_accounts_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants(id)
    ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE pppoe_servers
    ADD CONSTRAINT fk_pppoe_servers_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants(id)
    ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE products
    ADD CONSTRAINT fk_products_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants(id)
    ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE receipts
    ADD CONSTRAINT fk_receipts_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants(id)
    ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE service_activation_queue
    ADD CONSTRAINT fk_service_activation_queue_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants(id)
    ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE service_events
    ADD CONSTRAINT fk_service_events_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants(id)
    ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE service_status_logs
    ADD CONSTRAINT fk_service_status_logs_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants(id)
    ON DELETE RESTRICT ON UPDATE CASCADE;

-- Verification: this should show all tenant foreign keys after successful execution.
SELECT
    TABLE_NAME,
    CONSTRAINT_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = 'billing_system'
  AND REFERENCED_TABLE_NAME = 'tenants'
ORDER BY TABLE_NAME, CONSTRAINT_NAME;
