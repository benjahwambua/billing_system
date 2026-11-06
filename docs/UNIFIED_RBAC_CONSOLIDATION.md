# Flexihub Unified Roles and Permissions Consolidation

**Status:** Design and implementation gate  
**Branch:** `feature/unified-rbac-consolidation`  
**Scope:** Administration / identity and access management  
**Database impact:** None in this change. This document does not authorize SQL execution or data changes.

## Objective

Provide one authorization model for the whole Flexihub application. A module must not make an access decision from a mixture of legacy role strings, legacy permission-code tables, and the newer module/action tables.

The consolidation must preserve existing user accounts, tenant boundaries, platform/host administration, and currently granted access. No legacy table or column is to be dropped until the migration has been reconciled and verified against a disposable copy of the actual database.

## Findings from the current source and migration review

1. `database/005_roles_permissions.sql` creates `roles` and `role_permissions`, and adds `users.role_id`. It does not create the `user_roles` or `permissions` structures queried by the legacy helpers in `includes/functions.php`.
2. `database/006_access_control.sql` creates a second catalogue and permission matrix: `access_roles`, `access_role_permissions`, and `access_user_roles`.
3. `roles/index.php` reads and writes the `access_*` tables. It saves the five module actions (view/create/edit/delete/approve), but the module list is currently embedded in the page.
4. `users/index.php` adds role assignments with `INSERT IGNORE`, has no visible remove/replace workflow, and displays `users.role` rather than the assigned canonical roles.
5. `includes/functions.php` has a mixed authorization path:
   - `getCurrentUserRoles()` reads legacy `roles` and `user_roles`.
   - `userHasPermission()` reads legacy `user_roles`, `role_permissions`, and `permissions`.
   - `userCan()` prefers `access_*` when those tables are present, otherwise falls back to the legacy permission-code path.
   - `userCan()` selects one matching permission row with `LIMIT 1`; multiple assigned roles therefore do not have a defined effective-permission rule.
   - `isSuperUser()` also recognizes legacy session role strings.
6. `getCurrentUserScope()` and `getCurrentTenantId()` resolve host versus tenant scope from the persisted `users` row. This persisted scope is a security boundary and must not be inferred from a submitted role or a stale session value.
7. The supplied SQL snapshot contains the base `users` table and role-related migration files are incremental. The snapshot alone is not proof that migrations 005/006 were applied to the live local database.

## Canonical authorization contract

### One application-facing API

All protected pages and actions must call the same helpers:

- `userCan($module, $action)` — returns a boolean and denies by default.
- `requireModulePermission($module, $action)` — enforces the same decision and returns HTTP 403 on denial.
- `hasRole($roleCode)` — checks only the current user's canonical, scope-appropriate role assignments.
- `isSuperUser()` — must not grant tenant-wide access based on a legacy display string or a tenant-controlled role name.

The helpers must use persisted user identity and scope, and must fail closed when the required canonical schema or query is unavailable. No automatic fallback to legacy permissions is allowed after cutover.

### Canonical role and permission storage

For tenant users, the current `access_roles`, `access_role_permissions`, and `access_user_roles` tables are the migration target, subject to schema review and safe evolution. They must be the only source of tenant role assignments and module permissions after cutover.

Platform/host users must be represented in the same authorization contract, with an explicit platform scope that cannot be assigned or modified by tenant administrators. The schema design must decide how platform-scoped roles fit the canonical catalogue and assignment model before implementation. A tenant role must never grant platform access.

Legacy `roles`, `role_permissions`, `user_roles`, `permissions`, `users.role`, and `users.role_id` are migration inputs/compatibility data only. They must not remain active competing authorization sources after cutover.

### Multiple role aggregation

A user's effective permission for a module/action is the logical OR of that action across all active canonical roles assigned to that user within the same scope and tenant. In SQL this must be implemented as an aggregate (for example, `MAX(can_view)`) or an equivalent deterministic query—not `LIMIT 1`.

This rule is intentionally explicit: adding a second role can add permissions, but cannot silently remove permissions granted by another active role. Removing a role can reduce access. The UI must show the resulting assigned roles clearly.

### Scope and tenant isolation

- Tenant users can only be assigned roles owned by their own tenant.
- Role-permission rows must belong to the same tenant as their role.
- User-role assignments must match the persisted tenant ID and tenant scope of the user.
- Platform roles and assignments are managed only through host/platform administration.
- A host/platform role must never be assignable from the tenant Users & Access page.
- Unknown scope, missing tenant context, mismatched tenant IDs, inactive users, inactive roles, and missing schema all deny access.
- Module/action keys must be normalized and validated against a central permission catalogue.

