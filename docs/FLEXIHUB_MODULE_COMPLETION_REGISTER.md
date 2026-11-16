# Flexihub Module Completion Register

**Working branch:** `feature/module-completion-ui-consistency`  
**Baseline:** `ui/global-style-polish`  
**Rule:** A menu item, database table, or unfinished page does not count as a completed module. Mark a capability complete only after its schema, tenant-scoped workflow, permissions, UI, and practical tests have been checked.

## Dashboard — implementation complete; runtime sign-off deferred

- [x] Separate platform-host and tenant ISP dashboard views.
- [x] Tenant-scoped customer, revenue, service, router, session, payment and outstanding-balance summaries.
- [x] Host overview for tenants, platform collections, wallet balances and recent platform activity.
- [x] ISP operational snapshot with router status, service lifecycle, expiring services and quick actions.
- [x] Tenant payment analytics for hourly collections, seven-day collections, session mix and service lifecycle.
- [x] PPPoE online count is now queried by PPPoE session type rather than inferred by subtracting hotspot sessions from all online sessions.
- [x] Dashboard analytics styling aligned with the shared dark navy/blue command-centre theme.
- [x] Status and session-type filters normalized to avoid case-sensitive status/session mismatches.
- [x] PPPoE analytics caption corrected to describe the directly queried PPPoE session count.
- [ ] Final testing phase: run against the user's current XAMPP database and verify every metric, status label, link and empty state.
- [ ] Final testing phase: review desktop, tablet and mobile rendering in the browser before signing off the dashboard.

## Cross-cutting RBAC — implementation in progress

- [x] Tenant role editor and user assignment use the canonical `access_*` role tables.
- [x] Tenant role resolution and login session role loading now read canonical access-role assignments; host roles remain isolated from tenant RBAC.
- [x] User access screen supports tenant-scoped role assignment and revocation, while preventing removal of a system role or a user's final assigned role.
- [x] Added idempotent consolidation migration to create canonical access tables and copy compatible legacy tenant roles, module permissions and user assignments without deleting legacy data.
- [ ] Apply migration 038 against the existing database after schema preflight; verify role/permission and assignment counts before/after.
- [ ] Complete role revocation, verify platform-host role administration and remove remaining legacy tenant authorization fallback after the canonical migration is confirmed.

## Operations & Field Service — implementation in progress

- [x] Added tenant-scoped field job, update-history and attachment metadata schema.
- [x] Added job scheduling for installations, repairs, maintenance, surveys, relocations and disconnections.
- [x] Added tenant-owned customer/account validation, technician assignment from active staff, priority and status tracking.
- [x] Added status history, resolution notes and customer sign-off capture.
- [ ] Add secure photo/document upload and technician performance/SLA reporting.
- [ ] Apply migration 039 and verify job creation, assignment, history and customer/account isolation during final testing.

## Resellers — implementation in progress

- [x] Added tenant-scoped reseller directory and invoice-linked commission ledger.
- [x] Commission calculation uses recorded invoice collections and snapshots the reseller's commission rate.
- [x] Added approval, payment and reversal status transitions with tenant scoping and duplicate invoice/reseller protection.
- [ ] Add reseller customer attribution/portal access and automated commission reconciliation.
- [ ] Apply migration 041 and verify commissions against actual payment ledger records during final testing.

## Inventory — implementation in progress

- [x] Added tenant-scoped inventory item, storage location and stock movement ledger schema.
- [x] Added item/location creation, receipt/issue/adjustment posting, tenant ownership checks and prevention of negative stock issues.
- [x] Added on-hand balances calculated from the movement ledger and reorder-level indicators.
- [ ] Apply migration 040 and verify stock ledger balances and concurrent issue protection during final testing.
- [ ] Add supplier/purchase order workflow, transfers between locations and secure attachment support.

## PPPoE & Network — implementation in progress

- [x] PPPoE profile page now performs read-only profile discovery from tenant-assigned active MikroTik routers instead of showing a generic internet-plan placeholder.
- [x] ONU-to-service-account assignment now verifies that the selected account belongs to the selected customer and tenant.
- [ ] Complete IP address allocation/release, RouterOS sync/retry and topology/outage event workflows.
- [ ] Final testing phase: test read-only router polling against the isolated Flexihub test router; do not enable RouterOS writes against SmartISP.

## Hotspot — implementation in progress

