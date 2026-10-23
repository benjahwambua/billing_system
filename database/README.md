# Flexihub Billing System — Database SQL Guide

## Current local XAMPP database

The existing local database is `billing_system`. It has already been repaired to use `BIGINT UNSIGNED` tenant IDs and has 24 foreign keys referencing `tenants.id`. **Do not import all SQL files into this existing database as one batch.** Several files are incremental migrations and will fail or repeat schema changes if rerun.

## Start here: migration registry

Read [MIGRATION_REGISTRY.md](MIGRATION_REGISTRY.md) before touching schema. It assigns stable logical IDs to historical migration files, including duplicate numeric prefixes, and records known prerequisites and risks. **The registry is a review queue, not an approved execution sequence. No automated migration execution is authorized yet.**

The clean-install baseline and tested upgrade path are still release blockers. Preserve the working database and historical migration files.

## What to use

### Read-only checks
- `tenant_integrity_verification.sql` — run when checking tenant foreign keys and orphan tenant references. The orphan audit uses fully qualified `billing_system` table names so it does not rely on phpMyAdmin's current database context.
- `003_tenant_isolation_audit.sql` — legacy schema inspection only; read-only.

### Existing database migrations — only when their prerequisites apply
- `004_tenant_isolation.sql` — legacy tenant-column/index/FK migration. Not a universal import script; assumes the older schema and target tenant exist.
- `005_roles_permissions.sql`, `006_access_control.sql`, `007_module_foundation.sql`, `008_operational_workflows.sql` — incremental schema changes; inspect current schema before running.
- `009_legacy_tenant_bootstrap.sql` — legacy users/tenant bootstrap; only for installations that need it.
- `010_repair_tenant_foreign_keys.sql` — one-time legacy repair. **Do not rerun against the already repaired local database.** It drops/recreates the users FK and adds other FKs without existence guards.
- `010_service_activation_hotspot_runtime.sql` through `035_support_ticket_workflow.sql` — feature-specific incremental migrations. Apply only after reviewing the registry, verifying prerequisites, and confirming that each change is absent from the target schema.

## M-Pesa receipt uniqueness migration

Before applying `036_mpesa_receipt_claims.sql`, run `mpesa_receipt_integrity_preflight.sql` against the intended database. Resolve every duplicate receipt across `payment_gateway_transactions` and `platform_payments`, and review receipts longer than 191 characters. The migration backfills a single global receipt-claim table and deliberately fails if a duplicate remains. Do not use `INSERT IGNORE` or delete financial records to force it through. The callback code reserves receipts inside the same transaction as payment finalization; the database primary key is the concurrency guard.

This migration is a release-sensitive, one-time change and must be tested on a disposable database copy first. Do not apply it to the working XAMPP database as part of this code change.

## Read-only migration preflight

From the repository root, run:

```bash
php database/migration_preflight.php
```

This command does **not** connect to MySQL or execute SQL. It inventories numbered migration files, reports duplicate numeric prefixes, flags common non-idempotent DDL patterns for review, and reports when no recognized clean-install baseline file is present. A non-zero exit status means repository-level blockers or warnings were found. It is a guardrail, not an approved migration order and not proof that a migration is safe for a specific database.

## Existing database checklist

1. Back up the database in phpMyAdmin and verify the backup can be read.
2. Run `tenant_integrity_verification.sql` for read-only checks.
3. Compare actual columns, indexes, constraints, and migration history with the registry.
4. Apply only a reviewed migration that is demonstrably missing and has been tested on a disposable copy first.
5. Never re-run migration 010 tenant repair on the already repaired local database.

## Consolidation rule

Keep migration files atomic and ordered; consolidate the **instructions and runner**, not all DDL into one enormous SQL file. A single flattened SQL dump should be generated from a known-good schema snapshot, not assembled by concatenating migrations that may add the same columns or constraints.

## Release gate

Until the canonical baseline is derived from a reviewed schema snapshot and both empty-install and existing-schema upgrade paths pass on disposable databases, database installation/upgrade remains a deployment blocker. See [Migration Dependency Audit](MIGRATION_DEPENDENCY_AUDIT.md).
