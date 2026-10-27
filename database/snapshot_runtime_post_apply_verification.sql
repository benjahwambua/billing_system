-- READ-ONLY post-apply verification for FLEXIHUB_CONSOLIDATED_SNAPSHOT_UPGRADE.sql.
-- Run only against the disposable restored schema after candidate execution.
-- This script performs SELECTs only; it does not modify schema or data.

SET @verify_schema := DATABASE();

SELECT DATABASE() AS selected_schema,
       VERSION() AS server_version,
       CASE
         WHEN DATABASE() = 'billing_system_snapshot_test_20261027'
           THEN 'EXPECTED DISPOSABLE SCHEMA'
         ELSE 'STOP - VERIFY SELECTED SCHEMA'
       END AS schema_safety_check;

-- Verify all tables expected from the candidate exist and use InnoDB.
SELECT expected.table_name,
       t.TABLE_NAME IS NOT NULL AS table_exists,
       t.ENGINE,
       CASE
         WHEN t.TABLE_NAME IS NULL THEN 'MISSING'
         WHEN UPPER(COALESCE(t.ENGINE, '')) <> 'INNODB' THEN 'REVIEW ENGINE'
         ELSE 'PRESENT'
       END AS verification_status
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
  ON t.TABLE_SCHEMA = @verify_schema
 AND t.TABLE_NAME = expected.table_name
ORDER BY expected.table_name;

-- Confirm the key columns and signedness relevant to tenant isolation and router linkage.
SELECT expected.table_name,
       expected.column_name,
       c.COLUMN_TYPE,
       c.IS_NULLABLE,
       CASE
         WHEN c.COLUMN_NAME IS NULL THEN 'MISSING'
         WHEN expected.table_name = 'tenants'
          AND expected.column_name = 'id'
          AND LOWER(c.COLUMN_TYPE) = 'bigint unsigned'
           THEN 'MATCH'
         WHEN expected.table_name = 'hotspot_packages'
          AND expected.column_name = 'router_id'
          AND LOWER(c.COLUMN_TYPE) = 'bigint unsigned'
           THEN 'MATCH'
         WHEN expected.table_name = 'hotspot_sales'
          AND expected.column_name IN ('tenant_id', 'id')
          AND LOWER(c.COLUMN_TYPE) = 'bigint unsigned'
           THEN 'MATCH'
         WHEN expected.table_name IN (
              'payment_gateways','payment_gateway_transactions',
              'payment_gateway_events','hotspot_portal_settings',
              'hotspot_packages','hotspot_access_codes','hotspot_sales',
              'hotspot_finalization_queue','service_subscriptions',
              'tenant_platform_subscriptions','platform_invoices',
              'platform_payments','sms_messages','sms_gateways','network_sites',
              'communication_templates','tenant_wallets','wallet_transactions',
              'notification_templates','notification_queue','olt_devices','onu_devices',
              'network_map_nodes','network_map_links','hotspot_vouchers','agent_sales',
              'adverts','customer_chats','customer_chat_messages','failed_mpesa_transactions',
              'tenant_theme_settings','support_tickets','support_ticket_messages',
              'router_service_authorizations','router_sync_logs','hotspot_sessions',
              'service_activation_queue','service_events'
         ) AND expected.column_name = 'tenant_id'
          AND LOWER(c.COLUMN_TYPE) = 'bigint unsigned'
           THEN 'MATCH'
         ELSE 'PRESENT - REVIEW TYPE AGAINST CANDIDATE'
       END AS type_status
