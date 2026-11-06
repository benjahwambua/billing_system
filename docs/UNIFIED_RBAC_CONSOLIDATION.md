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