- [x] Tenant-scoped hotspot plan listing now handles router-name schema variants and orders by the package primary key.
- [x] Hotspot plan creation now uses the correct parameter types and validates tenant-owned active router assignment.
- [x] Hotspot session fallback now filters the shared active-session table to hotspot sessions only.
- [x] Hotspot workspace shortcuts include voucher and public payment-status workflows.
- [x] Public captive portal now follows the dark navy/blue brand styling with mobile-friendly payment and access-status forms.
- [x] Hotspot package editing now supports tenant-scoped router reassignment and validates active routers; legacy Add Plan route forwards to the router-aware workflow.
- [ ] Verify captive portal purchase, M-Pesa callback, voucher/code activation, expiry and retry queue end to end.
- [ ] Final testing phase: confirm the router association migration and test only against the isolated Flexihub test router.

## Customers — implementation in progress

- [x] Tenant-scoped customer directory, search, status filters, summary metrics and customer account view.
- [x] Customer creation with tenant ownership, input validation, generated customer number and CSRF protection.
- [x] Tenant-scoped customer edit workflow with CSRF protection, validated status and contact fields, and permission enforcement.
- [x] Customer detail view links services, invoices, subscriptions and payment history using tenant-scoped lookups.
- [x] Status shortcuts now pass their selected status into the directory; status comparisons are case-normalized.
- [x] Create-customer shortcuts are hidden when the current user lacks customer-create permission.
- [x] Added tenant-scoped customer groups and group assignment schema migration (`database/037_customer_groups.sql`) plus group management page.
- [ ] Apply and verify the customer groups migration during the final database testing phase; group assignment controls depend on it.
- [x] Added customer self-service portal with one-time, hashed 48-hour invitations, password activation, tenant-scoped login and customer-only session state.
- [x] Portal dashboard displays only the signed-in customer's services, invoices, payments and subscription history.
- [ ] Configure secure external HTTPS/tenant URL delivery and verify portal invitation/password reset flows during final testing.
- [ ] Add duplicate contact handling and customer self-service portal.

## Billing — implementation in progress

- [x] Tenant-scoped invoice creation and listing with actual payment-ledger balance calculations.
- [x] Invoice creation can associate an internet account belonging to the selected tenant customer.
- [x] Outstanding/debtor view calculates balances from the tenant-scoped payment ledger.
- [x] Invoice list provides a permission-gated Record Payment path, preselecting the invoice and linked service account.
- [x] Renewal workflow now prepares a customer- and account-linked invoice with the current plan price prefilled for review; it no longer links to a payment form without an invoice.
- [x] Expiring/renewal lists display customer names from the customer name fields rather than assuming a legacy `name` column.
- [x] Recurring invoice generation runs through the tenant-aware CLI service worker with account/service-period duplicate prevention.
- [x] Overdue invoices observe plan grace periods and queue service suspension through the isolated activation workflow.
- [x] Manual payment workflow validates invoice ownership, serializes payment posting, prevents overpayment, supports partial payments and defers renewal until full settlement.
- [ ] Add tax configuration/calculation and credit notes/refunds with approval and audit controls.
- [ ] Verify gateway reconciliation, unmatched/duplicate receipts and failed-payment recovery end to end.
- [ ] Final testing phase: verify invoice/payment balances against the existing database and test normal, duplicate and failed payment paths.
- [ ] Final testing phase: verify tenant boundaries, customer creation, filtering and linked account/ledger totals.

## Cross-cutting foundation — do first

| Capability | Required outcome | Current assessment |
|---|---|---|
| Unified roles and permissions | One authoritative role assignment and permission model for tenant users; platform-host scope separated; no conflicting legacy/new RBAC paths | Canonical `access_roles`, `access_role_permissions`, and `access_user_roles` implemented in application code; migration 038 consolidates compatible legacy tenant roles/assignments; apply and verify during final database testing |
| Tenant isolation | Every tenant-owned read/write is scoped to the authenticated tenant; host-only operations are explicitly guarded | Partially present; audit required |
| Audit trail | Record sensitive access, financial changes, approvals, and network actions | Not verified |
| Shared UI system | All authenticated pages use the common layout and design tokens; standalone pages have an intentional matching variant | Existing global CSS; consistency audit required |

## Workstream 1 — Network operations

