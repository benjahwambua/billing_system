# Flexihub Module Completion Register

**Working branch:** `feature/module-completion-ui-consistency`  
**Baseline:** `ui/global-style-polish`  
**Rule:** A menu item, database table, or unfinished page does not count as a completed module. Mark a capability complete only after its schema, tenant-scoped workflow, permissions, UI, and practical tests have been checked.

## Cross-cutting foundation — do first

| Capability | Required outcome | Current assessment |
|---|---|---|
| Unified roles and permissions | One authoritative role assignment and permission model for tenant users; platform-host scope separated; no conflicting legacy/new RBAC paths | Incomplete / schema must be reconciled |
| Tenant isolation | Every tenant-owned read/write is scoped to the authenticated tenant; host-only operations are explicitly guarded | Partially present; audit required |
| Audit trail | Record sensitive access, financial changes, approvals, and network actions | Not verified |
| Shared UI system | All authenticated pages use the common layout and design tokens; standalone pages have an intentional matching variant | Existing global CSS; consistency audit required |

## Workstream 1 — Network operations

- [ ] IP address pool lifecycle: create/edit/disable, allocation, release, overlap/conflict detection, exhaustion warnings.
  - Existing verified foundation: tenant-scoped pool CRUD, IPv4/CIDR and range validation, overlap checks, active PPPoE-server assignment protection, and pool-to-server association.
  - Latest improvement: pool list now shows tenant-scoped assigned/active PPPoE server counts when the association schema is present, and hides create/edit actions without the corresponding permission.
  - Still missing: live address allocation/release, in-use address accounting, pool exhaustion thresholds/alerts, and end-to-end RouterOS synchronization tests.
- [ ] Router provisioning synchronisation: desired vs actual state, sync status, safe retry and error history.
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
