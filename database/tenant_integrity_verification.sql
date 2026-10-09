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
FROM account_transactions c LEFT JOIN tenants t ON t.id = c.tenant_id
WHERE c.tenant_id IS NOT NULL AND t.id IS NULL
UNION ALL
SELECT 'active_sessions', COUNT(*)
FROM active_sessions c LEFT JOIN tenants t ON t.id = c.tenant_id
WHERE c.tenant_id IS NOT NULL AND t.id IS NULL
UNION ALL
SELECT 'communications', COUNT(*)
FROM communications c LEFT JOIN tenants t ON t.id = c.tenant_id
WHERE c.tenant_id IS NOT NULL AND t.id IS NULL
UNION ALL
SELECT 'customers', COUNT(*)
FROM customers c LEFT JOIN tenants t ON t.id = c.tenant_id
WHERE c.tenant_id IS NOT NULL AND t.id IS NULL
UNION ALL
SELECT 'expenses', COUNT(*)
FROM expenses c LEFT JOIN tenants t ON t.id = c.tenant_id
WHERE c.tenant_id IS NOT NULL AND t.id IS NULL
UNION ALL
SELECT 'hotspot_sessions', COUNT(*)
FROM hotspot_sessions c LEFT JOIN tenants t ON t.id = c.tenant_id
WHERE c.tenant_id IS NOT NULL AND t.id IS NULL
UNION ALL
SELECT 'internet_accounts', COUNT(*)
FROM internet_accounts c LEFT JOIN tenants t ON t.id = c.tenant_id
WHERE c.tenant_id IS NOT NULL AND t.id IS NULL
UNION ALL
SELECT 'internet_plans', COUNT(*)
FROM internet_plans c LEFT JOIN tenants t ON t.id = c.tenant_id
WHERE c.tenant_id IS NOT NULL AND t.id IS NULL
UNION ALL
SELECT 'invoices', COUNT(*)
FROM invoices c LEFT JOIN tenants t ON t.id = c.tenant_id
WHERE c.tenant_id IS NOT NULL AND t.id IS NULL
UNION ALL
SELECT 'invoice_items', COUNT(*)
FROM invoice_items c LEFT JOIN tenants t ON t.id = c.tenant_id
WHERE c.tenant_id IS NOT NULL AND t.id IS NULL
UNION ALL
SELECT 'ip_pools', COUNT(*)
FROM ip_pools c LEFT JOIN tenants t ON t.id = c.tenant_id
WHERE c.tenant_id IS NOT NULL AND t.id IS NULL
UNION ALL
SELECT 'mikrotik_routers', COUNT(*)
FROM mikrotik_routers c LEFT JOIN tenants t ON t.id = c.tenant_id
WHERE c.tenant_id IS NOT NULL AND t.id IS NULL
UNION ALL
SELECT 'orders', COUNT(*)
FROM orders c LEFT JOIN tenants t ON t.id = c.tenant_id
WHERE c.tenant_id IS NOT NULL AND t.id IS NULL
UNION ALL
SELECT 'order_items', COUNT(*)
FROM order_items c LEFT JOIN tenants t ON t.id = c.tenant_id
WHERE c.tenant_id IS NOT NULL AND t.id IS NULL
UNION ALL
SELECT 'payments', COUNT(*)
FROM payments c LEFT JOIN tenants t ON t.id = c.tenant_id
WHERE c.tenant_id IS NOT NULL AND t.id IS NULL
UNION ALL
SELECT 'payment_callbacks', COUNT(*)
FROM payment_callbacks c LEFT JOIN tenants t ON t.id = c.tenant_id
WHERE c.tenant_id IS NOT NULL AND t.id IS NULL
UNION ALL
SELECT 'pppoe_accounts', COUNT(*)
FROM pppoe_accounts c LEFT JOIN tenants t ON t.id = c.tenant_id
WHERE c.tenant_id IS NOT NULL AND t.id IS NULL
UNION ALL
SELECT 'pppoe_servers', COUNT(*)
FROM pppoe_servers c LEFT JOIN tenants t ON t.id = c.tenant_id
WHERE c.tenant_id IS NOT NULL AND t.id IS NULL
UNION ALL
SELECT 'products', COUNT(*)
FROM products c LEFT JOIN tenants t ON t.id = c.tenant_id
WHERE c.tenant_id IS NOT NULL AND t.id IS NULL
UNION ALL
SELECT 'receipts', COUNT(*)
FROM receipts c LEFT JOIN tenants t ON t.id = c.tenant_id
WHERE c.tenant_id IS NOT NULL AND t.id IS NULL
UNION ALL
SELECT 'service_activation_queue', COUNT(*)
FROM service_activation_queue c LEFT JOIN tenants t ON t.id = c.tenant_id
WHERE c.tenant_id IS NOT NULL AND t.id IS NULL
UNION ALL
SELECT 'service_events', COUNT(*)
FROM service_events c LEFT JOIN tenants t ON t.id = c.tenant_id
WHERE c.tenant_id IS NOT NULL AND t.id IS NULL
UNION ALL
SELECT 'service_status_logs', COUNT(*)
FROM service_status_logs c LEFT JOIN tenants t ON t.id = c.tenant_id
WHERE c.tenant_id IS NOT NULL AND t.id IS NULL
UNION ALL
SELECT 'users', COUNT(*)
FROM users c LEFT JOIN tenants t ON t.id = c.tenant_id
WHERE c.tenant_id IS NOT NULL AND t.id IS NULL
ORDER BY table_name;
