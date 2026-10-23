# Flexihub Billing System — Module Completeness Register

**Purpose:** Track whether each business module is present, implemented, integrated, tenant-safe, and tested. This is a working audit register, not a claim that listed routes have been functionally verified.

**Audit baseline:** Repository `main`, commit `39ea02d487fe7241cb78b7dcbff49d561f7f04c0` (2026-10-10). The file inventory confirms route/file presence only. A module is not marked complete until its workflows, permissions, tenant boundaries, schema prerequisites, failure handling, and tests have been verified.

## Status definitions

- **Present — verify:** Relevant source files exist; behavior has not yet passed an end-to-end audit.
- **Partial / risk identified:** Related code exists, but known design, integration, or deployment concerns remain.
- **Not evidenced:** The current file inventory does not establish that the capability exists. Search the full repository and schema before declaring it missing.
- **Pass criteria:** Explicit checks that must pass before changing a module to complete.

## 1. Module inventory

| Area | Existing source evidence | Initial status | Required verification / likely gaps |
|---|---|---|---|
| Authentication and sessions | `auth/login.php`, `auth/logout.php`, `sessions/index.php`, `includes/auth.php` | Present — verify | Password hashing, session rotation, timeout, CSRF, brute-force controls, logout invalidation, role checks on direct URLs |
| Dashboard and tenant analytics | `dashboard/index.php`, `dashboard/tenant_analytics.php` | Present — verify | Tenant-scoped metrics, accurate date ranges, empty/error states, query efficiency |
| Tenant onboarding and management | `tenants/index.php`, `platform/index.php` | Present — verify | Provisioning, setup defaults, suspension/reactivation, closure, tenant limits, safe tenant context |
| Users, roles, permissions, staff | `users/index.php`, `roles/index.php`, `staffs/*` | Present — verify | Permission matrix for every route/action, deny-by-default, tenant/host boundaries, staff lifecycle |
| Customers | `customers/*` | Present — verify | Create/edit/view, search, duplicates, status changes, tenant scoping, customer deletion policy, audit trail |
| Plans and internet accounts | `internet_plans/*`, `internet_accounts/*` | Present — verify | Plan changes, activation dates, price history, account/customer linkage, safe deactivation |
| Subscriptions and service lifecycle | `subscriptions/*`, `includes/billing_workflow.php`, service workers | Present — verify | New connection, renewal, upgrade/downgrade, expiry, suspension/restoration, idempotent worker retries |
| Routers and MikroTik integration | `routers/*`, `includes/mikrotik_api.php`, `settings/mikrotik.php` | Present — verify | Credential handling, connection test, timeouts, retries, router state reconciliation, multi-router isolation |
| PPPoE | `pppoe/*`, `pppoe_accounts/*`, `pppoe_servers/*` | Present — verify | Provision/deprovision, profile assignment, IP pool validation, online sessions, disconnect, router sync failures |
| Hotspot and captive portal | `hotspot/*`, `includes/hotspot_workflow.php`, `settings/captive_portal.php` | Present — verify | Voucher sale and redemption, portal tenant branding, expiry, renewals, session reconciliation, concurrent sales |
| IP pools and network | `ip_pools/*`, `network/*`, `network_sites/*` | Present — verify | Overlap/conflict detection, allocation rules, site/ router relationships, monitoring freshness, outage history |
| OLT/ONU / access equipment | `network/olt_onu.php`, `maintenance/cron_olt_onu_worker.php` | Present — verify | Device discovery/polling, online/offline states, failed polls, credentials, event history; confirm equipment support is in scope |
| Billing and invoices | `billing/*`, `invoices/*`, `includes/billing_workflow.php` | Present — verify | Invoice generation, line items, tax/discount, due dates, duplicate prevention, adjustments, voids, audit trail |
| Payments and M-Pesa | `payments/*`, `includes/payment_gateway.php`, `includes/platform_mpesa.php` | Present — verify | STK request/callback validation, idempotency, amount/account matching, late callbacks, reversal/refund handling, reconciliation |
| Receipts, transactions, wallet, revenue | `receipts/*`, `transactions/*`, `wallet/*`, `revenue/*` | Present — verify | Ledger integrity, payment allocation, partial payments, wallet balance invariants, immutable audit trail, reconciliation |
| Debtors and statements | `billing/outstanding.php`, `reports/aged_receivables.php`, `reports/customer_statement.php` | Present — verify | Reconciliation to invoices/payments, aging logic, customer statement correctness, date filters |
| Expenses and platform costs | `expenses/index.php`, `platform/revenue/*` | Present — verify | Expense categories, approvals, attachments, date/tenant filters, reconciliation and permissions |
| Reporting and exports | `reports/*` | Present — verify | Report totals match ledgers, tenant scope, CSV/export safety, date boundaries, authorization |
| Communication and notifications | `communication/*`, `settings/notifications.php`, `includes/sms_gateway.php`, `maintenance/cron_sms_worker.php` | Present — verify | Provider failures, retries, delivery states, templates, consent/preferences, secrets, tenant-level configuration |
| Support and service operations | `support/index.php`, `operations/*`, `system_events/index.php` | Partial — core support workflow and operations dashboard implemented; full workflow audit remains | Support tickets/replies/status/assignment, outage linking, work orders, SLA tracking, operational event visibility; support migration 035 must be applied before using ticket records |
| SaaS platform billing | `platform/billing.php`, `platform/plans/*`, `platform/subscriptions/*`, `platform/wallets/*`, `platform/payments/*` | Present — verify | Tenant subscription lifecycle, plan limits, platform ledger, arrears, grace period, suspend/restore and reconciliation |
| Agent/reseller operations | `operations/agent_sales.php` | Partial / verify scope | Commission rules, agent hierarchy, settlement, reversal, fraud prevention, tenant ownership |
| AI assistant and usage | `ai/*` | Present — verify | Tenant data isolation, prompt/data access control, usage limits, secrets, failure behavior, auditability |
| Audit and system events | `audit_logs/index.php`, `system_events/index.php` | Present — verify | Append-only behavior, actor/action/tenant capture, sensitive-data redaction, retention and access controls |
| Settings and customer profile | `settings/*`, `profile/index.php` | Present — verify | Tenant-vs-platform settings separation, secret storage, validation, branding consistency |
| Scheduled jobs and queues | `maintenance/*` | Present — verify | Cron documentation, locking, duplicate-run safety, bounded retries, dead-letter visibility, monitoring |
| Installation and upgrades | `database/*`, `config/database.php` | Partial / risk identified | Canonical clean-install schema, ordered migration registry/runner, environment configuration, upgrade tests and rollback plan |
| Automated quality checks | `.github/workflows/php-syntax.yml`, `test/tenant_context.php` | Partial / risk identified | Syntax lint is not business testing; add unit/integration tests, database-backed tenant tests and CI service setup |
| Backup, restore and deployment | No verified end-to-end evidence from the inventory | Not evidenced | Documented backup schedule, restore drill, production secrets, HTTPS, logging/alerts, release and rollback procedures |

