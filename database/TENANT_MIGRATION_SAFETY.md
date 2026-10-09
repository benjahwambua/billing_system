# Tenant schema migration safety

## Current tenant key contract

The tenant parent key is `tenants.id BIGINT UNSIGNED`. Tenant-aware tables use `tenant_id BIGINT UNSIGNED` and reference `tenants(id)` with `ON DELETE RESTRICT ON UPDATE CASCADE`.

## Important: migration 010 is a one-time legacy repair

`010_repair_tenant_foreign_keys.sql` is intended only for a legacy database that has the old tenant key type and is missing the expected foreign keys. It drops and recreates constraints and is **not safe to rerun** against an already repaired database. Back up first, inspect the actual schema, and run the orphan audit before using it. Do not run it as a routine deployment step.

The repository also contains another file named `010_service_activation_hotspot_runtime.sql`. Migration filenames therefore must not be treated as a complete, reliable installation order by numeric prefix alone.

## Existing installation

1. Back up the database and identify the exact schema state.
2. Run `003_tenant_isolation_audit.sql` and review its findings.
3. Confirm that the tenant key and every child `tenant_id` column have compatible types, and that orphan counts are zero.
4. If the database is the specific legacy state addressed by `010_repair_tenant_foreign_keys.sql`, apply that repair once only. If it is already repaired, do not rerun it.
5. Run `tenant_integrity_verification.sql` to inspect the constraints and orphan counts.
6. Test application-level tenant scoping separately; database foreign keys do not prevent a query from reading another tenant's rows.

## Fresh installation

Do not apply the legacy repair migration blindly. Review the full schema bootstrap and migration dependencies for the target installation, create `tenants.id` as `BIGINT UNSIGNED` from the start, and ensure each tenant-aware table uses the same type before adding foreign keys. Run the read-only verification script afterward.

## Verification script

`tenant_integrity_verification.sql` is read-only. It returns:
- the tenant foreign keys and their delete/update rules;
- the number of tenant-aware tables with a foreign key to `tenants(id)`;
- orphan counts for the 24 currently expected tenant-aware tables.

For the currently repaired local database, expect 24 tables with tenant foreign keys and zero orphan rows for every table. A passing schema audit is necessary but not sufficient for production readiness; application authorization, query scoping, and tenant-boundary tests are still required.
