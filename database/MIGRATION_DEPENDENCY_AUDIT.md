# Database Migration Dependency Audit

**Scope:** Static review of SQL files currently under `database/` on the repository's `main` branch. This is a source review, not proof that every migration has been run successfully. No production or local database was changed by this audit.

## Current verified runtime baseline

The local `billing_system` database was audited on 2026-10-09. The read-only tenant integrity script returned:
- 24 tables with foreign keys to `tenants.id`.
- All 24 use `ON DELETE RESTRICT` and `ON UPDATE CASCADE`.
- 0 orphaned `tenant_id` values across the 24 audited tables.

This validates tenant references in the current database, not a fresh-install sequence or application workflows.

## Findings requiring attention

### Critical: migration numbering collisions
- `010_repair_tenant_foreign_keys.sql` and `010_service_activation_hotspot_runtime.sql` share the same prefix but serve different purposes.
- `034_network_monitoring.sql` and `034_pppoe_server_ip_pool.sql` share the same prefix.
- A runner must not assume the numeric prefix alone defines a unique migration identity or total order.

### High: tenant ID type inconsistency in the M-Pesa migration
`013_mpesa_hotspot_runtime.sql` declares `tenant_id INT UNSIGNED` in `payment_gateways`, `payment_gateway_transactions`, and nullable `payment_gateway_events`, while the repaired canonical tenant key is `tenants.id BIGINT UNSIGNED`. These columns should be reconciled in a deliberate migration before adding/depending on tenant foreign keys. Do not run an ad hoc type alteration against an existing database without checking indexes, constraints, and a backup.

### High: non-idempotent DDL can break reruns
- `012_hotspot_router_runtime.sql` uses plain `ALTER TABLE ... ADD COLUMN` and `CREATE INDEX`; a second run will fail after the first succeeds.
- `013_mpesa_hotspot_runtime.sql` adds multiple `hotspot_sales` columns and indexes without existence checks; reruns can fail part-way through.
- `024_platform_mpesa_gateway_link.sql` and `025_platform_payment_stk_fields.sql` also use non-idempotent ALTER statements.
- Other migrations use `IF NOT EXISTS`, but compatibility must still be verified against the minimum supported MySQL/MariaDB version.

### High: migration prerequisites are not self-contained
- `010_service_activation_hotspot_runtime.sql` inserts into `tenant_wallets`, and therefore requires that table to exist before execution.
- `012_hotspot_router_runtime.sql` assumes `hotspot_packages` exists.
- `013_mpesa_hotspot_runtime.sql` assumes `hotspot_sales` exists and changes it.
- `017_hotspot_finalization_queue.sql` references `hotspot_sales(id)` but does not explicitly specify the storage engine on that table or document the expected key type.
- `018_service_subscription_lineage.sql` assumes `service_subscriptions` exists.
- The `database/` directory contains incremental changes but is not, by itself, a validated fresh-install baseline.

### Tenant isolation needs explicit review for newly introduced tables
Several tables created by incremental migrations have a `tenant_id` column but do not declare a foreign key to `tenants(id)` in the same migration. Examples include `router_service_authorizations`, `router_sync_logs`, the platform SaaS billing tables, and the feature-gap tables introduced in `027_smartisp_feature_gap_modules.sql`. Some relationships may intentionally be application-enforced, but the intended policy should be documented and checked table-by-table. Do not blindly add constraints without validating existing data and cross-tenant relationships.

### Duplicate feature definitions and misleading phase labels
- `failed_mpesa_transactions` is created in both `027_smartisp_feature_gap_modules.sql` and `028_mpesa_failure_reconciliation.sql`. `IF NOT EXISTS` prevents a duplicate-table error but does not apply columns that are only present in the second definition; later changes such as `031_failed_mpesa_reconciliation.sql` currently add fields incrementally.
- `032_sms_retry_queue.sql` comments call it Phase 35, `033_olt_onu_polling.sql` calls it Phase 36, and `034_pppoe_server_ip_pool.sql` calls it Phase 37. File prefixes and phase labels have drifted and should not be used as the sole migration ordering mechanism.

## Safe next steps

1. Preserve the current local database and take a backup before any schema changes.
2. Record each migration by a stable identifier (filename/path plus checksum), prerequisite list, and whether it is safe to rerun.
3. Create a canonical fresh-install schema from a known-good, reviewed schema snapshot; do not concatenate the historical migrations blindly.
4. Reconcile the `013` tenant ID types and design idempotent follow-up migrations for non-idempotent DDL.
5. Audit every tenant-aware table for tenant foreign key policy and cross-tenant composite relationships.
6. Test the ordered migration runner on a disposable empty database and a copy of the current schema before touching the working database.

## Explicit exclusions

This audit does not delete, rename, reorder, or execute migrations. It does not claim that the fresh-install path is ready. Historical migrations should remain available until a tested replacement path exists.
