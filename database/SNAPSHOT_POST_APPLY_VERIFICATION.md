# Post-apply schema verification

**Run only on the disposable restored database** after executing
`database/037_snapshot_compatible_core_runtime.sql`. This check is read-only.

## Procedure

1. Confirm phpMyAdmin has `billing_system_snapshot_test_20261027` selected.
2. Run `database/snapshot_runtime_post_apply_verification.sql`.
3. Review the table-existence results. Every candidate table should be present and use InnoDB.
4. Review tenant key type results. Tenant ID columns should match `tenants.id BIGINT UNSIGNED`.
5. Review the foreign-key results for `hotspot_packages`, `hotspot_access_codes`, and `hotspot_finalization_queue`. Any missing or unexpected target requires investigation.
6. Run `database/snapshot_runtime_contract_preflight.sql` again. There should be no required table/column marked missing.
7. Run `database/snapshot_financial_reconciliation_preflight.sql` and compare it with the baseline captured before candidate execution.
8. If any SQL statement fails, stop and inspect the partial DDL state; do not rerun blindly.

## Limits

This verifies schema objects and selected key definitions, not application workflows, payment processing, M-Pesa callbacks, router activation, or full tenant isolation. SQL execution against MariaDB 10.4.32 is still required; static inspection is not a substitute.
