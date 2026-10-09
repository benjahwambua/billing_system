# Flexihub Billing System — Database SQL Guide

## Current local XAMPP database

The existing local database is `billing_system`. It has already been repaired to use `BIGINT UNSIGNED` tenant IDs and has 24 foreign keys referencing `tenants.id`. **Do not import all SQL files into this existing database as one batch.** Several files are incremental migrations and will fail or repeat schema changes if rerun.

## What to use

### Read-only checks
- `tenant_integrity_verification.sql` — run when checking tenant foreign keys and orphan tenant references. The orphan audit uses fully qualified `billing_system` table names so it does not rely on phpMyAdmin's current database context.
- `003_tenant_isolation_audit.sql` — legacy schema inspection only; read-only.

### Existing database migrations — only when their prerequisites apply
- `004_tenant_isolation.sql` — legacy tenant-column/index/FK migration. Not a universal import script; assumes the older schema and target tenant exist.
- `005_roles_permissions.sql`, `006_access_control.sql`, `007_module_foundation.sql`, `008_operational_workflows.sql` — incremental schema changes; inspect current schema before running.
- `009_legacy_tenant_bootstrap.sql` — legacy users/tenant bootstrap; only for installations that need it.
- `010_repair_tenant_foreign_keys.sql` — one-time legacy repair. **Do not rerun against the already repaired local database.** It drops/recreates the users FK and adds the other FKs without existence guards.
- `010_service_activation_hotspot_runtime.sql` through `034_*.sql` — feature-specific incremental migrations. Apply only in a tracked migration sequence after checking their prerequisites and whether each change is already present.

## Files to retire

Do not delete historical migrations just because they are no longer needed on the current local database: they document how older installations reach the current schema. Instead, keep them as migration history and clearly separate them from files intended for routine local verification.

The filenames currently have two numbering collisions: `010_repair_tenant_foreign_keys.sql` and `010_service_activation_hotspot_runtime.sql`, and `034_network_monitoring.sql` and `034_pppoe_server_ip_pool.sql`. These are different migrations, not duplicate files. A migration registry should define their order before renaming anything.

## Clean XAMPP install vs existing XAMPP database

A clean installation needs a canonical baseline schema dump followed by an ordered migration runner. The current `database/` folder begins with legacy audit/migration files and does not contain a clearly identified `001`/ `002` baseline schema in the directory listing. Therefore, do not treat the folder as a single clean-install import package yet.

For the existing `billing_system` database:
1. Back up the database in phpMyAdmin.
2. Run `tenant_integrity_verification.sql` for read-only checks.
3. Apply only migrations explicitly identified as missing after comparing the database schema to the migration registry.
4. Never re-run migration 010 tenant repair on the already repaired local database.

## Consolidation rule

Keep migration files atomic and ordered; consolidate the *instructions and runner*, not all DDL into one enormous SQL file. A single flattened SQL dump should be generated from a known-good schema snapshot, not assembled by concatenating migrations that may add the same columns or constraints.
