# Snapshot-specific database upgrade plan

**Status: review plan only — not an executable migration and not approval to alter the working database.**  
**Snapshot:** user-confirmed `billing_system (1).sql`, reviewed 2026-10-27.  
**Source metadata:** MariaDB 10.4.32 / PHP 8.0.30.  
**Known shape:** 30 table definitions and 64 FK definitions in the supplied export; it contains both schema and data.

## Objective

Bring the application schema into line with the current code while preserving all customer, invoice, payment, receipt, and account-ledger records. The snapshot is partially migrated: some later runtime tables exist while foundational tables referenced by other migrations are absent. Therefore, neither a full historical migration replay nor a fresh-install script is a safe upgrade strategy.

## Mandatory first step

1. Make a new backup of the actual working database and verify the export is non-empty. Keep it outside the web root.
2. Restore the supplied export into a **new disposable database** on the supported MariaDB version. Do not use the working `billing_system` database as the test target.
3. Run `database/snapshot_upgrade_preflight.sql` and `database/payment_schema_compatibility_preflight.sql` against that disposable restore. Save all result sets with the candidate revision and server version.
4. Compare the result sets with the supplied export and the live source's current schema. The export is evidence for this plan, not proof that the live XAMPP database is identical.

## Proposed upgrade phases

### Phase A — inventory and prerequisites (read-only)

- Capture the full table/column/index/constraint inventory and migration manifest hashes.
- Record every existing migration-owned table and column before writing DDL.
- Check all tenant-scoped `tenant_id` columns against `tenants.id BIGINT UNSIGNED`; do not create a foreign key where type/signedness differs.
- Confirm actual migration history if one exists. Do not infer that a migration ran merely because one of its tables exists; partial migration execution is possible.
- Resolve duplicate numeric prefixes explicitly; filenames, dependencies, and checksums—not numeric prefixes alone—must identify migrations.

### Phase B — foundational runtime schema

Create a reviewed, dependency-ordered set of table definitions for genuinely missing runtime tables, starting with the gateway/event tables and service-subscription structures. Use `BIGINT UNSIGNED` for tenant keys where they reference the existing `tenants.id`. Keep creation separate from alteration of pre-existing tables.

Do not blindly replay `013_mpesa_hotspot_runtime.sql`: it declares gateway `tenant_id` columns as `INT UNSIGNED` and alters `hotspot_sales`, which is absent from the supplied export. Reconcile the intended `hotspot_sales` model before defining it; do not invent its columns based only on the later ALTER statements.

### Phase C — platform subscription and payment schema

- Establish and review `platform_settings` before any migration that inserts settings.
- Create `platform_plans`, `tenant_platform_subscriptions`, `platform_invoices`, and `platform_payments` in a dependency order after verifying current application expectations.
- Add platform gateway tables and STK fields only after inspecting whether each prerequisite table and each individual column/index already exists.
- Do not replay `023_flexihub_saas_subscriptions.sql` unchanged: its final write to `platform_settings` can fail after prior tables have already been created.
- Review invoice status/amount fields and callback code together; the tenant invoice schema is not the same as platform invoice schema.

### Phase D — optional module schema

Only after the core payment path has a reviewed schema, address the missing SMS, failed-payment reconciliation, OLT/ONU polling, network monitoring, voucher/agent/adverts, support, and related tables. For each module, inspect the exact migration file, dependencies, and current columns before applying alterations. An absent optional table should not be created with guessed structure just to silence a runtime error.

### Phase E — financial integrity and receipt claims

- First produce read-only duplicate and orphan reports using SQL compatible with the actual snapshot columns.
- Existing tenant payment links may use `account_transactions.reference_type='payment'` plus `reference_id`; the supplied schema has no `account_transactions.payment_id`. Receipts have `payment_id` and `reference_number`, but no `invoice_id`.
- Do not add assumed invoice/payment columns or automatically attach ambiguous ledger rows.
- Treat `036_mpesa_receipt_claims.sql` as a one-time data migration. It requires gateway/platform tables and duplicate-receipt reconciliation. Never use `INSERT IGNORE` or silently discard duplicate receipts to make it pass.

### Phase F — migration tracking and repeatability

Before a migration runner is approved, it must record a stable migration identity and SHA-256, fail closed on checksum drift or unmet prerequisites, detect partial prior execution, and report failures without pretending MySQL/MariaDB DDL is transactionally rolled back. No public web route may run migrations.

## Acceptance criteria before applying to the working database

- Disposable restore matches the supplied export's schema and expected row counts for key financial tables.
- Each DDL change is reviewed against the actual `SHOW CREATE TABLE` output and existing indexes/constraints.
- Upgrade run completes on a second disposable restore without duplicate-column/index errors or partial unexplained changes.
- Before/after checks confirm tenant IDs, invoice totals/balances, payment amounts/references, receipt links, and ledger row counts are unchanged except for documented, approved additions.
- Tenant isolation and payment idempotency tests pass against the upgraded disposable database.
- A tested restore procedure exists and the final migration manifest is attached to the release record.

## Current decision

**No migration from this plan is approved to run against the working XAMPP database.** This plan establishes the review sequence; it does not claim that the schema upgrade, module behavior, or deployment is complete.
