# Database Export Schema Gap Assessment

**Assessment date:** 2026-10-27  
**Source reviewed:** user-confirmed latest working database export, `billing_system (1).sql`  
**Source server metadata:** MariaDB 10.4.32; PHP 8.0.30  
**Scope:** Static inspection of the supplied SQL file only. The user confirmed this is the latest working database snapshot. No SQL was executed and no live XAMPP database was queried.

## What the supplied file establishes

- It contains 30 `CREATE TABLE` definitions and 64 foreign-key constraint definitions.
- It includes both schema and data inserts, so it is not a schema-only export.
- `tenants.id` is defined as `BIGINT UNSIGNED`.
- `payments` contains `id`, `tenant_id`, `invoice_id`, `amount`, `payment_date`, and `reference_number` (not a `reference` column in the shown schema).
- `receipts` contains `id`, `tenant_id`, `payment_id`, `amount`, and `reference_number`, but no `invoice_id` column.
- `account_transactions` contains `id`, `tenant_id`, `amount`, `reference_type`, and `reference_id`, but no `payment_id` or `invoice_id` columns.
- The export does not define `payment_gateways`, `payment_gateway_transactions`, `payment_gateway_events`, `hotspot_portal_settings`, `hotspot_sales`, `service_subscriptions`, `platform_payment_gateways`, `platform_invoices`, `platform_plans`, `tenant_platform_subscriptions`, `platform_payments`, `platform_settings`, or `mpesa_receipt_claims`.
- Other migration-owned tables absent from the export include `router_service_authorizations`, `router_sync_logs`, `hotspot_finalization_queue`, `notification_templates`, `notification_queue`, `olt_devices`, `onu_devices`, `network_map_nodes`, `network_map_links`, `hotspot_vouchers`, `agent_sales`, `adverts`, `customer_chats`, `customer_chat_messages`, `sms_gateways`, `failed_mpesa_transactions`, `tenant_theme_settings`, `sms_messages`, `support_tickets`, and `support_ticket_messages`.

## Consequences for repository preflight scripts

1. `database/payment_ledger_reconciliation_preflight.sql` cannot be run unchanged against this exported schema: its documented column assumptions for payments, receipts, and account_transactions do not match exactly. Adapt it only after reviewing the intended runtime schema and source migration history.
2. `database/mpesa_receipt_integrity_preflight.sql` and `database/036_mpesa_receipt_claims.sql` depend on gateway/platform payment tables that are absent from this export. Do not run them against a database with this schema until the missing tables are intentionally introduced and their prerequisites verified.
3. Because the user has confirmed this is the latest working database export, treat these absences as current-snapshot schema gaps. The export still does not prove the live XAMPP instance is byte-for-byte identical because no live connection was made.
4. The export contains data. Do not publish it or use it as a clean-install artifact. Prepare a separate schema-only export from the confirmed source database for baseline work.

## Migration blockers found in the current snapshot

- `013_mpesa_hotspot_runtime.sql` creates gateway tables with `tenant_id INT UNSIGNED`, while the current `tenants.id` is `BIGINT UNSIGNED`. It also alters `hotspot_sales` without guards, but that table is absent from the export. Do not apply this migration unchanged.
- `023_flexihub_saas_subscriptions.sql` creates platform subscription tables and then writes to `platform_settings`, which is absent from the export. Applying it unchanged may leave partially created tables before failing.
- `024_platform_mpesa_gateway_link.sql`, `026_platform_payment_stk_fields.sql`, `030_sms_gateway_delivery.sql`, `031_failed_mpesa_reconciliation.sql`, `032_sms_retry_queue.sql`, and `033_olt_onu_polling.sql` alter prerequisite tables and must not be run until their exact prerequisites and already-applied columns are checked.
- `036_mpesa_receipt_claims.sql` requires gateway/platform payment tables and a duplicate-receipt review; those source tables are absent from this export.
- Some modules' runtime code expects tables not present in this snapshot, so passing PHP lint alone cannot make those modules operational.

## Safe next steps

- Run the expanded read-only `database/payment_schema_compatibility_preflight.sql` against a backup/disposable copy and collect `SHOW CREATE TABLE` output for the payment and platform tables that do exist.
- Build a reviewed upgrade plan from this exact snapshot, correcting tenant-key types and separating safe table creation from non-idempotent alterations. Do not batch-import historical migrations.
- Reconcile historical financial rows before adding/rekeying ledger fields or applying migration 036. Do not delete or auto-attach financial artifacts based only on a preflight report.
- Build and validate a clean-install baseline separately from the current working database, then test the upgrade path from a restored backup.

## Limitations

This assessment is based solely on the supplied SQL file's text. It does not prove live database state, row-level integrity, migration success, application runtime correctness, or production readiness.