## Implementation phases

### Phase 1 — Freeze the contract and inventory callers

- Centralize the module/action catalogue; remove the duplicate hard-coded list from individual pages.
- Inventory all uses of `hasRole`, `userHasPermission`, `userCan`, `requirePermission`, and `requireModulePermission`.
- Classify each caller as platform, tenant, or shared.
- Identify any pages still using direct `$_SESSION['role']`, `users.role`, or role-name comparisons.
- Confirm actual schema and migration state from the user's existing database without importing SQL or changing data.

**Acceptance:** Every authorization entry point and caller is listed, and no unreviewed role/permission table is treated as canonical.

### Phase 2 — Canonical schema and data reconciliation

- Finalize one role/assignment model that supports both platform and tenant scopes without cross-scope assignment.
- Inspect actual table definitions, indexes, foreign keys, and rows in a disposable database copy.
- Produce a read-only reconciliation report mapping legacy roles/permissions and current `access_*` roles/permissions to canonical role IDs and module/action grants.
- Detect duplicate names, orphaned assignments, missing permission codes, tenant mismatches, and users with no role.
- Define deterministic conflict handling; do not silently merge roles with different grants.
- Prepare an idempotent, reviewable migration with preflight, backup, row counts, and post-migration verification.

**Acceptance:** A dry-run report accounts for every existing user, role, assignment, and permission. No production/local database writes occur before approval.

### Phase 3 — Central authorization implementation

- Implement the canonical query behind the shared helper API.
- Aggregate permissions across all assigned active roles.
- Remove legacy permission fallback and legacy role-string elevation only after equivalent canonical assignments exist.
- Keep platform and tenant authorization explicitly separated.
- Add safe caching only if invalidation on role/assignment changes is guaranteed; correctness takes priority over caching.

**Acceptance:** Every helper uses one canonical source and fails closed; tests prove multiple-role aggregation and cross-tenant denial.

### Phase 4 — Administration UI

- Make the role editor use the central permission catalogue.
- Add role creation, editing, activation/deactivation, and safe deletion rules.
- Add user role assignment, replacement, and removal with CSRF protection and tenant ownership checks.
- Display all canonical assigned roles, not the legacy `users.role` string.
- Prevent removal/deactivation of the last required platform administrator and protect system roles.
- Record role and permission changes in audit logs.

**Acceptance:** Administrators can manage access without duplicate assignments, stale role labels, or cross-tenant access.

### Phase 5 — Application-wide adoption

- Update all module guards and action handlers to use the canonical helpers.
- Remove direct legacy role comparisons where they make authorization decisions.
- Ensure UI visibility is not treated as authorization; backend handlers must independently enforce permissions.
- Keep navigation and dashboard visibility consistent with backend access.

**Acceptance:** A code search and test inventory show no protected module bypassing the central API.

### Phase 6 — Migration cutover and verification

- Run the migration only on a disposable copy first.
- Compare user/role/permission counts and effective access before and after.
- Test host admin, tenant admin, ordinary staff, multiple roles, no role, inactive role, inactive user, cross-tenant assignment, and direct URL/action access.
- Obtain explicit approval before applying the reviewed migration to the user's working database.
- Retain legacy tables during a defined rollback window; archive/drop them only in a separately approved cleanup migration.

**Acceptance:** All access-preservation checks pass, no cross-tenant or platform escalation is possible, and rollback steps are documented.

## Required test matrix

| Scenario | Expected result |
|---|---|
| Tenant user with one role granting View | View allowed; other actions denied unless granted |
| User with two roles granting different actions | Effective permissions are the union of grants |
| Two roles disagree on one action (one grants, one denies) | Grant is effective under the defined OR rule |
| Role belongs to another tenant | Assignment rejected |
| Assignment tenant differs from persisted user tenant | Assignment rejected |
| Tenant administrator attempts to assign platform role | Rejected |
| Host user attempts to use tenant role as platform authority | Denied |
| Inactive role or user | Denied |
| No role assignment | Denied by default |
| Canonical tables/query unavailable | Denied by default; no legacy fallback |
| Direct URL or POST without permission | HTTP 403 |
| Role removed | Effective permissions recalculate without that role |
| Legacy role string says Administrator but no canonical authority | No elevation |

## Non-goals and safety constraints

