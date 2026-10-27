-- READ-ONLY snapshot upgrade preflight for Flexihub Billing System.
-- Run only after selecting a restored/disposable copy of the latest billing_system database.
-- This script reads information_schema only. It never changes schema or financial data.
-- A MISSING result is evidence for review, not authorization to create a table.
-- Target compatibility: source export reports MariaDB 10.4.32; verify actual server version separately.

SET @target_schema := DATABASE();

-- Result set 1: module/migration-owned table presence.
SELECT expected.table_name,
       CASE WHEN t.TABLE_NAME IS NULL THEN 'MISSING' ELSE 'PRESENT' END AS table_status,
       t.ENGINE AS storage_engine
FROM (
    SELECT 'tenants' AS table_name
    UNION ALL SELECT 'customers'
    UNION ALL SELECT 'invoices'
    UNION ALL SELECT 'internet_accounts'
    UNION ALL SELECT 'payments'
    UNION ALL SELECT 'receipts'
    UNION ALL SELECT 'account_transactions'
    UNION ALL SELECT 'payment_callbacks'
    UNION ALL SELECT 'payment_gateways'
    UNION ALL SELECT 'payment_gateway_transactions'
    UNION ALL SELECT 'payment_gateway_events'
    UNION ALL SELECT 'hotspot_portal_settings'
    UNION ALL SELECT 'hotspot_sales'
    UNION ALL SELECT 'hotspot_sessions'
    UNION ALL SELECT 'service_subscriptions'
    UNION ALL SELECT 'service_activation_queue'
    UNION ALL SELECT 'service_events'
    UNION ALL SELECT 'platform_settings'
    UNION ALL SELECT 'platform_payment_gateways'
    UNION ALL SELECT 'platform_plans'
    UNION ALL SELECT 'tenant_platform_subscriptions'
    UNION ALL SELECT 'platform_invoices'
    UNION ALL SELECT 'platform_payments'
    UNION ALL SELECT 'mpesa_receipt_claims'
    UNION ALL SELECT 'router_service_authorizations'
    UNION ALL SELECT 'router_sync_logs'
    UNION ALL SELECT 'hotspot_finalization_queue'
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
    UNION ALL SELECT 'sms_gateways'
    UNION ALL SELECT 'failed_mpesa_transactions'
    UNION ALL SELECT 'tenant_theme_settings'
    UNION ALL SELECT 'sms_messages'
    UNION ALL SELECT 'support_tickets'
    UNION ALL SELECT 'support_ticket_messages'
) AS expected
LEFT JOIN information_schema.TABLES t
  ON t.TABLE_SCHEMA = @target_schema
 AND t.TABLE_NAME = expected.table_name
ORDER BY table_status DESC, expected.table_name;

-- Result set 2: tenant-key types on existing tenant-scoped tables.
-- Compare these with tenants.id. FK-compatible integer types and signedness matter.
SELECT c.TABLE_NAME AS table_name,
       c.COLUMN_TYPE AS tenant_id_type,
       c.IS_NULLABLE AS nullable,
       CASE
         WHEN parent.COLUMN_TYPE IS NULL THEN 'TENANTS.ID MISSING'
         WHEN c.COLUMN_TYPE = parent.COLUMN_TYPE THEN 'TYPE MATCH'
         ELSE 'TYPE MISMATCH — REVIEW BEFORE FK/INSERT'
       END AS compatibility
FROM information_schema.COLUMNS c
LEFT JOIN information_schema.COLUMNS parent
  ON parent.TABLE_SCHEMA = c.TABLE_SCHEMA
 AND parent.TABLE_NAME = 'tenants'
 AND parent.COLUMN_NAME = 'id'
WHERE c.TABLE_SCHEMA = @target_schema
  AND c.COLUMN_NAME = 'tenant_id'
  AND c.TABLE_NAME IN (
    'customers','invoices','internet_accounts','payments','receipts',
    'account_transactions','payment_callbacks','payment_gateways',
    'payment_gateway_transactions','payment_gateway_events',
    'hotspot_portal_settings','hotspot_sales','hotspot_sessions',
    'service_subscriptions','service_activation_queue','service_events',
    'platform_settings','platform_invoices','platform_payments',
    'router_service_authorizations','router_sync_logs',
    'hotspot_finalization_queue','notification_templates','notification_queue',
    'olt_devices','onu_devices','network_map_nodes','network_map_links',
    'hotspot_vouchers','agent_sales','adverts','customer_chats',
    'customer_chat_messages','sms_gateways','failed_mpesa_transactions',
    'tenant_theme_settings','sms_messages','support_tickets',
    'support_ticket_messages'
  )
ORDER BY compatibility, c.TABLE_NAME;

-- Result set 3: critical existing payment/ledger columns.
-- Missing columns must be addressed through a reviewed compatibility design;
-- do not add financial foreign keys or backfill values based on guesses.
SELECT expected.table_name,
       expected.column_name,
       CASE
         WHEN t.TABLE_NAME IS NULL THEN 'TABLE MISSING'
         WHEN c.COLUMN_NAME IS NULL THEN 'COLUMN MISSING'
         ELSE 'PRESENT'
       END AS object_status,
       c.COLUMN_TYPE AS actual_type,
       c.IS_NULLABLE AS nullable
FROM (
    SELECT 'payments' AS table_name, 'customer_id' AS column_name
    UNION ALL SELECT 'payments', 'internet_account_id'
    UNION ALL SELECT 'payments', 'invoice_id'
    UNION ALL SELECT 'payments', 'reference_number'
    UNION ALL SELECT 'payments', 'payment_method'
    UNION ALL SELECT 'invoices', 'subtotal'
    UNION ALL SELECT 'invoices', 'total'
    UNION ALL SELECT 'invoices', 'amount_paid'
    UNION ALL SELECT 'invoices', 'balance'
    UNION ALL SELECT 'receipts', 'payment_id'
    UNION ALL SELECT 'receipts', 'reference_number'
    UNION ALL SELECT 'receipts', 'invoice_id'
    UNION ALL SELECT 'account_transactions', 'internet_account_id'
    UNION ALL SELECT 'account_transactions', 'payment_id'
    UNION ALL SELECT 'account_transactions', 'reference_type'
    UNION ALL SELECT 'account_transactions', 'reference_id'
    UNION ALL SELECT 'payment_gateway_transactions', 'provider_receipt'
    UNION ALL SELECT 'payment_gateway_transactions', 'platform_invoice_id'
    UNION ALL SELECT 'platform_payments', 'external_transaction_id'
    UNION ALL SELECT 'platform_payments', 'checkout_request_id'
) AS expected
LEFT JOIN information_schema.TABLES t
  ON t.TABLE_SCHEMA = @target_schema
 AND t.TABLE_NAME = expected.table_name
LEFT JOIN information_schema.COLUMNS c
  ON c.TABLE_SCHEMA = @target_schema
 AND c.TABLE_NAME = expected.table_name
 AND c.COLUMN_NAME = expected.column_name
ORDER BY expected.table_name, expected.column_name;

-- Result set 4: actual server/database identity for the test record.
SELECT DATABASE() AS selected_schema,
       VERSION() AS database_server_version,
       @@sql_mode AS sql_mode;
