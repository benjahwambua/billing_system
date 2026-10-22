<?php
require_once __DIR__ . '/../includes/service_activation_policy.php';

$cases = [
    ['first failure retries after five minutes', 1, 'pending', 5],
    ['second failure retries after ten minutes', 2, 'pending', 10],
    ['third failure retries after fifteen minutes', 3, 'pending', 15],
    ['fourth failure retries after twenty minutes', 4, 'pending', 20],
    ['fifth failure exhausts retry budget', 5, 'failed', 0],
    ['attempt count cannot be negative', -3, 'pending', 0],
    ['delay caps at one hour with a larger retry budget', 20, 'failed', 0],
];
$failed = 0;
foreach ($cases as [$name, $attempts, $status, $delay]) {
    $actual = flexihubServiceActivationRetryDecision($attempts, $attempts === 20 ? 25 : 5);
    if ($actual['status'] !== $status || $actual['delay_minutes'] !== $delay) {
        fwrite(STDERR, "FAIL: {$name}; got {$actual['status']} / {$actual['delay_minutes']} minutes\n");
        $failed++;
    } else {
        echo "PASS: {$name}\n";
    }
}
$cap = flexihubServiceActivationRetryDecision(20, 25);
if ($cap['delay_minutes'] !== 60 || $cap['status'] !== 'pending') {
    fwrite(STDERR, "FAIL: retry delay cap\n");
    $failed++;
} else {
    echo "PASS: retry delay is capped at sixty minutes\n";
}
if ($failed) {
    fwrite(STDERR, "{$failed} service activation retry test(s) failed.\n");
    exit(1);
}
echo "8 service activation retry regression tests passed.\n";
