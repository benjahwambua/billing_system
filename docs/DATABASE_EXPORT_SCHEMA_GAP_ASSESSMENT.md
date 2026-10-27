# Database Export Schema Gap Assessment

**Assessment date:** 2026-10-27  
**Source reviewed:** user-provided phpMyAdmin dump `billing_system (1).sql`  
**Source server metadata:** MariaDB 10.4.32; PHP 8.0.30  
**Scope:** Static inspection of the supplied SQL file only. No SQL was executed and no live database was queried.

## What the supplied file establishes

- It contains 30 `CREATE TABLE` definitions and 64 foreign-key constraint definitions.
- It includes both schema and data inserts, so it is not a schema-only export.
- `tenants.id` is defined as `BIGINT UNSIGNED`.
- `payments` contains `id`, `tenant_id`, `invoice_id`, `amount`, `payment_date`, and `reference_number` (not a `reference` column in the shown schema).
- `receipts` contains `id`, `tenant_id`, `payment_id`, `amount`, and `reference_number`, but no `invoice_id` column.
- `account_transactions` contains `id`, `tenant_id`, `amount`, `reference_type`, and `reference_id`, but no `payment_id` or `invoice_id` columns.
- The export does not define `payment_gateways`, `payment_gateway_transactions`, `payment_gateway_events`, `hotspot_portal_settings`, `hotspot_sales`, `service_subscriptions`, `platform_payments`, or `mpesa_receipt_claims`.

## Consequences for repository preflight scripts

1. `database/payment_ledger_reconciliation_preflight.sql` cannot be run unchanged against this exported schema: its documented column assumptions for payments, receipts, and account_transactions do not match exactly. Adapt it only after reviewing the intended runtime schema and source migration history.
2. `database/mpesa_receipt_integrity_preflight.sql` and `database/036_mpesa_receipt_claims.sql` depend on gateway/platform payment tables that are absent from this export. Do not run them against a database with this schema until the missing tables are intentionally introduced and their prerequisites verified.
3. The source export alone cannot establish whether these tables are absent from the current working XAMPP database; it may represent an earlier schema state or a different database snapshot.
4. The export contains data. Do not publish it or use it as a clean-install artifact. Prepare a separate schema-only export from the confirmed source database for baseline work.

## Safe next steps

- Confirm whether the dump came from the current, known-good XAMPP `billing_system` database and whether it was taken before or after the recent M-Pesa migrations.
- On a backup/disposable database only, collect `SHOW CREATE TABLE` output for `payments`, `receipts`, `account_transactions`, `tenants`, and any available gateway/platform payment tables.
- Compare that output against the current repository schema and migration files, including the expected columns in the payment callback and subscription recovery code.
- Reconcile historical financial rows before adding/rekeying ledger fields or applying migration 036. Do not delete or auto-attach financial artifacts based only on a preflight report.
- Build and validate a clean-install baseline separately from the current working database, then test the upgrade path from a restored backup.

## Limitations

This assessment is based solely on the supplied SQL file's text. It does not prove live database state, row-level integrity, migration success, application runtime correctness, or production readiness.