- Do not import the supplied SQL snapshot.
- Do not reset, clone over, or modify the working database as part of this source change.
- Do not delete or rename legacy role tables in the first implementation pass.
- Do not change customer, payment, invoice, router, or subscription data.
- Do not grant every user administrator access to avoid migration problems.
- Do not claim production readiness based only on the presence of tables or pages.


## Expanded caller and login review

The source review also inspected the login flow, host guards, navigation, and representative tenant modules.

### Confirmed authorization entry points

- Tenant pages already using `requireModulePermission()` include customer list/create/view, internet plan list/create, internet account list/create, billing, invoices, payments, hotspot dashboard/sales, router list/create, network status/monitoring, support, operations, staff list/create, roles, users, settings, M-Pesa settings, and reports.
- Some list/detail pages use `userCan()` to conditionally show create/edit controls; these controls are presentation only and must continue to be backed by server-side checks.
- The sidebar has its own path-to-module mapping and calls `userCan()`. This is a second module map that should be generated from the central catalogue, not independently maintained.
- The dashboard, `pppoe/accounts.php`, and `audit_logs/index.php` did not show a direct module guard in the inspected source. Their intended access model and any guards in included code must be verified before declaring them unprotected.
- `requirePermission()` and `userHasPermission()` are legacy permission-code entry points. Their remaining callers must be inventoried before those compatibility functions are removed.

### Login flow defect

`auth/login.php` still tries to load role codes from `user_roles` joined to `roles.role_code`, filtered by `roles.status`. That is incompatible with the supplied migration 005, which creates `roles.name` and `roles.is_system`, does not create `user_roles`, and does not create `roles.role_code` or `roles.status`.

The login flow then assigns a synthetic `HOST_SUPER_ADMIN` session role when a host-scoped user has an empty role list and the legacy `users.role` value is `Administrator`. The authorization helpers also elevate based on the session's legacy role string. This is a separate source of authority from the newer tenant `access_*` assignments and must be removed only as part of a controlled migration that gives existing host administrators explicit canonical assignments.

### Host/platform model decision

The current `access_roles` schema requires `tenant_id NOT NULL` and references `tenants`. It therefore cannot represent a host/platform role without bending tenant semantics or creating another role catalogue.

The chosen direction is **one shared role catalogue and assignment model with an explicit scope discriminator**, not a separate platform role system. The migration design must evolve the current `access_*` tables to represent:

- `scope = 'platform'` with no tenant owner; or
- `scope = 'tenant'` with a required tenant owner.

A stable `scope_key` (for example, `platform` or `tenant:<id>`) should provide an unambiguous uniqueness boundary for role keys/names despite SQL NULL uniqueness behavior. Role assignments and permission rows must carry the same scope/owner identity, and every authorization query must join the persisted `users.user_scope` and `users.tenant_id` to the assignment. The exact DDL and compatibility approach must be tested against MariaDB 10.4 before it is approved.

Host access must not mean “every host user is a super administrator”. Host-scoped roles need explicit platform permissions. Platform routes should use a platform module/action permission check in addition to the persisted host-scope check. Tenant administrators must never create, assign, edit, or deactivate platform roles.

## Updated implementation gate

Before changing the shared authorization helpers, complete the following in order:

1. Finish the repository-wide caller inventory for the legacy APIs and direct role-string checks.
2. Verify the exact live migration state against the already supplied SQL baseline; the source snapshot is not proof that migrations 005/006 were run.
3. Define the shared platform/tenant schema and role keys, including the first platform owner/bootstrap path.
4. Prepare a read-only mapping report for the existing host administrator and every tenant user's current legacy role and any existing `access_*` assignments.
5. Only then change login, shared helpers, role editor, user assignment UI, and page guards as one coordinated access-control change.

No code path should start relying exclusively on the new model until the existing administrator has a verified canonical assignment. This avoids locking the user out during cutover.

## Current status

- [x] Duplicate role/permission structures identified in migrations and helpers.
- [x] Mixed legacy/new authorization paths identified.
- [x] Users & Access assignment/display limitations identified.
- [ ] Full caller inventory completed.
- [ ] Canonical platform/tenant schema finalized.
- [ ] Reconciliation preflight prepared and reviewed.
- [ ] Shared authorization helper consolidated.
- [ ] Role and user assignment workflows completed.
- [ ] All modules adopted the canonical helpers.
- [ ] Disposable migration and authorization tests passed.
- [ ] Working database migration approved and applied.
