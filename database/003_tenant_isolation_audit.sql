-- Flexihub Billing System
-- Phase 3: Tenant Isolation Audit
-- IMPORTANT:
-- This script is READ-ONLY. It does not alter production data.
-- Run it against the billing_system database before creating 003 tenant migration.

USE billing_system;

-- 1. Confirm required platform tables exist.
SELECT
    TABLE_NAME,
    TABLE_TYPE,
    ENGINE
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
      'tenants',
      'tenant_settings',
      'users',
      'roles',
      'permissions',
      'user_roles',
      'customers',
      'internet_plans',
      'internet_accounts',
      'pppoe_accounts',
      'mikrotik_routers',
      'pppoe_servers',
      'ip_pools',
      'active_sessions',
      'invoices',
      'invoice_items',
      'payments',
      'receipts',
      'orders',
      'order_items',
      'account_transactions',
      'service_status_logs',
      'expenses',
      'communications',
      'settings',
      'audit_logs'
  )
ORDER BY TABLE_NAME;

-- 2. Show the complete column definition of every legacy ISP table.
SELECT
    TABLE_NAME,
    ORDINAL_POSITION,
    COLUMN_NAME,
    COLUMN_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_KEY,
    EXTRA
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
      'customers',
      'internet_plans',
      'internet_accounts',
      'pppoe_accounts',
      'mikrotik_routers',
      'pppoe_servers',
      'ip_pools',
      'active_sessions',
      'invoices',
      'invoice_items',
      'payments',
      'receipts',
      'orders',
      'order_items',
      'account_transactions',
      'service_status_logs',
      'expenses',
      'communications',
      'settings',
      'audit_logs'
  )
ORDER BY TABLE_NAME, ORDINAL_POSITION;

-- 3. Identify which tables already have tenant_id.
SELECT
    TABLE_NAME,
    COLUMN_NAME,
    COLUMN_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_KEY
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND COLUMN_NAME = 'tenant_id'
ORDER BY TABLE_NAME;

-- 4. Show all foreign keys involving tenants or tenant_id.
SELECT
    kcu.TABLE_NAME,
    kcu.COLUMN_NAME,
    kcu.CONSTRAINT_NAME,
    kcu.REFERENCED_TABLE_NAME,
    kcu.REFERENCED_COLUMN_NAME,
    rc.UPDATE_RULE,
    rc.DELETE_RULE
FROM information_schema.KEY_COLUMN_USAGE kcu
LEFT JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
    ON rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA
   AND rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
   AND rc.TABLE_NAME = kcu.TABLE_NAME
WHERE kcu.TABLE_SCHEMA = DATABASE()
  AND (
      kcu.COLUMN_NAME = 'tenant_id'
      OR kcu.REFERENCED_TABLE_NAME = 'tenants'
  )
ORDER BY kcu.TABLE_NAME, kcu.COLUMN_NAME;

-- 5. Show row counts for the tables that will require tenant migration.
SELECT
    TABLE_NAME,
    TABLE_ROWS
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
      'customers',
      'internet_plans',
      'internet_accounts',
      'pppoe_accounts',
      'mikrotik_routers',
      'pppoe_servers',
      'ip_pools',
      'active_sessions',
      'invoices',
      'invoice_items',
      'payments',
      'receipts',
      'orders',
      'order_items',
      'account_transactions',
      'service_status_logs',
      'expenses',
      'communications',
      'settings',
      'audit_logs'
  )
ORDER BY TABLE_NAME;

-- 6. Detect orphaned user tenant references.
SELECT
    u.id,
    u.username,
    u.tenant_id
FROM users u
LEFT JOIN tenants t ON t.id = u.tenant_id
WHERE u.tenant_id IS NOT NULL
  AND t.id IS NULL;

-- 7. Confirm the current host users.
SELECT
    id,
    username,
    user_scope,
    tenant_id,
    status
FROM users
WHERE user_scope = 'host'
ORDER BY id;
