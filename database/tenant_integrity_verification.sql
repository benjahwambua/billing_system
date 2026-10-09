-- Flexihub Billing System
-- Read-only tenant integrity verification.
-- Run against billing_system after tenant migrations/repairs.
-- This script does not change schema or data.

USE billing_system;

-- 1. List tenant foreign keys and their referential rules.
SELECT
    k.TABLE_NAME,
    k.CONSTRAINT_NAME,
    k.COLUMN_NAME,
    k.REFERENCED_TABLE_NAME,
    k.REFERENCED_COLUMN_NAME,
    r.DELETE_RULE,
    r.UPDATE_RULE
FROM information_schema.KEY_COLUMN_USAGE AS k
JOIN information_schema.REFERENTIAL_CONSTRAINTS AS r
  ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
 AND r.TABLE_NAME = k.TABLE_NAME
 AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
WHERE k.CONSTRAINT_SCHEMA = DATABASE()
  AND k.REFERENCED_TABLE_NAME = 'tenants'
  AND k.REFERENCED_COLUMN_NAME = 'id'
ORDER BY k.TABLE_NAME;

-- 2. Count tenant foreign keys. The currently repaired schema should return 24.
SELECT COUNT(DISTINCT k.TABLE_NAME) AS tables_with_tenant_fk
FROM information_schema.KEY_COLUMN_USAGE AS k
WHERE k.CONSTRAINT_SCHEMA = DATABASE()
  AND k.REFERENCED_TABLE_NAME = 'tenants'
  AND k.REFERENCED_COLUMN_NAME = 'id';

