# Deployment Blockers and Release Gates

**Status date:** 2026-10-27  
**Repository:** `benjahwambua/billing_system`  
**Purpose:** A single operational checklist for remaining deployment blockers. This is a source/repository status record, not a declaration that the application has passed production testing.

## Release decision

**Do not deploy to production yet.** The repository contains targeted payment-integrity safeguards and CI checks, but the database install/upgrade path and database-backed runtime workflows have not been validated in this workstream.

## Completed repository changes (not equivalent to runtime validation)

- [x] Payment callbacks preserve confirmed gateway status when delayed failure callbacks arrive.
- [x] Platform/manual SaaS payment flow scopes invoices to the tenant and checks subscription renewal/reactivation before commit.
- [x] M-Pesa STK amount policy rejects invalid/non-whole-KES amounts rather than silently charging a different amount.
- [x] Payment artifact workflow fails closed when receipt/ledger storage is missing required structures.
- [x] Invoice settlement guard rejects zero/negative invoice totals as fully paid.
- [x] Read-only payment artifact duplicate preflight added.
- [x] Read-only payment/ledger reconciliation preflight added.
- [x] Existing M-Pesa payment records can enter artifact recovery rather than skipping it.
- [x] Payment retry avoids extending an already-created linked service period twice, when the schema supports that linkage.
- [x] Existing receipt and ledger artifacts are checked against expected invoice/amount when those fields exist.
- [x] Migration manifest and clean-install baseline procedure added.
- [x] Read-only M-Pesa tenant-key type preflight added.
- [x] Relevant repository CI runs passed for the merged changes listed in the current work log.

## Blocking gates — must be completed before production

### 1. Database baseline and migrations
- [ ] Export a schema-only snapshot from a known-good database after confirming it is the intended baseline.
- [ ] Import that snapshot into a separate disposable database and verify it creates cleanly.
- [ ] Reconcile migration inventory, duplicate numeric prefixes, prerequisites, and non-idempotent DDL.
- [ ] Review actual output from `mpesa_tenant_key_preflight.sql`; do not infer live column types from migration source alone.
- [ ] Review actual output from `mpesa_receipt_integrity_preflight.sql` before considering migration 036.
- [ ] Review actual output from `payment_artifact_idempotency_preflight.sql` and `payment_ledger_reconciliation_preflight.sql`; resolve unexplained duplicates, missing artifacts, and mismatches without deleting financial history.
- [ ] Define and test a deterministic clean-install and upgrade sequence on disposable databases.
- [ ] Document backup and restore/rollback steps.

### 2. Database-backed payment and service tests
- [ ] Replay the same successful M-Pesa callback more than once; verify one payment, one receipt claim, one receipt/ledger artifact, and one service-period extension.
- [ ] Replay delayed failure after success; verify confirmed state is not downgraded.
- [ ] Test callback invoice, tenant, account, amount, and receipt mismatch paths; verify they fail closed.
- [ ] Test callback arriving before/after invoice settlement and duplicate payment records.
- [ ] Verify database constraints support the assumptions in payment and subscription code.
- [ ] Test manual/platform SaaS payments, partial payments, failed renewals, and recovery paths.
- [ ] Verify M-Pesa provider sandbox behavior and MikroTik activation separately; CI does not prove either integration works.

### 3. Full application verification (after module implementation)
- [ ] Walk through each module’s create/read/update/status/action workflows with realistic tenant-separated data.
- [ ] Verify direct URL authorization and tenant isolation for each role.
- [ ] Check validation, CSRF, error states, empty states, and failure recovery.
- [ ] Verify reports reconcile to invoices, payments, receipts, and ledger entries.
- [ ] Run database-backed automated tests and regression checks; syntax lint alone is insufficient.

### 4. Production operations
- [ ] Confirm production PHP/MySQL or MariaDB versions and extension compatibility.
- [ ] Move secrets out of source and development config; set least-privilege database credentials.
- [ ] Enforce HTTPS and secure session/cookie settings.
- [ ] Configure application, payment callback, cron/worker and database error logging without exposing secrets or payment credentials.
- [ ] Schedule backups and complete a restore drill.
- [ ] Configure cron/queue monitoring, failed-job visibility, and operational alerts.
- [ ] Perform a controlled release with rollback steps and post-deploy smoke checks.

## Safety constraints

- Never execute historical SQL files as a batch against the working XAMPP database.
- Do not rerun `010_repair_tenant_foreign_keys.sql` against the already-repaired local database.
- Do not apply `036_mpesa_receipt_claims.sql` until duplicate/length preflight output is reviewed and the migration is tested on a disposable copy.
- Do not alter M-Pesa tenant key types until live schema metadata, foreign keys, indexes, and data have been reviewed.
- Keep the working database untouched while building the baseline and migration path.

## Evidence policy

Mark a gate complete only when its evidence is recorded (command/test result, target database/environment, commit, and outcome). A merged PR or green syntax CI check only proves the corresponding repository checks passed; it is not evidence of a successful MySQL import, live callback, or MikroTik operation.
