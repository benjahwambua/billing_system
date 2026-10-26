# Clean-install baseline capture and migration verification

**Status: procedure only; no baseline has been generated or approved.** This document does not authorize running historical migrations against the working XAMPP database.

## Why a baseline must be captured, not invented

The repository contains incremental migrations with prerequisite gaps, duplicate numeric prefixes, and some non-rerunnable DDL. Concatenating them would not produce a reliable clean-install script. The baseline must come from a known-good schema snapshot whose application workflows and constraints have been reviewed.

## Safe baseline workflow

1. **Freeze the source revision.** Record the main commit SHA and run php database/migration_manifest.php --json. Preserve the manifest with the candidate release so reviewers can identify exact SQL file contents.
2. **Back up the existing database.** In phpMyAdmin, export billing_system with structure and data, and confirm the file exists and is non-empty. Keep it outside the web root. Do not use the working database as a disposable test target.
3. **Capture schema only from a reviewed source.** After confirming the current database is the intended known-good reference, use a local terminal (with credentials supplied securely, not embedded in source or shell history) to export structure only:
   ```bash
   mysqldump --no-data --routines --triggers --events --single-transaction billing_system > billing_system_schema_candidate.sql
   ```
   Use the local XAMPP mysqldump executable if it is not on PATH. Do not commit the candidate until it has been reviewed for environment-specific objects, definer clauses, secrets, unsupported SQL, and whether all required objects are included.
4. **Verify that candidate in isolation.** Create a new empty disposable database on the same supported MySQL/MariaDB version. Import the candidate there—not into billing_system. Confirm table engines, primary keys, indexes, foreign keys, views, triggers, routines, and events. Then run the application setup against the disposable database and record all missing-schema errors.
5. **Exercise schema-dependent paths.** Run automated regression tests and database-backed payment, receipt/ledger, tenant isolation, subscription, and service-activation tests against the disposable database. A schema-only import succeeding is necessary but not sufficient.
6. **Prove the upgrade path separately.** Restore a copy of the current database backup into another disposable database. Apply only reviewed migrations through a migration runner with history/checksum tracking. Verify tenant and financial integrity before and after. Do not mix this test with the clean-install test.
7. **Approve and tag.** Only after both clean-install and upgrade tests pass, review the baseline, migration manifest, runner, rollback/restore procedure, and test results together. Then tag the release candidate and document supported database versions.

## Required safeguards before a runner can execute migrations

- Stable migration identity (full filename plus SHA-256), recorded in a migration-history table.
- Deterministic dependency order; numeric prefixes alone are insufficient.
- Fail-fast behavior on checksum drift, missing prerequisites, duplicate migrations, and partial failures.
- Explicit MySQL/MariaDB version support and DDL transaction limitations.
- Backup/restore procedure tested on a disposable copy.
- No automatic repair or deletion of ambiguous financial rows.
- No migration run through a public web route; CLI or controlled deployment only.

## Current release blockers

- Canonical baseline not yet captured and verified.
- No approved executable migration order.
- Duplicate numeric prefixes remain in historical files.
- Migration 013 tenant key types need deliberate reconciliation.
- Existing schema and clean-install paths have not both passed disposable-database verification.
- Payment reconciliation preflights still need to be run against an appropriately backed-up/disposable schema after confirming column compatibility.

## Read-only inventory

Run from the repository root:

```bash
php database/migration_manifest.php
php database/migration_manifest.php --json
```

This prints filenames and SHA-256 content hashes, identifies numeric prefix collisions, and reports whether a recognized baseline candidate is present. It never connects to MySQL and never executes SQL. The output is evidence for review, not an execution plan.
