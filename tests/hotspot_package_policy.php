<?php
require_once __DIR__ . '/../includes/hotspot_package_policy.php';

$cases = [
    ['active accepted', 'active', true],
    ['inactive accepted', 'inactive', true],
    ['case-insensitive active accepted', 'ACTIVE', true],
    ['unknown status rejected', 'deleted', false],
    ['blank status rejected', '', false],
    ['crafted numeric status rejected', '1', false],
];
$failed = 0;
foreach ($cases as [$name, $status, $expected]) {
    $actual = flexihubIsValidHotspotPackageStatus($status);
    if ($actual !== $expected) {
        fwrite(STDERR, 'FAIL: ' . $name . PHP_EOL);
        $failed++;
    } else {
        echo 'PASS: ' . $name . PHP_EOL;
    }
}
if ($failed > 0) {
    fwrite(STDERR, $failed . " hotspot package status regression test(s) failed." . PHP_EOL);
    exit(1);
}
echo count($cases) . " hotspot package status regression tests passed." . PHP_EOL;
