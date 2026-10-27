# Snapshot-compatible core runtime schema candidate

**Status: candidate only. Review and execute only against a disposable restore of the 2026-10-27 export. This is not approval to alter the working XAMPP database.**

File: `database/037_snapshot_compatible_core_runtime.sql`.

## What this candidate does

- Creates missing tenant M-Pesa gateway tables, gateway event storage, and hotspot portal settings. Includes the `phone_number` field used by platform M-Pesa STK requests.
- Creates the missing `hotspot_packages` and `hotspot_access_codes` foundations from migration 007, including the router link from migration 012 and `sold_at`/`activated_at` fields used by runtime flows.
- Creates the missing `hotspot_sales` table using fields explicitly present across historical migrations 008, 013, 015, and 016, plus `created_at` required by the public portal's recent-sale lookup, with `BIGINT UNSIGNED` tenant and primary keys compatible with the current export.
- Creates the missing service-subscription lineage table using the fields defined in migrations 008 and 018, correcting the historical signed `INT` tenant key mismatch.
- Creates platform SaaS plan, subscription, invoice, payment, gateway, and key/value settings tables from the repository's existing migration contracts.
- Adds `hotspot_packages.router_id` and its index only when they are missing.

## What it deliberately does not do

- It does not insert, update, delete, or backfill customer, invoice, payment, receipt, or account-ledger rows.
- It does not seed starter plans, platform settings, portal tokens, or subscriptions.
- It does not create M-Pesa receipt claims or decide how duplicate historical receipts should be resolved.
- It does not upgrade optional SMS, OLT/ONU, network monitoring, support-ticket, voucher, or advertising modules.
- It does not repair an already-existing partial table. `CREATE TABLE IF NOT EXISTS` leaves existing definitions untouched.

## Mandatory review before any test execution

1. Back up the working database and restore the supplied export into a separate disposable database.
2. Run `snapshot_upgrade_preflight.sql`, `payment_schema_compatibility_preflight.sql`, and `snapshot_runtime_contract_preflight.sql` first. Confirm target tables are missing as expected and compare the export with the disposable restore.
3. Inspect `SHOW CREATE TABLE` for `tenants`, `hotspot_packages`, `hotspot_access_codes`, `mikrotik_routers`, `payments`, and `invoices` in the disposable restore.
4. Review every foreign-key, tenant-isolation, and application column expectation. Do not run this file if any table it creates already exists with a different shape; reconcile that table first.
5. Execute only on the disposable restore, capture errors and all resulting `SHOW CREATE TABLE` output, then run the preflights again.
6. Run `snapshot_runtime_contract_preflight.sql` again and confirm every required object is present. Run the financial reconciliation preflight and compare key financial row counts and sums before/after. The expected outcome is no change to financial records.

## Validation status

The DDL was assembled by reviewing the supplied export and the source definitions in historical migrations. It has **not** been executed against MariaDB in this environment. The PHP-lint workflow does not validate SQL syntax or schema compatibility. Treat this as an implementation candidate until it passes the disposable-restore run and second-run checks.
