-- READ-ONLY preflight for receipt/account-ledger idempotency constraints.
-- Run against a BACKUP or disposable copy before considering any unique-index migration.
-- Prerequisite: receipts and account_transactions must both have id, payment_id,
-- and tenant_id columns. Confirm with SHOW COLUMNS before running this file.
-- Do not run a schema migration until every duplicate group is reviewed/reconciled.

SELECT 'receipts' AS table_name, tenant_id, payment_id, COUNT(*) AS duplicate_count,
       GROUP_CONCAT(id ORDER BY id) AS row_ids
FROM receipts
WHERE payment_id IS NOT NULL
GROUP BY tenant_id, payment_id
HAVING COUNT(*) > 1
ORDER BY duplicate_count DESC, tenant_id, payment_id;

SELECT 'account_transactions' AS table_name, tenant_id, payment_id, COUNT(*) AS duplicate_count,
       GROUP_CONCAT(id ORDER BY id) AS row_ids
FROM account_transactions
WHERE payment_id IS NOT NULL
GROUP BY tenant_id, payment_id
HAVING COUNT(*) > 1
ORDER BY duplicate_count DESC, tenant_id, payment_id;

-- Expected: zero rows from both queries before adding unique indexes on
-- (tenant_id, payment_id). Null payment_id rows are excluded because MySQL
-- unique indexes allow multiple NULL values; the application now requires the
-- column for idempotency, but historical NULL rows should be reviewed separately.
