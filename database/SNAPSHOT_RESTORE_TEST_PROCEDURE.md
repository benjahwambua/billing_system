# Disposable snapshot restore and SQL validation (Windows/XAMPP)

**Do not run the candidate migration against the working `billing_system` database.** This procedure uses a separate database named `billing_system_snapshot_test_20261027`.

## 1. Preserve the working database

1. Open `http://localhost/phpmyadmin`.
2. Select the current `billing_system` database and choose **Export → Quick → SQL → Export**.
3. Save the backup outside the project folder and verify that the downloaded `.sql` file is non-empty.
4. Do not drop, rename, or import over the working database.

## 2. Restore the supplied snapshot into a separate database

1. In phpMyAdmin, choose **New** and create `billing_system_snapshot_test_20261027` using `utf8mb4` / `utf8mb4_unicode_ci` if prompted.
2. Select the new database (confirm its name in the left navigation).
3. Choose **Import**, upload the latest `billing_system (1).sql` export, and start the import. The export does not contain a `USE billing_system` statement, so it can be imported into the selected disposable database.
4. Confirm the import completed and the tables/data are present. If the import fails, stop and resolve the restore error before proceeding.

## 3. Run read-only baseline diagnostics

Run each SQL file while `billing_system_snapshot_test_20261027` is selected:

1. `database/snapshot_upgrade_preflight.sql`
2. `database/payment_schema_compatibility_preflight.sql`
3. `database/snapshot_runtime_contract_preflight.sql`
4. `database/snapshot_candidate_collision_preflight.sql`
5. `database/snapshot_financial_reconciliation_preflight.sql`

Save/export the result sets. The first result set in each file identifies the selected schema/server. Stop immediately if the selected schema is not `billing_system_snapshot_test_20261027`. Record any existing target tables or columns before considering the candidate. The collision preflight lists all candidate target tables, checks `tenants.id` key type, and exposes existing key-column/FK definitions.

## 4. Review the candidate before running it

Candidate file: `database/037_snapshot_compatible_core_runtime.sql`.

- Do not run it if a table it creates already exists with an unknown or different structure. Inspect that table with `SHOW CREATE TABLE` first.
- The candidate does not backfill financial records, seed platform plans, create portal tokens, or create `mpesa_receipt_claims`.
- It is not a complete deployment migration: optional SMS, OLT/ONU, network monitoring, support-ticket, voucher and advertising tables remain separate work.
- It contains conditional DDL for `hotspot_packages.router_id`; test it on MariaDB 10.4.32 and check its second-run behavior.

## 5. Execute only in the disposable database

1. Confirm the selected database is `billing_system_snapshot_test_20261027`.
2. Import/run `database/037_snapshot_compatible_core_runtime.sql`.
3. If any statement fails, stop. Do not continue with later migrations or rerun blindly; save the exact error and inspect which earlier statements succeeded.
4. If it completes, run `database/snapshot_runtime_contract_preflight.sql` again. Investigate any `TABLE MISSING` or `COLUMN MISSING` result.
5. Run `database/snapshot_upgrade_preflight.sql` again and inspect tenant-key compatibility.
6. Run `database/snapshot_financial_reconciliation_preflight.sql` again. Compare the financial totals and findings with the baseline. This candidate should not modify payments, invoices, receipts, or account-ledger rows.
7. Run the candidate a second time only after reviewing the first run. Confirm it completes without duplicate-column/index errors. `CREATE TABLE IF NOT EXISTS` does not repair partial tables, so a successful second run alone does not prove compatibility.

## 6. What to report back

Send the exact phpMyAdmin import/SQL error, if any, plus the result of the target identity query and any missing/mismatched object rows. Do not send gateway secrets, M-Pesa passkeys, consumer keys, customer phone numbers, or full financial records. Once this disposable restore passes, the schema can be refined into a reviewed upgrade. The working database remains untouched throughout this procedure.

## Current validation boundary

The current environment has no `mariadb`, `mysql`, `mysqld`, or Docker executable available, so it cannot execute this SQL against MariaDB. Static source review has checked 134 required column names against the candidate definitions; this is not a substitute for the disposable restore run.