-- READ-ONLY schema compatibility preflight for payment workflows.
-- Run with billing_system selected, preferably on a backup/disposable copy.
-- This script only reads information_schema. It does not inspect or change financial rows.
-- A MISSING result identifies a schema/code prerequisite, not an instruction to ALTER TABLE.
-- Review the migration history and existing data before designing any schema change.

SET @target_schema := DATABASE();

-- Result set 1: table presence for payment and subscription workflows.
SELECT expected.table_name,
       CASE
           WHEN t.TABLE_NAME IS NULL THEN 'MISSING'
           ELSE 'PRESENT'
       END AS table_status,
       t.ENGINE AS storage_engine
FROM (
    SELECT 'tenants' AS table_name
    UNION ALL SELECT 'payments'
    UNION ALL SELECT 'receipts'
    UNION ALL SELECT 'account_transactions'
    UNION ALL SELECT 'payment_gateways'
    UNION ALL SELECT 'payment_gateway_transactions'
    UNION ALL SELECT 'payment_gateway_events'
    UNION ALL SELECT 'hotspot_portal_settings'
    UNION ALL SELECT 'hotspot_sales'
    UNION ALL SELECT 'service_subscriptions'
    UNION ALL SELECT 'platform_invoices'
    UNION ALL SELECT 'platform_payments'
    UNION ALL SELECT 'mpesa_receipt_claims'
) AS expected
LEFT JOIN information_schema.TABLES t
  ON t.TABLE_SCHEMA = @target_schema
 AND t.TABLE_NAME = expected.table_name
ORDER BY expected.table_name;

-- Result set 2: critical payment-artifact columns plus key M-Pesa receipt-claim prerequisites.
-- REQUIRED means the current shared payment workflow expects the column for safe operation.
-- OPTIONAL means the application adapts to its absence in some paths; review before relying on it.
SELECT expected.table_name,
       expected.column_name,
       expected.importance,
       CASE
           WHEN t.TABLE_NAME IS NULL THEN 'TABLE MISSING'
           WHEN c.COLUMN_NAME IS NULL THEN 'COLUMN MISSING'
           ELSE 'PRESENT'
       END AS column_status,
       c.COLUMN_TYPE AS actual_column_type,
       c.IS_NULLABLE AS nullable
FROM (
    SELECT 'payments' AS table_name, 'id' AS column_name, 'REQUIRED' AS importance
    UNION ALL SELECT 'payments', 'tenant_id', 'REQUIRED'
    UNION ALL SELECT 'payments', 'invoice_id', 'REQUIRED'
    UNION ALL SELECT 'payments', 'amount', 'REQUIRED'
    UNION ALL SELECT 'payments', 'payment_date', 'OPTIONAL'
    UNION ALL SELECT 'payments', 'reference', 'OPTIONAL'
    UNION ALL SELECT 'payments', 'reference_number', 'OPTIONAL'

    UNION ALL SELECT 'receipts', 'id', 'REQUIRED'
    UNION ALL SELECT 'receipts', 'tenant_id', 'REQUIRED'
    UNION ALL SELECT 'receipts', 'payment_id', 'REQUIRED'
    UNION ALL SELECT 'receipts', 'amount', 'REQUIRED'
    UNION ALL SELECT 'receipts', 'invoice_id', 'OPTIONAL'
    UNION ALL SELECT 'receipts', 'reference', 'OPTIONAL'
    UNION ALL SELECT 'receipts', 'reference_number', 'OPTIONAL'

    UNION ALL SELECT 'account_transactions', 'id', 'REQUIRED'
    UNION ALL SELECT 'account_transactions', 'tenant_id', 'REQUIRED'
    UNION ALL SELECT 'account_transactions', 'payment_id', 'REQUIRED'
    UNION ALL SELECT 'account_transactions', 'amount', 'REQUIRED'
    UNION ALL SELECT 'account_transactions', 'invoice_id', 'OPTIONAL'
    UNION ALL SELECT 'account_transactions', 'reference_type', 'OPTIONAL'
    UNION ALL SELECT 'account_transactions', 'reference_id', 'OPTIONAL'

    UNION ALL SELECT 'payment_gateway_transactions', 'tenant_id', 'REQUIRED FOR M-PESA CLAIM MIGRATION'
    UNION ALL SELECT 'payment_gateway_transactions', 'provider', 'REQUIRED FOR M-PESA CLAIM MIGRATION'
    UNION ALL SELECT 'payment_gateway_transactions', 'provider_receipt', 'REQUIRED FOR M-PESA CLAIM MIGRATION'
    UNION ALL SELECT 'payment_gateway_transactions', 'id', 'REQUIRED FOR M-PESA CLAIM MIGRATION'
    UNION ALL SELECT 'payment_gateway_transactions', 'flow', 'REQUIRED FOR MIRROR DETECTION'
    UNION ALL SELECT 'payment_gateway_transactions', 'platform_invoice_id', 'REQUIRED FOR MIRROR DETECTION'
    UNION ALL SELECT 'payment_gateway_transactions', 'account_reference', 'REQUIRED FOR MIRROR DETECTION'

    UNION ALL SELECT 'platform_payments', 'tenant_id', 'REQUIRED FOR M-PESA CLAIM MIGRATION'
    UNION ALL SELECT 'platform_payments', 'provider', 'REQUIRED FOR M-PESA CLAIM MIGRATION'
    UNION ALL SELECT 'platform_payments', 'external_transaction_id', 'REQUIRED FOR M-PESA CLAIM MIGRATION'
    UNION ALL SELECT 'platform_payments', 'invoice_id', 'REQUIRED FOR M-PESA CLAIM MIGRATION'
    UNION ALL SELECT 'platform_payments', 'id', 'REQUIRED FOR M-PESA CLAIM MIGRATION'
    UNION ALL SELECT 'platform_invoices', 'tenant_id', 'REQUIRED FOR MIRROR DETECTION'
    UNION ALL SELECT 'platform_invoices', 'id', 'REQUIRED FOR MIRROR DETECTION'
    UNION ALL SELECT 'platform_invoices', 'invoice_number', 'REQUIRED FOR MIRROR DETECTION'
) AS expected
LEFT JOIN information_schema.TABLES t
  ON t.TABLE_SCHEMA = @target_schema
 AND t.TABLE_NAME = expected.table_name
LEFT JOIN information_schema.COLUMNS c
  ON c.TABLE_SCHEMA = @target_schema
 AND c.TABLE_NAME = expected.table_name
 AND c.COLUMN_NAME = expected.column_name
ORDER BY expected.table_name, expected.importance, expected.column_name;

-- Safety reminder:
-- Do not apply database/036_mpesa_receipt_claims.sql unless its source tables and columns
-- are present, duplicate/long receipt preflight results have been reviewed, and the migration
-- has been tested on a disposable copy. Do not add payment_id/invoice_id columns blindly:
-- first classify existing account_transactions and receipt rows and define a safe backfill.