## 2. Initial priority queue

### P0 — Verify before trusting live customer or money workflows
1. Tenant isolation and route-level authorization across all modules.
2. Payment callback authenticity, idempotency, duplicate/late callback behavior and reconciliation.
3. Invoice/payment/receipt/wallet ledger invariants and prevention of duplicate charges.
4. Service activation/suspension/renewal consistency between database and router.
5. Cron/queue idempotency, failure visibility and retry recovery.
6. Database migration safety and a tested clean-install path.

### P1 — Complete operational ISP workflows
1. One reliable customer-to-order-to-installation-to-active-service lifecycle.
2. PPPoE and hotspot provisioning, expiry, renewal and disconnect/reconnect.
3. IP allocation and conflict detection, router synchronization and online-session accuracy.
4. Customer support tickets, outage communication and field/installation work orders.
5. Customer statements, aged receivables and financial reports reconciled against transaction records.
6. Tenant onboarding, SaaS subscription limits, billing, arrears and controlled suspension/restoration.

### P2 — Scale, usability and commercial maturity
1. Agent/reseller commissions and settlement if required by the business model.
2. Self-service customer portal and richer usage/history views.
3. SLA dashboards, network availability history and actionable alerts.
4. Bulk import/export with validation and dry-run support.
5. Operational dashboards, report scheduling and safe audit-log exports.

## 3. Audit checklist for each module

For every module, record evidence for all of the following before marking it complete:

- [ ] Routes and sidebar links are correct; no orphaned or misleading menu entries.
- [ ] Create/read/update/delete actions work where appropriate; destructive actions have safeguards.
- [ ] View/create/edit/approve/delete permissions are enforced server-side, not just hidden in the UI.
- [ ] Every tenant-owned query and mutation is scoped to the authenticated tenant; cross-tenant negative tests pass.
- [ ] Database constraints, indexes, foreign keys and migration prerequisites are documented.
- [ ] Inputs are validated; SQL uses prepared statements; output is escaped; state-changing requests use CSRF protection.
- [ ] External integration failures are visible and recoverable; retries cannot duplicate financial or service actions.
- [ ] Audit events record important changes without exposing secrets or payment credentials.
- [ ] Empty, loading, validation, permission-denied and integration-error states are usable.
- [ ] Automated tests cover success, invalid input, permission denial, tenant isolation, duplicate requests and failure recovery.
- [ ] UI follows the existing Flexihub black/deep-navy and blue command-center styling.
- [ ] Evidence (test result, commit/PR, and known limitations) is linked here.

## 4. Database safety notes

The separate database migration audit identifies numbering collisions, non-idempotent DDL, dependency ordering risks, and tenant-ID type inconsistencies. Preserve historical migrations and the existing `billing_system` database. Do not batch-import every SQL file or apply a schema change to the working database without a backup, a reviewed migration and a disposable-database test.

Reference: [Database README](../database/README.md) and [Migration Dependency Audit](../database/MIGRATION_DEPENDENCY_AUDIT.md).

## 5. Next execution sequence

1. Perform a source-level audit of authentication, permissions and tenant scoping first.
2. Trace one complete money path: invoice → payment initiation/callback → transaction allocation → receipt → ledger/report.
3. Trace one complete service path: customer/account → subscription → router provisioning → online state → expiry/suspension → renewal/restoration.
4. Convert verified defects into small fixes with regression tests; do not add duplicate modules where the capability already exists.
5. Add CI checks and database-backed tests, then work through P1/P2.
6. Update this register with links to evidence and commits as work is completed.

## 6. Recent implementation log

- **Support ticket workflow — PR #118 merged** (`c7b5e98`): replaced the support placeholder with tenant ticket creation, conversation replies, host-side status/resolution management, and tenant-scoped reads/writes. Added `database/035_support_ticket_workflow.sql` and a tenant Support Centre sidebar entry. PHP lint passed. The migration has not been applied to the local database and the workflow has not been runtime-tested.
- **Operations command centre — PR #119 merged** (`d2b7443`): replaced the generic Operations landing page with tenant-scoped sales/commission and advert summaries plus links to key operational modules. PHP lint passed. Database/runtime behavior remains unverified.
- These are implementation milestones, **not completion sign-offs**. Mark either module complete only after the module checklist, permissions, tenant-boundary checks, migration prerequisites, and later end-to-end tests have passed.

- **Financial workflow hardening — PRs #121–#125 merged:** expanded tenant-aware role permissions; payment artifact writes now fail closed; invoice-paid calculations report query/schema failures; subscription, invoice, and payment listings distinguish database errors from empty data. These are source-level improvements, not local database sign-off.
- **Receipt and balance handling — PRs #126–#129 merged:** tenant-scoped printable receipt detail view; safe handling for invoice-balance errors in manual payments and overdue suspension; explicit query error states for Outstanding and Aged Receivables; payment history resolves the customer through the linked tenant-matched invoice. Local XAMPP/runtime checks remain pending.
- **Subscription and recurring billing safety — PRs #130–#134 merged:** payment-driven subscription renewal deduplication now fails closed on lookup errors; overdue SMS template selection reads invoice status; recurring invoice generation stops on query failures and checks duplicate-period lookups; subscription expiry does not expire an account when active-subscription verification fails; account/subscription expiry candidate queries handle database errors explicitly.
- **Deployment constraint still open:** the database directory remains historical incremental migrations, not a validated clean-install package. Keep the existing database intact; migration ordering, schema prerequisites, a clean-install baseline, and disposable-database upgrade verification remain release blockers until completed.
 
**Important:** This register is an initial inventory based on repository files and existing database audit notes. It intentionally does not claim that any module is functionally complete merely because a PHP file exists.
