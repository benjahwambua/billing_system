# Snapshot financial reconciliation preflight

**Status: read-only diagnostic; not a migration and not authorization to change the working database.**

This script is specific to the user-confirmed `billing_system (1).sql` export reviewed on 2026-10-27. The export reports MariaDB 10.4.32. The SQL uses the exported column names and does not assume `payments.reference`, `receipts.invoice_id`, `account_transactions.payment_id`, or `account_transactions.invoice_id`.

## Safe execution sequence

1. Make a fresh backup of the actual working database and verify the file is non-empty.
2. Restore the supplied export into a **new disposable database** on MariaDB, keeping the working database untouched.
3. Select the disposable database in the SQL client.
4. Run `database/snapshot_financial_reconciliation_preflight.sql`.
5. Save all result sets with the export hash, database server version, and candidate Git commit.
6. Review every returned finding against source documents and payment records before approving any correction.

The script contains only `SELECT` statements. It does not insert ledger rows, rewrite invoice balances, change receipts, add indexes, or create tables. A clean result does not prove the PHP workflows, gateway callbacks, tenant isolation, or external services work.

## Result sets

1. **Target identity** — selected schema, server version, SQL mode. Stop if the selected schema is not the disposable restore.
2. **Tenant totals** — independently aggregated invoice, payment, receipt, and account-ledger totals. Different totals can be legitimate; use them as a high-level comparison, not as proof of an error.
3. **Invoice arithmetic** — invoices where `total` differs from `amount_paid + balance`, or amounts are negative/out of range.
4. **Payment/invoice linkage** — payments linked to missing invoices or an invoice with a different tenant/customer. Payments with `invoice_id IS NULL` are not flagged here.
5. **Invoice payment totals** — compares `invoices.amount_paid` with payments linked by tenant and invoice. Investigate differences; never auto-overwrite either side.
6. **Receipt linkage** — reports missing payment links, tenant/customer mismatches, and amount differences between a receipt and its payment.
7. **Payment ledger references** — reports `account_transactions` rows with `reference_type='payment'` whose `reference_id` does not resolve to a matching payment by tenant, customer, and amount.
8. **Missing payment ledger references** — lists payments without a corresponding `reference_type='payment'` ledger row. Some may be legitimate historical exceptions; do not auto-create ledger rows.
9. **Internet-account association** — reports payment or ledger rows whose account is missing or belongs to a different tenant/customer.

## Important limitations

- The checks deliberately report discrepancies; they do not infer the correct value or repair records.
- `account_transactions` has no `payment_id` column in the supplied export; the payment link is checked using `reference_type` and `reference_id`.
- `receipts` has no `invoice_id` column in the supplied export; receipt-to-invoice lineage is therefore not inferred.
- A receipt may not be one-to-one with a payment in every historical workflow. Review flagged amount differences in context.
- This file has not been executed against MariaDB in this environment. Run it on the disposable restore and review its output before approving a migration.