-- 3. Orphan audit. Expected result: zero rows.
-- Any returned row identifies a table containing tenant_id values with no parent tenant.
SELECT 'account_transactions' AS table_name, COUNT(*) AS orphan_rows
FROM billing_system.account_transactions c LEFT JOIN billing_system.tenants t ON t.id = c.tenant_id
WHERE c.tenant_id IS NOT NULL AND t.id IS NULL
UNION ALL
SELECT 'active_sessions', COUNT(*)
FROM billing_system.active_sessions c LEFT JOIN billing_system.tenants t ON t.id = c.tenant_id
WHERE c.tenant_id IS NOT NULL AND t.id IS NULL
UNION ALL
SELECT 'communications', COUNT(*)
FROM billing_system.communications c LEFT JOIN billing_system.tenants t ON t.id = c.tenant_id
WHERE c.tenant_id IS NOT NULL AND t.id IS NULL
UNION ALL
SELECT 'customers', COUNT(*)
FROM billing_system.customers c LEFT JOIN billing_system.tenants t ON t.id = c.tenant_id
WHERE c.tenant_id IS NOT NULL AND t.id IS NULL
UNION ALL
SELECT 'expenses', COUNT(*)
FROM billing_system.expenses c LEFT JOIN billing_system.tenants t ON t.id = c.tenant_id
WHERE c.tenant_id IS NOT NULL AND t.id IS NULL
UNION ALL
SELECT 'hotspot_sessions', COUNT(*)
FROM billing_system.hotspot_sessions c LEFT JOIN billing_system.tenants t ON t.id = c.tenant_id
WHERE c.tenant_id IS NOT NULL AND t.id IS NULL
UNION ALL
SELECT 'internet_accounts', COUNT(*)
FROM billing_system.internet_accounts c LEFT JOIN billing_system.tenants t ON t.id = c.tenant_id
WHERE c.tenant_id IS NOT NULL AND t.id IS NULL
UNION ALL
SELECT 'internet_plans', COUNT(*)
FROM billing_system.internet_plans c LEFT JOIN billing_system.tenants t ON t.id = c.tenant_id
WHERE c.tenant_id IS NOT NULL AND t.id IS NULL
UNION ALL
SELECT 'invoices', COUNT(*)
FROM billing_system.invoices c LEFT JOIN billing_system.tenants t ON t.id = c.tenant_id
WHERE c.tenant_id IS NOT NULL AND t.id IS NULL
UNION ALL
SELECT 'invoice_items', COUNT(*)
FROM billing_system.invoice_items c LEFT JOIN billing_system.tenants t ON t.id = c.tenant_id
WHERE c.tenant_id IS NOT NULL AND t.id IS NULL
UNION ALL
SELECT 'ip_pools', COUNT(*)
FROM billing_system.ip_pools c LEFT JOIN billing_system.tenants t ON t.id = c.tenant_id
WHERE c.tenant_id IS NOT NULL AND t.id IS NULL
UNION ALL
SELECT 'mikrotik_routers', COUNT(*)
FROM billing_system.mikrotik_routers c LEFT JOIN billing_system.tenants t ON t.id = c.tenant_id
WHERE c.tenant_id IS NOT NULL AND t.id IS NULL
UNION ALL
SELECT 'orders', COUNT(*)
FROM billing_system.orders c LEFT JOIN billing_system.tenants t ON t.id = c.tenant_id
WHERE c.tenant_id IS NOT NULL AND t.id IS NULL
UNION ALL
SELECT 'order_items', COUNT(*)
FROM billing_system.order_items c LEFT JOIN billing_system.tenants t ON t.id = c.tenant_id
WHERE c.tenant_id IS NOT NULL AND t.id IS NULL
UNION ALL
SELECT 'payments', COUNT(*)
FROM billing_system.payments c LEFT JOIN billing_system.tenants t ON t.id = c.tenant_id
WHERE c.tenant_id IS NOT NULL AND t.id IS NULL
UNION ALL
SELECT 'payment_callbacks', COUNT(*)
FROM billing_system.payment_callbacks c LEFT JOIN billing_system.tenants t ON t.id = c.tenant_id
WHERE c.tenant_id IS NOT NULL AND t.id IS NULL
UNION ALL
SELECT 'pppoe_accounts', COUNT(*)
FROM billing_system.pppoe_accounts c LEFT JOIN billing_system.tenants t ON t.id = c.tenant_id
WHERE c.tenant_id IS NOT NULL AND t.id IS NULL
UNION ALL
SELECT 'pppoe_servers', COUNT(*)
FROM billing_system.pppoe_servers c LEFT JOIN billing_system.tenants t ON t.id = c.tenant_id
WHERE c.tenant_id IS NOT NULL AND t.id IS NULL
UNION ALL
SELECT 'products', COUNT(*)
FROM billing_system.products c LEFT JOIN billing_system.tenants t ON t.id = c.tenant_id
WHERE c.tenant_id IS NOT NULL AND t.id IS NULL
UNION ALL
SELECT 'receipts', COUNT(*)
FROM billing_system.receipts c LEFT JOIN billing_system.tenants t ON t.id = c.tenant_id
WHERE c.tenant_id IS NOT NULL AND t.id IS NULL
UNION ALL
SELECT 'service_activation_queue', COUNT(*)
FROM billing_system.service_activation_queue c LEFT JOIN billing_system.tenants t ON t.id = c.tenant_id
WHERE c.tenant_id IS NOT NULL AND t.id IS NULL
UNION ALL
SELECT 'service_events', COUNT(*)
FROM billing_system.service_events c LEFT JOIN billing_system.tenants t ON t.id = c.tenant_id
WHERE c.tenant_id IS NOT NULL AND t.id IS NULL
UNION ALL
SELECT 'service_status_logs', COUNT(*)
FROM billing_system.service_status_logs c LEFT JOIN billing_system.tenants t ON t.id = c.tenant_id
WHERE c.tenant_id IS NOT NULL AND t.id IS NULL
UNION ALL
SELECT 'users', COUNT(*)
FROM billing_system.users c LEFT JOIN billing_system.tenants t ON t.id = c.tenant_id
WHERE c.tenant_id IS NOT NULL AND t.id IS NULL
ORDER BY table_name;
