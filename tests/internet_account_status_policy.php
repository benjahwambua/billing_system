<?php
require_once __DIR__ . '/../includes/internet_account_status_policy.php';

$submittedStatuses = ['active', 'pending', 'suspended', 'expired', 'inactive', '', null];
$failed = 0;
foreach ($submittedStatuses as $submitted) {
    $actual = flexihubInitialInternetAccountStatus($submitted);
    if ($actual !== 'pending') {
        fwrite(STDERR, 'FAIL: submitted status ' . var_export($submitted, true) . ' produced ' . var_export($actual, true) . PHP_EOL);
        $failed++;
    } else {
        echo 'PASS: submitted status ' . var_export($submitted, true) . ' cannot bypass pending activation' . PHP_EOL;
    }
}
if ($failed > 0) {
    fwrite(STDERR, $failed . " internet account status regression test(s) failed." . PHP_EOL);
    exit(1);
}
echo count($submittedStatuses) . " internet account status regression tests passed." . PHP_EOL;
