<?php
/**
 * Read-only migration inventory with content hashes.
 * Usage: php database/migration_manifest.php [--json]
 * Never connects to MySQL or executes SQL.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}
$directory = __DIR__;
$paths = glob($directory . DIRECTORY_SEPARATOR . '*.sql') ?: [];
$files = [];
$prefixGroups = [];
$baselineCandidates = ['001_schema.sql', '002_initial_schema.sql', 'schema.sql', 'baseline.sql'];
$readOnly = [
    '003_tenant_isolation_audit.sql',
    'tenant_integrity_verification.sql',
    'mpesa_receipt_integrity_preflight.sql',
    'payment_artifact_idempotency_preflight.sql',
    'payment_ledger_reconciliation_preflight.sql',
];
foreach ($paths as $path) {
    $name = basename($path);
    $contents = file_get_contents($path);
    if ($contents === false) {
        fwrite(STDERR, "Unable to read {$name}\n");
        exit(2);
    }
    $isNumbered = preg_match('/^(\d+)_.*\.sql$/', $name, $match) === 1;
    $prefix = $isNumbered ? $match[1] : null;
    if ($isNumbered && !in_array($name, $readOnly, true)) {
        $prefixGroups[$prefix][] = $name;
    }
    $files[] = [
        'file' => $name,
        'sha256' => hash('sha256', $contents),
        'bytes' => strlen($contents),
        'kind' => in_array($name, $readOnly, true) ? 'read_only_audit' : ($isNumbered ? 'numbered_sql_review_required' : 'unclassified_sql'),
        'numeric_prefix' => $prefix,
    ];
}
usort($files, static fn(array $a, array $b): int => strcmp($a['file'], $b['file']));
$collisions = [];
foreach ($prefixGroups as $prefix => $group) {
    if (count($group) > 1) $collisions[$prefix] = $group;
}
$hasBaseline = count(array_intersect(array_column($files, 'file'), $baselineCandidates)) > 0;
$manifest = [
    'generated_at_utc' => gmdate('c'),
    'database_connection' => false,
    'sql_execution' => false,
    'canonical_baseline_candidate_present' => $hasBaseline,
    'numbered_sql_files' => count(array_filter($files, static fn(array $file): bool => $file['numeric_prefix'] !== null)),
    'numeric_prefix_collisions' => (object) $collisions,
    'files' => $files,
    'warning' => 'Inventory only. Hashes do not prove migration order, compatibility, or safety.',
];
if (in_array('--json', $argv, true)) {
    echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
}
echo "Flexihub Billing System — read-only migration manifest\n";
echo "SQL files: " . count($files) . "\n";
echo "Database connection: none; SQL execution: none\n";
echo "Recognized baseline candidate: " . ($hasBaseline ? 'yes' : 'no') . "\n";
echo "Numbering collisions: " . count($collisions) . "\n";
foreach ($collisions as $prefix => $group) echo " - {$prefix}: " . implode(', ', $group) . "\n";
echo "\nSHA-256 inventory:\n";
foreach ($files as $file) echo $file['sha256'] . "  " . $file['file'] . "\n";
echo "\nInventory only; not an approved execution sequence.\n";