FROM (
    SELECT 'tenants' AS table_name, 'id' AS column_name
    UNION ALL SELECT 'hotspot_packages', 'router_id'
    UNION ALL SELECT 'hotspot_sales', 'id'
    UNION ALL SELECT 'hotspot_sales', 'tenant_id'
    UNION ALL SELECT 'payment_gateways', 'tenant_id'
    UNION ALL SELECT 'payment_gateway_transactions', 'tenant_id'
    UNION ALL SELECT 'payment_gateway_events', 'tenant_id'
    UNION ALL SELECT 'hotspot_portal_settings', 'tenant_id'
    UNION ALL SELECT 'hotspot_packages', 'tenant_id'
    UNION ALL SELECT 'hotspot_access_codes', 'tenant_id'
    UNION ALL SELECT 'hotspot_finalization_queue', 'tenant_id'
    UNION ALL SELECT 'service_subscriptions', 'tenant_id'
    UNION ALL SELECT 'tenant_platform_subscriptions', 'tenant_id'
    UNION ALL SELECT 'platform_invoices', 'tenant_id'
    UNION ALL SELECT 'platform_payments', 'tenant_id'
    UNION ALL SELECT 'sms_messages', 'tenant_id'
    UNION ALL SELECT 'sms_gateways', 'tenant_id'
    UNION ALL SELECT 'network_sites', 'tenant_id'
    UNION ALL SELECT 'communication_templates', 'tenant_id'
    UNION ALL SELECT 'tenant_wallets', 'tenant_id'
    UNION ALL SELECT 'wallet_transactions', 'tenant_id'
    UNION ALL SELECT 'notification_templates', 'tenant_id'
    UNION ALL SELECT 'notification_queue', 'tenant_id'
    UNION ALL SELECT 'olt_devices', 'tenant_id'
    UNION ALL SELECT 'onu_devices', 'tenant_id'
    UNION ALL SELECT 'network_map_nodes', 'tenant_id'
    UNION ALL SELECT 'network_map_links', 'tenant_id'
    UNION ALL SELECT 'hotspot_vouchers', 'tenant_id'
    UNION ALL SELECT 'agent_sales', 'tenant_id'
    UNION ALL SELECT 'adverts', 'tenant_id'
    UNION ALL SELECT 'customer_chats', 'tenant_id'
    UNION ALL SELECT 'customer_chat_messages', 'tenant_id'
    UNION ALL SELECT 'failed_mpesa_transactions', 'tenant_id'
    UNION ALL SELECT 'tenant_theme_settings', 'tenant_id'
    UNION ALL SELECT 'support_tickets', 'tenant_id'
    UNION ALL SELECT 'support_ticket_messages', 'tenant_id'
    UNION ALL SELECT 'router_service_authorizations', 'tenant_id'
    UNION ALL SELECT 'router_sync_logs', 'tenant_id'
    UNION ALL SELECT 'hotspot_sessions', 'tenant_id'
    UNION ALL SELECT 'service_activation_queue', 'tenant_id'
    UNION ALL SELECT 'service_events', 'tenant_id'
) expected
LEFT JOIN information_schema.COLUMNS c
  ON c.TABLE_SCHEMA = @verify_schema
 AND c.TABLE_NAME = expected.table_name
 AND c.COLUMN_NAME = expected.column_name
ORDER BY expected.table_name, expected.column_name;

-- Verify candidate-owned foreign keys exist. Absence or unexpected target is a review item.
SELECT expected.table_name,
       expected.constraint_name,
       k.COLUMN_NAME,
       k.REFERENCED_TABLE_NAME,
       k.REFERENCED_COLUMN_NAME,
       rc.UPDATE_RULE,
       rc.DELETE_RULE,
       CASE
         WHEN k.CONSTRAINT_NAME IS NULL THEN 'MISSING - REVIEW'
         ELSE 'PRESENT - REVIEW TARGET'
       END AS verification_status
FROM (
    SELECT 'hotspot_packages' AS table_name, 'fk_hotspot_packages_tenant' AS constraint_name
    UNION ALL SELECT 'hotspot_access_codes', 'fk_hotspot_codes_tenant'
    UNION ALL SELECT 'hotspot_finalization_queue', 'fk_hotspot_finalization_tenant'
    UNION ALL SELECT 'hotspot_finalization_queue', 'fk_hotspot_finalization_sale'
) expected
LEFT JOIN information_schema.KEY_COLUMN_USAGE k
  ON k.CONSTRAINT_SCHEMA = @verify_schema
 AND k.TABLE_NAME = expected.table_name
 AND k.CONSTRAINT_NAME = expected.constraint_name
 AND k.REFERENCED_TABLE_NAME IS NOT NULL
LEFT JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
  ON rc.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
 AND rc.TABLE_NAME = k.TABLE_NAME
 AND rc.CONSTRAINT_NAME = k.CONSTRAINT_NAME
ORDER BY expected.table_name, expected.constraint_name;

-- Report unexpected existing schema objects with the same names is handled by the
-- collision preflight; this post-check does not prove application behavior.
