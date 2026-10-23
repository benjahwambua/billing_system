<?php
/**
 * Read-only migration inventory and deployment preflight.
 *
 * Run from the repository root:
 *   php database/migration_preflight.php
 *
 * This script NEVER connects to MySQL and NEVER executes SQL. It reports
 * repository-level migration risks only; a clean result is not proof that
 * migrations can be applied to a particular database.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

$directory = __DIR__;
$files = glob($directory . DIRECTORY_SEPARATOR . '*.sql') ?: [];
$names = array_map('basename', $files);
sort($names, SORT_STRING);

$migrations = [];
$prefixes = [];
$blockers = [];
$warnings = [];

foreach ($names as $name) {
    // Read-only audit/query scripts are not incremental migrations.
    if (in_array($name, ['003_tenant_isolation_audit.sql', 'tenant_integrity_verification.sql'], true)) {
        continue;
    }

    if (!preg_match('/^(\d+)_.*\.sql$/', $name, $matches)) {
        $warnings[] = "SQL file has no numbered migration prefix: {$name}";
        continue;
    }

    $prefix = $matches[1];
    $migrations[] = $name;
    $prefixes[$prefix][] = $name;

    $contents = file_get_contents($directory . DIRECTORY_SEPARATOR . $name);
    if ($contents === false) {
        $blockers[] = "Cannot read migration file: {$name}";
        continue;
    }

    // Conservative static warnings only; no SQL is parsed or executed.
    if (preg_match('/\bALTER\s+TABLE\b[\s\S]*?\bADD\s+(?:COLUMN\s+)?(?!IF\s+NOT\s+EXISTS\b)/i', $contents)) {
        $warnings[] = "Review ALTER TABLE ADD statements for rerun safety: {$name}";
    }
    if (preg_match('/\bCREATE\s+(?:UNIQUE\s+)?INDEX\b(?!\s+IF\s+NOT\s+EXISTS\b)/i', $contents)) {
        $warnings[] = "Review CREATE INDEX statements for rerun safety: {$name}";
    }
}

foreach ($prefixes as $prefix => $group) {
    if (count($group) > 1) {
        $blockers[] = sprintf(
            'Numbering collision %s: %s (filename, not prefix alone, must identify each migration)',
            $prefix,
            implode(', ', $group)
        );
    }
}

$baselineCandidates = ['001_schema.sql', '002_initial_schema.sql', 'schema.sql', 'baseline.sql'];
$hasBaseline = count(array_intersect($names, $baselineCandidates)) > 0;
if (!$hasBaseline) {
    $blockers[] = 'No recognized canonical clean-install baseline was found; historical migrations are not a clean-install package.';
}

echo "Flexihub Billing System — read-only migration preflight\n";
echo "SQL files found: " . count($names) . "\n";
echo "Numbered migration candidates: " . count($migrations) . "\n";
echo "Database connection: none\n";
echo "SQL execution: none\n\n";

if ($blockers) {
    echo "BLOCKERS (" . count($blockers) . ")\n";
    foreach ($blockers as $item) {
        echo " - {$item}\n";
    }
    echo "\n";
}

if ($warnings) {
    echo "REVIEW WARNINGS (" . count($warnings) . ")\n";
    foreach (array_unique($warnings) as $item) {
        echo " - {$item}\n";
    }
    echo "\n";
}

echo "Migration inventory (filename order; NOT an approved execution order):\n";
foreach ($migrations as $name) {
    echo " - {$name}\n";
}

if (!$blockers && !$warnings) {
    echo "\nNo repository-level warnings detected. Database-specific review is still required.\n";
    exit(0);
}

echo "\nDo not bulk-import this directory. Resolve blockers and review warnings before deployment.\n";
exit($blockers ? 2 : 1);
