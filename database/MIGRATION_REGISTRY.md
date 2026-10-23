# Migration Registry and Release Gate

**Status: NOT APPROVED FOR AUTOMATED EXECUTION.** This registry gives each historical migration a stable identity and a review queue. It is not a claim that the migrations can safely run in this order. Do not run this registry against the existing `billing_system` database.

## Rules

1. Identify a migration by its full filename (and, for release records, its content hash), never by numeric prefix alone.
2. Keep historical SQL files unchanged until a replacement sequence has been proven against disposable databases.
3. The order below is a **review order only**. Dependency, schema preconditions, rerun safety, supported MySQL/MariaDB versions, and data impact must be verified before a migration is marked executable.
4. Read-only audit scripts are not migrations: `003_tenant_isolation_audit.sql` and `tenant_integrity_verification.sql`.
5. `010_repair_tenant_foreign_keys.sql` is a one-time legacy repair. It must not be rerun on the already repaired local database.
6. No canonical clean-install baseline has been validated. A fresh install and automated upgrade remain blocked until a baseline is generated from a reviewed schema snapshot and tested.

## Stable identities and review queue

The logical IDs below resolve duplicate filename prefixes. They do **not** authorize execution.

| Logical ID | File | Review status / known constraint |
|---|---|---|
| LEGACY-004 | `004_tenant_isolation.sql` | Legacy tenant columns/indexes/FKs; requires schema and target-tenant preflight |
| LEGACY-005 | `005_roles_permissions.sql` | Incremental; verify current schema and rerun safety |
| LEGACY-006 | `006_access_control.sql` | Incremental; verify prerequisites and rerun safety |
| LEGACY-007 | `007_module_foundation.sql` | Incremental; verify prerequisites and rerun safety |
| LEGACY-008 | `008_operational_workflows.sql` | Incremental; verify prerequisites and rerun safety |
| LEGACY-009 | `009_legacy_tenant_bootstrap.sql` | Legacy bootstrap only; review users and tenant assignments first |
| LEGACY-010A | `010_repair_tenant_foreign_keys.sql` | **Manual/one-time only**; do not rerun on repaired local DB |
| FEATURE-010B | `010_service_activation_hotspot_runtime.sql` | Requires `tenant_wallets`; inspect current schema/data |
| FEATURE-011 | `011_pppoe_router_runtime.sql` | Verify dependencies and current schema |
| FEATURE-012 | `012_hotspot_router_runtime.sql` | Assumes `hotspot_packages`; plain ADD COLUMN/CREATE INDEX can fail on rerun |
| FEATURE-013 | `013_mpesa_hotspot_runtime.sql` | Assumes `hotspot_sales`; tenant_id INT UNSIGNED conflicts with BIGINT UNSIGNED tenant key; reconcile deliberately |
| FEATURE-014 | `014_hotspot_captive_portal.sql` | Verify dependencies and current schema |
| FEATURE-015 | `015_hotspot_sale_credentials.sql` | Verify dependencies and current schema |
| FEATURE-016 | `016_hotspot_renewals.sql` | Verify dependencies and current schema |
| FEATURE-017 | `017_hotspot_finalization_queue.sql` | References `hotspot_sales(id)`; verify engine and key type |
| FEATURE-018 | `018_service_subscription_lineage.sql` | Assumes `service_subscriptions` exists |
| FEATURE-019 | `019_internet_account_connection_type.sql` | Verify dependencies and current schema |
| FEATURE-020 | `020_automated_recurring_billing.sql` | Verify dependencies, unique constraints, and rerun safety |
| FEATURE-021 | `021_overdue_suspension.sql` | Verify dependencies and current schema |
| FEATURE-022 | `022_notifications.sql` | Verify dependencies and current schema |
| FEATURE-023 | `023_flexihub_saas_subscriptions.sql` | Audit tenant scope/FKs and dependencies |
| FEATURE-024 | `024_platform_mpesa_gateway_link.sql` | Non-idempotent ALTER statements require schema preflight |
| FEATURE-025 | `025_platform_payment_gateway.sql` | Non-idempotent ALTER statements require schema preflight |
| FEATURE-026 | `026_platform_payment_stk_fields.sql` | Verify prerequisites and rerun safety |
| FEATURE-027 | `027_smartisp_feature_gap_modules.sql` | Audit new tables, tenant FKs, and overlap with later migrations |
| FEATURE-028 | `028_mpesa_failure_reconciliation.sql` | Overlaps creation of `failed_mpesa_transactions` in 027; verify later field migrations |
| FEATURE-029 | `029_olt_onu_operational_metadata.sql` | Verify dependencies and current schema |
| FEATURE-030 | `030_sms_gateway_delivery.sql` | Verify dependencies and current schema |
| FEATURE-031 | `031_failed_mpesa_reconciliation.sql` | Incremental reconciliation fields; verify prerequisites |
| FEATURE-032 | `032_sms_retry_queue.sql` | Verify dependencies; embedded phase label has drifted |
| FEATURE-033 | `033_olt_onu_polling.sql` | Verify dependencies; embedded phase label has drifted |
| FEATURE-034A | `034_network_monitoring.sql` | Duplicate prefix; verify dependencies before assigning execution order |
| FEATURE-034B | `034_pppoe_server_ip_pool.sql` | Duplicate prefix; verify dependencies before assigning execution order |
| FEATURE-035 | `035_support_ticket_workflow.sql` | Required before using support-ticket records; not confirmed applied to local DB |
| FEATURE-036 | `036_mpesa_receipt_claims.sql` | One-time backfill; run read-only duplicate/length preflight first; paired SaaS ledger mirrors are deduplicated; true duplicate receipts must be reconciled |

## Promotion gates for each migration

A migration can only be marked **approved** after the record contains:

- [ ] Preconditions and dependent migrations confirmed from SQL/source review.
- [ ] Existing-schema detection and duplicate/already-applied behavior documented.
- [ ] Tenant ID types, foreign keys, indexes, and cross-tenant relationship policy checked.
- [ ] Data backfill and destructive-change impact reviewed.
- [ ] Supported MySQL/MariaDB compatibility checked.
- [ ] Backup and recovery/rollback procedure documented (DDL rollback may require restore).
- [ ] Successfully applied to a disposable empty database from the proposed baseline.
- [ ] Successfully upgraded a disposable copy of the current schema.
- [ ] Post-migration integrity checks and application regression checks passed.
- [ ] Applied migration identity and checksum recorded in a migration-history table.

## Clean install and upgrade status

- **Clean-install baseline:** NOT ESTABLISHED.
- **Executable ordered migration runner:** NOT ESTABLISHED.
- **M-Pesa receipt claim migration:** prepared as FEATURE-036; preflight and existing duplicate reconciliation are mandatory before applying.
- **Automated upgrade from historical schemas:** NOT VERIFIED.
- **Existing local XAMPP database:** preserve it; compare schema before considering any migration.
- **Release decision:** database installation/upgrade is a deployment blocker.

Related: [Database SQL Guide](README.md), [Migration Dependency Audit](MIGRATION_DEPENDENCY_AUDIT.md), and [read-only preflight](migration_preflight.php).