- [ ] IP address pool lifecycle: create/edit/disable, allocation, release, overlap/conflict detection, exhaustion warnings.
  - Existing verified foundation: tenant-scoped pool CRUD, IPv4/CIDR and range validation, overlap checks, active PPPoE-server assignment protection, and pool-to-server association.
  - Latest improvement: pool list now shows tenant-scoped assigned/active PPPoE server counts when the association schema is present, and hides create/edit actions without the corresponding permission.
  - Still missing: live address allocation/release, in-use address accounting, pool exhaustion thresholds/alerts, and end-to-end RouterOS synchronization tests.
- [ ] Router provisioning synchronisation: desired vs actual state, sync status, safe retry and error history.
  - Latest addition: `routers/sync.php` provides a tenant-scoped, read-only comparison of Flexihub PPPoE IP pool ranges and PPP profiles against RouterOS, linked from each router row. It uses the existing deterministic `fh_t{tenant}_pool{pool}` and `fh_t{tenant}_pppoe{server}` names and performs only RouterOS print/identity reads.
  - Still missing: durable sync state, command queue, retries, error history, automated scheduled reconciliation, and safe write-path tests. The RouterOS write lock remains disabled.
- [ ] Session and usage tracking: active sessions, accounting records, usage limits, disconnect/reconciliation.
- [ ] Network topology and site map: routers, links, sites, status and outage history.
- [ ] OLT/ONU inventory and status where supported by actual device integrations.
- [ ] Durable router command queue with idempotency, retry/backoff, audit history and failure recovery.
- [ ] Read-only discovery remains safe by default; write operations require explicit per-environment enablement and isolated test hardware.

## Workstream 2 — Billing and financial control

- [ ] Recurring invoice generation with duplicate prevention and tenant billing cycles.
- [ ] Tax configuration and invoice line calculations.
- [ ] Partial payments and deterministic allocation to invoices.
- [ ] Duplicate, unmatched, excess and unapplied payment handling.
- [ ] Reconciliation against gateway callbacks and statements.
- [ ] Refunds, reversals and credit notes with approval/audit controls.
- [ ] Separate ISP customer ledger from Flexihub SaaS subscription ledger.

## Workstream 3 — Customer lifecycle and service orders

- [ ] Service request/order capture and status history.
- [ ] Installation scheduling and assignment.
- [ ] Activation workflow with eligibility/payment checks.
- [ ] Plan changes, renewal, suspension, resumption and cancellation.
- [ ] Proration and effective-date rules.
- [ ] Customer self-service for profile, services, invoices, payments and support.
- [ ] Every transition emits an auditable service event and handles retry safely.

## Workstream 4 — Support and field operations

- [ ] Ticket categories, priorities, assignment, SLA timers and escalation.
- [ ] Incident/outage linkage and customer communication.
- [ ] Technician profiles, skills, availability and workload.
- [ ] Installation and repair work orders with dispatch/scheduling.
- [ ] Job status, notes, timestamps, photos, materials used and customer sign-off.
- [ ] Technician job history and performance reporting.
- [ ] CPE/ONT inventory issue, return and customer/site assignment.

## Workstream 5 — Flexihub SaaS and resellers

- [ ] Platform tenant onboarding, verification, activation, suspension and closure.
- [ ] Platform plans, limits, feature entitlements, trials and renewals.
- [ ] SaaS invoices, payments and subscription ledger independent of ISP customer finances.
- [ ] Usage metering and plan-limit enforcement.
- [ ] Tenant branding, logo, colours and custom domains with safe validation.
- [ ] Reseller onboarding, tenant assignment, commission rules and payout reconciliation.
- [ ] Platform-host permissions separated from tenant permissions.

## Workstream 6 — Production reliability

- [ ] Versioned, repeatable database migrations with preflight and rollback guidance.
- [ ] Automated tests for permissions, tenant isolation, financial posting and service lifecycle.
- [ ] Backup/restore procedure with verified restore test.
- [ ] Structured application and integration logs with correlation IDs.
- [ ] Health checks and monitoring for queue workers, callbacks and router integrations.
- [ ] Error handling that does not silently report failed operations as successful.

## Delivery sequence and completion gate

1. Unified RBAC and tenant context.
2. Network operations and provisioning.
3. Billing and financial control.
4. Customer lifecycle.
5. Support and field operations.
6. SaaS and reseller capabilities.
7. Reliability and regression testing.

For each workstream: inspect existing implementation → reconcile schema → implement missing capability → apply shared UI → test tenant boundaries and normal/error paths → record the evidence here. Do not duplicate working features.
