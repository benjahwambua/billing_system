-- READ-ONLY financial integrity preflight for the user-confirmed 2026-10-27 billing_system export.
-- IMPORTANT: select a DISPOSABLE restored copy of the export before running this file.
-- MariaDB target: export metadata reports 10.4.32; verify the actual server version.
-- This script performs SELECTs only. It does not change schema, rows, balances, or payment records.
-- Results are findings for manual reconciliation, not instructions to auto-correct financial data.
-- It uses the exported schema: payments.reference_number; receipts.payment_id/reference_number;
-- account_transactions.reference_type/reference_id; invoices.total/amount_paid/balance.

-- Result 1: record the database target and server before interpreting any findings.
SELECT DATABASE() AS selected_schema,
       VERSION() AS database_server_version,
       @@sql_mode AS sql_mode;

-- Result 2: high-level financial totals by tenant.
-- Each table is aggregated separately to avoid multiplying sums through joins.
SELECT t.id AS tenant_id,
       t.tenant_code,
       t.name AS tenant_name,
       COALESCE(i.invoice_count, 0) AS invoice_count,
       COALESCE(i.invoice_total, 0.00) AS invoice_total,
       COALESCE(i.invoice_amount_paid, 0.00) AS invoice_amount_paid,
       COALESCE(i.invoice_balance, 0.00) AS invoice_balance,
       COALESCE(p.payment_count, 0) AS payment_count,
       COALESCE(p.payment_total, 0.00) AS payment_total,
       COALESCE(r.receipt_count, 0) AS receipt_count,
       COALESCE(r.receipt_total, 0.00) AS receipt_total,
       COALESCE(a.ledger_count, 0) AS ledger_count,
       COALESCE(a.ledger_amount, 0.00) AS ledger_amount
FROM tenants t
LEFT JOIN (
    SELECT tenant_id, COUNT(*) AS invoice_count,
           SUM(total) AS invoice_total,
           SUM(amount_paid) AS invoice_amount_paid,
           SUM(balance) AS invoice_balance
    FROM invoices GROUP BY tenant_id
) i ON i.tenant_id = t.id
LEFT JOIN (
    SELECT tenant_id, COUNT(*) AS payment_count, SUM(amount) AS payment_total
    FROM payments GROUP BY tenant_id
) p ON p.tenant_id = t.id
LEFT JOIN (
    SELECT tenant_id, COUNT(*) AS receipt_count, SUM(amount) AS receipt_total
    FROM receipts GROUP BY tenant_id
) r ON r.tenant_id = t.id
LEFT JOIN (
    SELECT tenant_id, COUNT(*) AS ledger_count, SUM(amount) AS ledger_amount
    FROM account_transactions GROUP BY tenant_id
) a ON a.tenant_id = t.id
ORDER BY t.id;

-- Result 3: invoices whose stored total does not reconcile to amount_paid + balance.
-- Draft/cancelled rows are included intentionally so their state can be reviewed.
SELECT id AS invoice_id, tenant_id, invoice_number, customer_id, status,
       subtotal, total, amount_paid, balance,
       ROUND(total - amount_paid - balance, 2) AS arithmetic_difference
FROM invoices
WHERE ABS(ROUND(total - amount_paid - balance, 2)) > 0.01
   OR amount_paid < 0
   OR balance < 0
   OR amount_paid > total + 0.01
ORDER BY tenant_id, id;

-- Result 4: payments with a missing invoice or a tenant/customer mismatch.
-- Payments with invoice_id NULL are not flagged here; they may be valid unallocated payments.
SELECT p.id AS payment_id, p.tenant_id AS payment_tenant_id,
       p.customer_id AS payment_customer_id, p.invoice_id,
       p.amount AS payment_amount, p.reference_number,
       i.tenant_id AS invoice_tenant_id, i.customer_id AS invoice_customer_id,
       i.total AS invoice_total, i.status AS invoice_status,
       CASE
         WHEN i.id IS NULL THEN 'INVOICE NOT FOUND'
         WHEN i.tenant_id <> p.tenant_id THEN 'TENANT MISMATCH'
         WHEN i.customer_id <> p.customer_id THEN 'CUSTOMER MISMATCH'
         ELSE 'REVIEW'
       END AS finding
FROM payments p
LEFT JOIN invoices i ON i.id = p.invoice_id
WHERE p.invoice_id IS NOT NULL
  AND (i.id IS NULL OR i.tenant_id <> p.tenant_id OR i.customer_id <> p.customer_id)
ORDER BY p.tenant_id, p.id;

-- Result 5: compare invoice amount_paid with the sum of linked payment rows.
-- Differences are findings only; do not overwrite invoice totals from this report.
SELECT i.id AS invoice_id, i.tenant_id, i.invoice_number, i.status,
       i.total, i.amount_paid AS stored_amount_paid, i.balance,
       COALESCE(p.linked_payment_count, 0) AS linked_payment_count,
       COALESCE(p.linked_payment_total, 0.00) AS linked_payment_total,
       ROUND(i.amount_paid - COALESCE(p.linked_payment_total, 0.00), 2) AS paid_difference
