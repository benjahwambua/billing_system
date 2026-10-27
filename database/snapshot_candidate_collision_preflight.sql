-- READ-ONLY collision and key-type preflight for 037_snapshot_compatible_core_runtime.sql.
-- Run only against a disposable restore BEFORE the candidate DDL.
-- This script does not change schema or data. Existing target tables are not
-- automatically safe: CREATE TABLE IF NOT EXISTS will leave their definitions intact.

SET @target_schema := DATABASE();

SELECT DATABASE() AS selected_schema, VERSION() AS server_version,
       'Verify this is a disposable restored copy before proceeding' AS safety_check;

-- Every table that the candidate intends to create. Any PRESENT result requires
-- SHOW CREATE TABLE review; do not assume it matches the candidate.
SELECT expected.table_name,
       CASE WHEN t.TABLE_NAME IS NULL THEN 'MISSING - candidate may create'
            ELSE 'PRESENT - inspect definition before candidate' END AS table_status,
       t.ENGINE, t.TABLE_COLLATION
FROM (
    SELECT 'payment_gateways' AS table_name
    UNION ALL SELECT 'payment_gateway_transactions'
    UNION ALL SELECT 'payment_gateway_events'
    UNION ALL SELECT 'hotspot_portal_settings'
    UNION ALL SELECT 'hotspot_packages'
    UNION ALL SELECT 'hotspot_access_codes'
    UNION ALL SELECT 'hotspot_sales'
    UNION ALL SELECT 'hotspot_finalization_queue'
    UNION ALL SELECT 'service_subscriptions'
    UNION ALL SELECT 'platform_settings'
    UNION ALL SELECT 'platform_plans'
    UNION ALL SELECT 'tenant_platform_subscriptions'
    UNION ALL SELECT 'platform_invoices'
    UNION ALL SELECT 'platform_payments'
    UNION ALL SELECT 'platform_payment_gateways'
    UNION ALL SELECT 'sms_messages'
    UNION ALL SELECT 'sms_gateways'
    UNION ALL SELECT 'network_sites'
    UNION ALL SELECT 'communication_templates'
    UNION ALL SELECT 'tenant_wallets'
    UNION ALL SELECT 'wallet_transactions'
    UNION ALL SELECT 'notification_templates'
    UNION ALL SELECT 'notification_queue'
    UNION ALL SELECT 'olt_devices'
    UNION ALL SELECT 'onu_devices'
    UNION ALL SELECT 'network_map_nodes'
    UNION ALL SELECT 'network_map_links'
    UNION ALL SELECT 'hotspot_vouchers'
    UNION ALL SELECT 'agent_sales'
    UNION ALL SELECT 'adverts'
    UNION ALL SELECT 'customer_chats'
    UNION ALL SELECT 'customer_chat_messages'
    UNION ALL SELECT 'failed_mpesa_transactions'
    UNION ALL SELECT 'tenant_theme_settings'
    UNION ALL SELECT 'support_tickets'
    UNION ALL SELECT 'support_ticket_messages'
    UNION ALL SELECT 'router_service_authorizations'
    UNION ALL SELECT 'router_sync_logs'
    UNION ALL SELECT 'hotspot_sessions'
    UNION ALL SELECT 'service_activation_queue'
    UNION ALL SELECT 'service_events'
) expected
LEFT JOIN information_schema.TABLES t
  ON t.TABLE_SCHEMA = @target_schema
 AND t.TABLE_NAME = expected.table_name
ORDER BY expected.table_name;

-- Verify tenant key type and signedness before creating any tenant foreign key.
SELECT c.TABLE_NAME, c.COLUMN_NAME, c.COLUMN_TYPE, c.IS_NULLABLE,
       CASE
         WHEN c.COLUMN_NAME IS NULL THEN 'MISSING'
         WHEN LOWER(c.COLUMN_TYPE) = 'bigint unsigned' THEN 'MATCHES EXPECTED TENANT KEY'
         ELSE 'REVIEW - does not match BIGINT UNSIGNED'
       END AS key_status
FROM (SELECT 'tenants' AS table_name, 'id' AS column_name) expected
LEFT JOIN information_schema.COLUMNS c
  ON c.TABLE_SCHEMA = @target_schema
 AND c.TABLE_NAME = expected.table_name
 AND c.COLUMN_NAME = expected.column_name;

-- Show actual definitions of key columns on candidate target tables that already exist.
-- This makes partial or incompatible prior migration state visible before DDL.
SELECT c.TABLE_NAME, c.COLUMN_NAME, c.COLUMN_TYPE, c.IS_NULLABLE,
       c.COLUMN_DEFAULT, c.EXTRA, c.COLUMN_KEY
FROM information_schema.COLUMNS c
WHERE c.TABLE_SCHEMA = @target_schema
  AND (
       (c.TABLE_NAME IN ('payment_gateways','payment_gateway_transactions',
                         'payment_gateway_events','hotspot_portal_settings',
                         'hotspot_packages','hotspot_access_codes','hotspot_sales',
                         'hotspot_finalization_queue','service_subscriptions',
                         'platform_settings','platform_plans','tenant_platform_subscriptions',
                         'platform_invoices','platform_payments','platform_payment_gateways')
        AND c.COLUMN_NAME IN ('id','tenant_id','router_id','gateway_id',
                              'gateway_transaction_id','sale_id','account_id',
                              'customer_id','plan_id','subscription_id','invoice_id',
                              'payment_id','setting_key','setting_value'))
       OR (c.TABLE_NAME = 'tenants' AND c.COLUMN_NAME = 'id')
  )
ORDER BY c.TABLE_NAME, c.ORDINAL_POSITION;

-- Show existing foreign keys touching candidate target tables. Review for
-- name collisions and unexpected dependencies before adding any further constraints.
SELECT k.TABLE_NAME, k.CONSTRAINT_NAME, k.COLUMN_NAME,
       k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME,
       rc.UPDATE_RULE, rc.DELETE_RULE
FROM information_schema.KEY_COLUMN_USAGE k
LEFT JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
  ON rc.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
 AND rc.CONSTRAINT_NAME = k.CONSTRAINT_NAME
 AND rc.TABLE_NAME = k.TABLE_NAME
WHERE k.CONSTRAINT_SCHEMA = @target_schema
  AND k.REFERENCED_TABLE_NAME IS NOT NULL
  AND k.TABLE_NAME IN ('payment_gateways','payment_gateway_transactions',
                       'payment_gateway_events','hotspot_portal_settings',
                       'hotspot_packages','hotspot_access_codes','hotspot_sales',
                       'hotspot_finalization_queue','service_subscriptions',
                       'platform_settings','platform_plans','tenant_platform_subscriptions',
                       'platform_invoices','platform_payments','platform_payment_gateways')
ORDER BY k.TABLE_NAME, k.CONSTRAINT_NAME, k.ORDINAL_POSITION;
