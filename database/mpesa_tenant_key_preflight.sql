-- Flexihub Billing System
-- Read-only preflight for the legacy INT UNSIGNED tenant_id declarations
-- in migration 013. Run with billing_system selected, or retain the explicit
-- schema filter below. This report does not alter schema or data.
--
-- Review output before designing any type-alignment migration. Do not run an
-- ALTER TABLE based on this report alone.

-- 1. Actual tenant key types and nullability.
SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'billing_system'
  AND (
    (TABLE_NAME = 'tenants' AND COLUMN_NAME = 'id')
    OR (TABLE_NAME IN (
      'payment_gateways',
      'payment_gateway_transactions',
      'payment_gateway_events',
      'hotspot_portal_settings'
    ) AND COLUMN_NAME = 'tenant_id')
  )
ORDER BY TABLE_NAME, COLUMN_NAME;

-- 2. Foreign keys touching these tenant columns, including referenced types'
--    relationship context. Empty output means no declared FK was found here;
--    it does not prove the relationship is safe or unnecessary.
SELECT
  kcu.TABLE_NAME,
  kcu.COLUMN_NAME,
  kcu.CONSTRAINT_NAME,
  kcu.REFERENCED_TABLE_SCHEMA,
  kcu.REFERENCED_TABLE_NAME,
  kcu.REFERENCED_COLUMN_NAME,
  rc.UPDATE_RULE,
  rc.DELETE_RULE
FROM information_schema.KEY_COLUMN_USAGE kcu
LEFT JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
  ON rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA
 AND rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
 AND rc.TABLE_NAME = kcu.TABLE_NAME
WHERE kcu.TABLE_SCHEMA = 'billing_system'
  AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
  AND (
    (kcu.TABLE_NAME = 'tenants' AND kcu.COLUMN_NAME = 'id')
    OR (kcu.TABLE_NAME IN (
      'payment_gateways',
      'payment_gateway_transactions',
      'payment_gateway_events',
      'hotspot_portal_settings'
    ) AND kcu.COLUMN_NAME = 'tenant_id')
    OR (kcu.REFERENCED_TABLE_NAME = 'tenants' AND kcu.REFERENCED_COLUMN_NAME = 'id')
  )
ORDER BY kcu.TABLE_NAME, kcu.CONSTRAINT_NAME;

-- 3. Indexes that include tenant_id. Index definitions and uniqueness matter
--    when planning type changes or foreign keys.
SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME, SUB_PART
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = 'billing_system'
  AND (
    (TABLE_NAME = 'tenants' AND COLUMN_NAME = 'id')
    OR (TABLE_NAME IN (
      'payment_gateways',
      'payment_gateway_transactions',
      'payment_gateway_events',
      'hotspot_portal_settings'
    ) AND COLUMN_NAME = 'tenant_id')
  )
ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;

-- 4. Confirm which affected tables exist. Do not execute a separate table-
--    specific orphan query until this inventory confirms each table exists.
SELECT TABLE_NAME, ENGINE, TABLE_ROWS
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'billing_system'
  AND TABLE_NAME IN (
    'tenants',
    'payment_gateways',
    'payment_gateway_transactions',
    'payment_gateway_events',
    'hotspot_portal_settings'
  )
ORDER BY TABLE_NAME;
