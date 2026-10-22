# Initial Source Audit — Authentication, Tenant Boundaries and Payments

**Repository baseline reviewed:** `main` at `39ea02d487fe7241cb78b7dcbff49d561f7f04c0`  
**Scope:** Static source review of authentication, shared permission helpers, invoice/customer listing, M-Pesa callback handling, hotspot authorization and CI. This is not a live penetration test; no database or router was accessed.

## Findings

### A1 — High priority: login throttling / lockout is not evident in the login flow
**Evidence:** `auth/login.php` validates username/password and performs a prepared lookup, but the reviewed flow does not show attempt counting, throttling, lockout, or alerting.

**Risk:** Automated password guessing may continue without application-level friction (in addition to any infrastructure controls).

**Next action:** Add tenant-safe/global login attempt tracking with progressive delay or temporary lockout, avoid account-enumeration differences, and test successful login after expiry. Do not permanently lock accounts in a way that enables denial of service.

### A2 — High priority: legacy plaintext password fallback remains in authentication
**Evidence:** `auth/login.php` first calls `password_verify()`; if it fails, it compares the stored value directly to the supplied password using `hash_equals()`, then upgrades that value to `password_hash()` after a successful match.

**Risk:** This appears intended as a migration path for legacy plaintext credentials. While it supports old accounts, any remaining plaintext passwords are a serious credential-storage risk, and the fallback makes that legacy format part of the active login surface.

**Next action:** Establish how many legacy accounts remain using a safe, non-credential-revealing audit. Plan a forced password reset or time-bounded migration; remove direct plaintext comparison once the migration window is closed. Never log or export password values.

### A3 — High priority: tenant isolation requires route-by-route authorization verification
**Evidence:** `includes/auth.php` deliberately leaves module/action authorization to individual modules. `customers/index.php` and `invoices/index.php` visibly enforce active user, tenant context, and view permission, and scope their listing queries by tenant. The sidebar also maps links to permission codes.

**Risk:** A role-aware sidebar is not a security boundary. Any page missing a server-side `requireModulePermission()`, or any mutation lacking tenant ownership checks, may still be directly accessible.

**Next action:** Inventory every PHP route and each write action. Verify server-side permission checks and tenant-scoped SELECT/UPDATE/DELETE statements. Add negative tests that attempt cross-tenant object IDs and direct URL access for users without permission. Do not globally assume all modules follow the two inspected listing pages.

### A4 — High priority: payment callback correctness and replay behavior need focused testing
**Evidence:** `payments/mpesa_callback.php` accepts gateway ID and callback token from the URL, parses the STK callback, checks transaction identity, and has transaction/locking logic in the inspected branches. It validates platform callback amount and receipt, and checks for duplicate platform receipt use.

**Risk / unknowns:** Static inspection alone does not establish that every customer-payment branch validates the callback token, amount, phone and receipt consistently, or that all concurrent/replayed callback cases are safe. Callback URLs also contain a bearer-like token; URL and application logs must not expose it.

**Next action:** Build an automated callback test matrix for success, failure, wrong token, unknown checkout ID, amount mismatch, phone mismatch, duplicate receipt, duplicate callback, out-of-order callback, concurrent callback, overpayment, already-paid invoice, and DB failure. Check all customer and platform branches before changing production payment logic.

### A5 — High priority: external router state can diverge from the database during hotspot authorization
**Evidence:** `includes/hotspot_workflow.php` calls MikroTik to add/update a hotspot user before inserting the corresponding `hotspot_sessions` row. It throws if session recording fails, but the router action has already occurred. The finalization queue retries work and records router sync logs, which is a useful foundation.

**Risk:** If the database insert fails after router authorization succeeds, live access may exist without a matching local session. A retry may also encounter a pre-existing router user, so recovery must be idempotent.

**Next action:** Add a reconciliation/compensation design: uniquely identify the intended router operation, persist intent before side effects where feasible, record external result, and reconcile router state after partial failure. Test router success + DB failure and retry scenarios with a mocked router API before changing live router behavior.

### A6 — Medium priority: session lifecycle needs full verification
**Evidence:** `auth/login.php` calls `session_regenerate_id(true)` after credentials and tenant status are checked. `includes/auth.php` starts sessions and includes helpers for recording/revoking database sessions.

**Unknowns to verify:** Secure/HttpOnly/SameSite cookie configuration, session idle/absolute expiry, revocation enforcement on each request, handling of inactive users after login, and whether all logout paths revoke both PHP and database sessions.

**Next action:** Inspect full session validation and deployment cookie configuration; add tests for session fixation, expiry, revocation and inactive-user access.

### A7 — Medium priority: CI currently proves PHP syntax, not workflow correctness
**Evidence:** `.github/workflows/php-syntax.yml` runs `php -l` across PHP files. `test/tenant_context.php` is an authenticated diagnostic page rather than a visible automated test suite.

**Risk:** Syntax-green changes can still break SQL, permissions, financial invariants, tenant isolation or provider integrations.

**Next action:** Keep syntax lint and add automated tests in layers: pure PHP unit tests, database-backed tests against disposable MySQL/MariaDB, then mocked integration tests for M-Pesa and MikroTik. Never point CI at the live working database or real routers.

## Positive evidence

- Prepared statements are used in the inspected login, customer listing and invoice listing.
- Login rotates the PHP session ID after successful authentication.
- Customer and invoice list pages explicitly require tenant context and view permission, and their inspected queries scope rows by tenant.
- Gateway credential encryption uses AES-256-GCM and requires `FLEXIHUB_APP_KEY`; production key storage and rotation still need deployment verification.
- Hotspot workflows include a finalization queue and router synchronization logs, giving a base for recovery improvements.

## Execution order

1. Finish a static route/action permission and tenant-scope inventory.
2. Add tests before changing payment callback and router workflow code.
3. Resolve the legacy plaintext-password migration and login throttling design.
4. Add a disposable-database test pipeline and schema/migration smoke tests.
5. Fix confirmed defects in small commits with regression coverage.

**Caution:** These are source-level findings and follow-up requirements, not claims of an exploited vulnerability or proof of production failure.
