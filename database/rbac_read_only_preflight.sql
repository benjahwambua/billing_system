-- Flexihub RBAC read-only preflight
-- Purpose: collect the live authorization schema and current role assignments
-- in one run before any RBAC migration or login cutover.
--
-- SAFE: SELECTs and session-local variables only. No CREATE, ALTER, INSERT,
-- UPDATE, DELETE, TRUNCATE, DROP, or account changes are performed.
-- Run with billing_system selected in phpMyAdmin. Do not import this file.

-- 1) Which RBAC-related tables actually exist?
SELECT TABLE_NAME, ENGINE
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
      'users', 'tenants',
      'roles', 'role_permissions', 'permissions', 'user_roles',
      'access_roles', 'access_role_permissions', 'access_user_roles'
  )
ORDER BY TABLE_NAME;

-- 2) Exact columns and types for the user and RBAC tables.
SELECT TABLE_NAME, ORDINAL_POSITION, COLUMN_NAME, COLUMN_TYPE,
       IS_NULLABLE, COLUMN_DEFAULT, COLUMN_KEY, EXTRA
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
      'users', 'roles', 'role_permissions', 'permissions', 'user_roles',
      'access_roles', 'access_role_permissions', 'access_user_roles'
  )
ORDER BY TABLE_NAME, ORDINAL_POSITION;

-- 3) Existing indexes and their column order.
SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE,
       GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ', ') AS indexed_columns
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
      'users', 'roles', 'role_permissions', 'permissions', 'user_roles',
      'access_roles', 'access_role_permissions', 'access_user_roles'
  )
GROUP BY TABLE_NAME, INDEX_NAME, NON_UNIQUE
ORDER BY TABLE_NAME, INDEX_NAME;

-- 4) Existing foreign keys.
SELECT TABLE_NAME, CONSTRAINT_NAME, COLUMN_NAME,
       REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = DATABASE()
  AND REFERENCED_TABLE_NAME IS NOT NULL
  AND TABLE_NAME IN (
      'users', 'roles', 'role_permissions', 'permissions', 'user_roles',
      'access_roles', 'access_role_permissions', 'access_user_roles'
  )
ORDER BY TABLE_NAME, CONSTRAINT_NAME, ORDINAL_POSITION;

-- 5) Existing users. Password hashes and other secrets are intentionally omitted.
SELECT id, tenant_id, user_scope, staff_id, username, role, status, last_login
FROM users
ORDER BY id;

-- 6) Current access_* role assignments and permissions, if all three tables exist.
SET @has_access_tables := (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME IN ('access_roles', 'access_role_permissions', 'access_user_roles')
);
SET @access_sql := IF(
    @has_access_tables = 3,
    'SELECT u.id AS user_id, u.username, u.user_scope, u.tenant_id AS user_tenant_id,
            ar.id AS role_id, ar.name AS role_name, ar.tenant_id AS role_tenant_id,
            ar.status AS role_status, arp.module_key,
            arp.can_view, arp.can_create, arp.can_edit, arp.can_delete, arp.can_approve
     FROM access_user_roles aur
     INNER JOIN users u ON u.id = aur.user_id
     INNER JOIN access_roles ar ON ar.id = aur.role_id
     LEFT JOIN access_role_permissions arp
       ON arp.role_id = ar.id AND arp.tenant_id = ar.tenant_id
     ORDER BY u.id, ar.id, arp.module_key',
    'SELECT ''access_* tables are not all installed'' AS preflight_note'
);
PREPARE rbac_access_stmt FROM @access_sql;
EXECUTE rbac_access_stmt;
DEALLOCATE PREPARE rbac_access_stmt;

-- 7) Legacy user_roles mapping, only when the expected legacy tables/columns exist.
SET @has_legacy_role_map := (
    SELECT COUNT(*) = 4
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND (
          (TABLE_NAME = 'user_roles' AND COLUMN_NAME IN ('user_id', 'role_id')) OR
          (TABLE_NAME = 'roles' AND COLUMN_NAME IN ('id', 'name'))
      )
);
SET @legacy_sql := IF(
    @has_legacy_role_map,
    'SELECT u.id AS user_id, u.username, u.user_scope, u.tenant_id,
            r.id AS role_id, r.name AS role_name
     FROM user_roles ur
     INNER JOIN users u ON u.id = ur.user_id
     INNER JOIN roles r ON r.id = ur.role_id
     ORDER BY u.id, r.id',
    'SELECT ''legacy user_roles/roles mapping is not present in the expected form'' AS preflight_note'
);
PREPARE rbac_legacy_stmt FROM @legacy_sql;
EXECUTE rbac_legacy_stmt;
DEALLOCATE PREPARE rbac_legacy_stmt;

-- 8) Legacy single-role pointer, only if users.role_id exists.
SET @has_users_role_id := (
    SELECT COUNT(*) > 0
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'role_id'
);
SET @role_id_sql := IF(
    @has_users_role_id,
    'SELECT u.id AS user_id, u.username, u.user_scope, u.tenant_id,
            u.role AS legacy_role, u.role_id, r.name AS linked_role_name
     FROM users u
     LEFT JOIN roles r ON r.id = u.role_id
     ORDER BY u.id',
    'SELECT ''users.role_id is not present'' AS preflight_note'
);
PREPARE rbac_role_id_stmt FROM @role_id_sql;
EXECUTE rbac_role_id_stmt;
DEALLOCATE PREPARE rbac_role_id_stmt;
