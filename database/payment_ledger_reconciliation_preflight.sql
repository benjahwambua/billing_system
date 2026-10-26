-- READ-ONLY reconciliation preflight for customer payments and their financial artifacts.
-- Run only against a backup/disposable copy first. These queries do not modify data.
-- Confirm the required columns exist with SHOW COLUMNS before running:
-- payments: id, tenant_id, invoice_id, amount, reference, payment_date
-- receipts: id, tenant_id, payment_id, invoice_id, amount, reference
-- account_transactions: id, tenant_id, payment_id, invoice_id, amount
-- If your actual schema differs, adapt this report after reviewing SHOW COLUMNS.
--
-- 1. Payments missing a receipt or account-ledger entry.
SELECT p.tenant_id, p.id AS payment_id, p.invoice_id, p.amount AS payment_amount,
       CASE WHEN r.id IS NULL THEN 'MISSING' ELSE 'PRESENT' END AS receipt_status,
       CASE WHEN atx.id IS NULL THEN 'MISSING' ELSE 'PRESENT' END AS ledger_status
FROM payments p
LEFT JOIN receipts r
  ON r.tenant_id = p.tenant_id AND r.payment_id = p.id
LEFT JOIN account_transactions atx
  ON atx.tenant_id = p.tenant_id AND atx.payment_id = p.id
WHERE r.id IS NULL OR atx.id IS NULL
ORDER BY p.tenant_id, p.id;

-- 2. Duplicate receipt artifacts for the same tenant/payment.
SELECT tenant_id, payment_id, COUNT(*) AS row_count,
       GROUP_CONCAT(id ORDER BY id) AS row_ids
FROM receipts
WHERE payment_id IS NOT NULL
GROUP BY tenant_id, payment_id
HAVING COUNT(*) > 1
ORDER BY row_count DESC, tenant_id, payment_id;

-- 3. Duplicate account-ledger artifacts for the same tenant/payment.
SELECT tenant_id, payment_id, COUNT(*) AS row_count,
       GROUP_CONCAT(id ORDER BY id) AS row_ids
FROM account_transactions
WHERE payment_id IS NOT NULL
GROUP BY tenant_id, payment_id
HAVING COUNT(*) > 1
ORDER BY row_count DESC, tenant_id, payment_id;

-- 4. Amount or invoice mismatches. Each output row is a candidate for manual review;
-- do not automatically edit/delete financial records based only on this report.
SELECT p.tenant_id, p.id AS payment_id, p.invoice_id AS payment_invoice_id,
       p.amount AS payment_amount,
       r.id AS receipt_id, r.invoice_id AS receipt_invoice_id,
       r.amount AS receipt_amount,
       atx.id AS ledger_transaction_id, atx.invoice_id AS ledger_invoice_id,
       atx.amount AS ledger_amount
FROM payments p
JOIN receipts r
  ON r.tenant_id = p.tenant_id AND r.payment_id = p.id
JOIN account_transactions atx
  ON atx.tenant_id = p.tenant_id AND atx.payment_id = p.id
WHERE ABS(COALESCE(p.amount, 0) - COALESCE(r.amount, 0)) > 0.00001
   OR ABS(COALESCE(p.amount, 0) - COALESCE(atx.amount, 0)) > 0.00001
   OR NOT (p.invoice_id <=> r.invoice_id)
   OR NOT (p.invoice_id <=> atx.invoice_id)
ORDER BY p.tenant_id, p.id;

-- 5. Historical artifacts that cannot be tied to a payment using payment_id.
-- These require manual classification; do not delete or auto-attach them.
SELECT 'receipts' AS table_name, id AS artifact_id, tenant_id, invoice_id, amount, reference
FROM receipts
WHERE payment_id IS NULL
UNION ALL
SELECT 'account_transactions' AS table_name, id AS artifact_id, tenant_id, invoice_id, amount, reference
FROM account_transactions
WHERE payment_id IS NULL
ORDER BY table_name, tenant_id, artifact_id;
