<?php
/**
 * Read-only preflight for database/038_rbac_consolidation.sql.
 *
 * Run from the repository root:
 *   C:\\xampp\\php\\php.exe database\\rbac_preflight.php
 *
 * This script inspects the connected database only. It never creates,
 * alters, deletes, or updates database objects or records.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require_once __DIR__ . '/../config/database.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    fwrite(STDERR, "Database connection is unavailable.\n");
    exit(2);
}

$conn->set_charset('utf8mb4');

function rbacPrint($label, $value)
{
    echo str_pad($label, 42) . $value . PHP_EOL;
}

function rbacTableExists(mysqli $conn, string $table): bool
{
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS total
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?"
    );
    if (!$stmt) return false;
    $stmt->bind_param('s', $table);
    if (!$stmt->execute()) {
        $stmt->close();
        return false;
    }
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['total'] ?? 0) > 0;
}

function rbacColumns(mysqli $conn, string $table): array
{
    $stmt = $conn->prepare(
        "SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?"
    );
    if (!$stmt) return [];
    $stmt->bind_param('s', $table);
    if (!$stmt->execute()) {
        $stmt->close();
        return [];
    }
    $result = $stmt->get_result();
    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $columns[$row['COLUMN_NAME']] = [
            'type' => strtolower($row['COLUMN_TYPE']),
            'nullable' => $row['IS_NULLABLE'] === 'YES',
        ];
    }
    $stmt->close();
    return $columns;
}

function rbacEngine(mysqli $conn, string $table): ?string
{
    $stmt = $conn->prepare(
        "SELECT ENGINE
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?"
    );
    if (!$stmt) return null;
    $stmt->bind_param('s', $table);
    if (!$stmt->execute()) {
        $stmt->close();
        return null;
    }
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row['ENGINE'] ?? null;
}

function rbacCount(mysqli $conn, string $sql): ?int
{
    $result = $conn->query($sql);
    if (!$result) return null;
    $row = $result->fetch_row();
    $result->free();
    return isset($row[0]) ? (int)$row[0] : null;
}

echo "Flexihub — read-only RBAC migration preflight\n";
echo "Database: " . $conn->query("SELECT DATABASE()")->fetch_row()[0] . "\n";
echo "SQL writes: NONE\n\n";

$requiredCanonical = [
    'access_roles' => ['id', 'tenant_id', 'name', 'description', 'is_system', 'status'],
    'access_role_permissions' => ['id', 'tenant_id', 'role_id', 'module_key', 'can_view', 'can_create', 'can_edit', 'can_delete', 'can_approve'],
    'access_user_roles' => ['id', 'tenant_id', 'user_id', 'role_id'],
];

$canonicalPresent = 0;
$canonicalIncomplete = [];
$canonicalColumns = [];

foreach ($requiredCanonical as $table => $required) {
    $exists = rbacTableExists($conn, $table);
    $columns = $exists ? rbacColumns($conn, $table) : [];
    $canonicalColumns[$table] = $columns;

    if (!$exists) {
        rbacPrint($table, 'MISSING — migration would create it');
        continue;
    }

    $canonicalPresent++;
    $missing = array_values(array_diff($required, array_keys($columns)));
    if ($missing) {
        $canonicalIncomplete[$table] = $missing;
        rbacPrint($table, 'EXISTS BUT INCOMPLETE: ' . implode(', ', $missing));
    } else {
        rbacPrint($table, 'present; required columns found');
    }

    $engine = rbacEngine($conn, $table);
    rbacPrint($table . ' engine', (string)($engine ?? 'unknown'));
    if (strtolower((string)$engine) !== 'innodb') {
        $canonicalIncomplete[$table][] = 'table engine must be InnoDB';
    }
}

echo PHP_EOL . "Migration 038 prerequisites\n";
$legacyRequirements = [
    'tenants' => ['id'],
    'users' => ['id', 'tenant_id', 'user_scope'],
    'roles' => ['id', 'tenant_id', 'name', 'description', 'is_system'],
    'role_permissions' => ['role_id', 'module_key', 'can_view', 'can_create', 'can_edit', 'can_delete', 'can_approve'],
    'user_roles' => ['user_id', 'role_id'],
];

$legacyWarnings = [];
foreach ($legacyRequirements as $table => $required) {
    $exists = rbacTableExists($conn, $table);
    $columns = $exists ? rbacColumns($conn, $table) : [];
    $missing = array_values(array_diff($required, array_keys($columns)));
    if (!$exists) {
        $legacyWarnings[] = $table . ' table is missing';
        rbacPrint($table, 'MISSING');
    } elseif ($missing) {
        $legacyWarnings[] = $table . ' is missing: ' . implode(', ', $missing);
        rbacPrint($table, 'missing columns: ' . implode(', ', $missing));
    } else {
        rbacPrint($table, 'present; required columns found');
    }
}

$tenantColumns = rbacColumns($conn, 'tenants');
$tenantIdTypeRaw = $tenantColumns['id']['type'] ?? null;
$tenantIdType = $tenantIdTypeRaw !== null ? preg_replace('/\\(\\d+\\)/', '', $tenantIdTypeRaw) : null;
$tenantEngine = rbacEngine($conn, 'tenants');
rbacPrint('tenants.id column type', (string)($tenantIdType ?? 'unknown'));
rbacPrint('tenants table engine', (string)($tenantEngine ?? 'unknown'));
if (strtolower((string)$tenantEngine) !== 'innodb') {
    $canonicalIncomplete['tenants'] = ['table engine must be InnoDB for tenant foreign keys'];
}

foreach (['access_roles', 'access_role_permissions', 'access_user_roles'] as $table) {
    if (!empty($canonicalColumns[$table]['tenant_id']['type'])) {
        $actualRaw = $canonicalColumns[$table]['tenant_id']['type'];
        $actual = preg_replace('/\\(\\d+\\)/', '', $actualRaw);
        rbacPrint($table . '.tenant_id column type', $actualRaw);
        if ($tenantIdType !== null && $actual !== $tenantIdType) {
            $canonicalIncomplete[$table][] = 'tenant_id type differs from tenants.id';
        }
    }
}

$usersColumns = rbacColumns($conn, 'users');
if (isset($usersColumns['user_scope'])) {
    rbacPrint('users.user_scope', 'present');
} else {
    rbacPrint('users.user_scope', 'MISSING (application RBAC requires it)');
}

echo PHP_EOL . "Canonical RBAC data checks\n";
$canonicalChecks = [
    'Canonical roles with missing tenant' => "SELECT COUNT(*) FROM access_roles ar LEFT JOIN tenants t ON t.id=ar.tenant_id WHERE t.id IS NULL",
    'Canonical permissions crossing role tenant' => "SELECT COUNT(*) FROM access_role_permissions p LEFT JOIN access_roles r ON r.id=p.role_id AND r.tenant_id=p.tenant_id WHERE r.id IS NULL",
    'Canonical user assignments crossing tenant' => "SELECT COUNT(*) FROM access_user_roles ur LEFT JOIN users u ON u.id=ur.user_id AND u.tenant_id=ur.tenant_id LEFT JOIN access_roles r ON r.id=ur.role_id AND r.tenant_id=ur.tenant_id WHERE u.id IS NULL OR r.id IS NULL",
    'Duplicate canonical role names within tenant' => "SELECT COUNT(*) FROM (SELECT tenant_id, name FROM access_roles GROUP BY tenant_id, name HAVING COUNT(*)>1) d",
];
foreach ($canonicalChecks as $label => $sql) {
    $count = rbacCount($conn, $sql);
    rbacPrint($label, $count === null ? 'CHECK FAILED — inspect schema/query' : (string)$count);
}

echo PHP_EOL . "Legacy migration data checks\n";
$legacyChecks = [
    'Roles with missing tenant records' => "SELECT COUNT(*) FROM roles r LEFT JOIN tenants t ON t.id=r.tenant_id WHERE t.id IS NULL",
    'Tenant users with missing tenant records' => "SELECT COUNT(*) FROM users u LEFT JOIN tenants t ON t.id=u.tenant_id WHERE u.tenant_id IS NOT NULL AND t.id IS NULL",
    'Legacy user-role assignments crossing tenant boundary' => "SELECT COUNT(*) FROM user_roles ur INNER JOIN users u ON u.id=ur.user_id INNER JOIN roles r ON r.id=ur.role_id WHERE u.tenant_id IS NULL OR r.tenant_id<>u.tenant_id",
    'Duplicate legacy role names within a tenant' => "SELECT COUNT(*) FROM (SELECT tenant_id, name FROM roles GROUP BY tenant_id, name HAVING COUNT(*)>1) d",
];
$legacyTablesAvailable = true;
foreach (['roles', 'users', 'user_roles', 'tenants'] as $table) {
    if (!rbacTableExists($conn, $table)) {
        $legacyTablesAvailable = false;
        break;
    }
}
foreach ($legacyChecks as $label => $sql) {
    $count = $legacyTablesAvailable ? rbacCount($conn, $sql) : null;
    rbacPrint($label, $count === null ? 'NOT APPLICABLE — legacy tables missing' : (string)$count);
}

echo PHP_EOL . "Result\n";
if ($canonicalIncomplete) {
    echo "STOP: one or more RBAC/tenant schema prerequisites are incomplete or incompatible.\n";
    echo "Migration 038 uses CREATE TABLE IF NOT EXISTS and will not repair an existing incomplete table.\n";
    exit(2);
}

if ($tenantIdType !== 'bigint unsigned') {
    echo "STOP: tenants.id is not BIGINT UNSIGNED; review tenant key compatibility before migration.\n";
    exit(2);
}

if ($legacyWarnings) {
    echo "CANONICAL RBAC STRUCTURE PASSED, subject to reviewing the canonical data checks above.\n";
    echo "Migration 038 is NOT applicable to this database: its legacy source tables are absent.\n";
    echo "Do not create legacy tables or run Migration 038. Preserve and validate the existing access_* data instead.\n";
    foreach ($legacyWarnings as $warning) echo " - " . $warning . PHP_EOL;
    exit(0);
}

echo "CANONICAL RBAC STRUCTURE PASSED. Legacy source tables are present for a separately reviewed import.\n";
echo "This is not approval to execute migration 038; back up and review all data checks first.\n";
exit(0);