FROM invoices i
LEFT JOIN (
    SELECT tenant_id, invoice_id, COUNT(*) AS linked_payment_count,
           SUM(amount) AS linked_payment_total
    FROM payments
    WHERE invoice_id IS NOT NULL
    GROUP BY tenant_id, invoice_id
) p ON p.tenant_id = i.tenant_id AND p.invoice_id = i.id
WHERE ABS(ROUND(i.amount_paid - COALESCE(p.linked_payment_total, 0.00), 2)) > 0.01
ORDER BY i.tenant_id, i.id;

-- Result 6: receipts whose linked payment is missing or disagrees on tenant/customer/amount.
-- A receipt is joined by payment ID first; the tenant comparison detects cross-tenant links.
SELECT r.id AS receipt_id, r.tenant_id AS receipt_tenant_id,
       r.receipt_number, r.payment_id, r.customer_id AS receipt_customer_id,
       r.amount AS receipt_amount, r.reference_number AS receipt_reference,
       p.tenant_id AS payment_tenant_id, p.customer_id AS payment_customer_id,
       p.amount AS payment_amount, p.reference_number AS payment_reference,
       CASE
         WHEN p.id IS NULL THEN 'PAYMENT NOT FOUND'
         WHEN p.tenant_id <> r.tenant_id THEN 'TENANT MISMATCH'
         WHEN p.customer_id <> r.customer_id THEN 'CUSTOMER MISMATCH'
         WHEN ABS(ROUND(p.amount - r.amount, 2)) > 0.01 THEN 'AMOUNT MISMATCH'
         ELSE 'REVIEW'
       END AS finding
FROM receipts r
LEFT JOIN payments p ON p.id = r.payment_id
WHERE p.id IS NULL
   OR p.tenant_id <> r.tenant_id
   OR p.customer_id <> r.customer_id
   OR ABS(ROUND(p.amount - r.amount, 2)) > 0.01
ORDER BY r.tenant_id, r.id;

-- Result 7: payment-referenced account ledger rows that do not resolve to a same-tenant,
-- same-customer payment with the same amount. Other reference types are not assumed to be payments.
SELECT a.id AS ledger_id, a.tenant_id AS ledger_tenant_id,
       a.internet_account_id, a.customer_id AS ledger_customer_id,
       a.transaction_type, a.reference_type, a.reference_id, a.amount AS ledger_amount,
       p.tenant_id AS payment_tenant_id, p.customer_id AS payment_customer_id,
       p.amount AS payment_amount, p.reference_number,
       CASE
         WHEN p.id IS NULL THEN 'PAYMENT NOT FOUND'
         WHEN p.tenant_id <> a.tenant_id THEN 'TENANT MISMATCH'
         WHEN p.customer_id <> a.customer_id THEN 'CUSTOMER MISMATCH'
         WHEN ABS(ROUND(p.amount - a.amount, 2)) > 0.01 THEN 'AMOUNT MISMATCH'
         ELSE 'REVIEW'
       END AS finding
FROM account_transactions a
LEFT JOIN payments p
  ON p.id = a.reference_id
 AND LOWER(COALESCE(a.reference_type, '')) = 'payment'
WHERE LOWER(COALESCE(a.reference_type, '')) = 'payment'
  AND (p.id IS NULL
       OR p.tenant_id <> a.tenant_id
       OR p.customer_id <> a.customer_id
       OR ABS(ROUND(p.amount - a.amount, 2)) > 0.01)
ORDER BY a.tenant_id, a.id;

-- Result 8: payments with no matching payment-reference ledger row.
-- A missing ledger row is a reconciliation item, not permission to insert one automatically.
SELECT p.id AS payment_id, p.tenant_id, p.customer_id, p.internet_account_id,
       p.invoice_id, p.payment_date, p.amount, p.reference_number
FROM payments p
LEFT JOIN account_transactions a
  ON a.tenant_id = p.tenant_id
 AND LOWER(COALESCE(a.reference_type, '')) = 'payment'
 AND a.reference_id = p.id
WHERE a.id IS NULL
ORDER BY p.tenant_id, p.id;

-- Result 9: payments and ledger rows whose account/customer/tenant association conflicts.
SELECT p.id AS payment_id, p.tenant_id AS payment_tenant_id,
       p.customer_id AS payment_customer_id, p.internet_account_id,
       ia.tenant_id AS account_tenant_id, ia.customer_id AS account_customer_id
FROM payments p
LEFT JOIN internet_accounts ia ON ia.id = p.internet_account_id
WHERE p.internet_account_id IS NOT NULL
  AND (ia.id IS NULL OR ia.tenant_id <> p.tenant_id OR ia.customer_id <> p.customer_id)
ORDER BY p.tenant_id, p.id;

SELECT a.id AS ledger_id, a.tenant_id AS ledger_tenant_id,
       a.customer_id AS ledger_customer_id, a.internet_account_id,
       ia.tenant_id AS account_tenant_id, ia.customer_id AS account_customer_id
FROM account_transactions a
LEFT JOIN internet_accounts ia ON ia.id = a.internet_account_id
WHERE ia.id IS NULL
   OR ia.tenant_id <> a.tenant_id
   OR ia.customer_id <> a.customer_id
ORDER BY a.tenant_id, a.id;
