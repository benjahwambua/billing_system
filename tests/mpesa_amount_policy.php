<?php
require_once __DIR__ . '/../includes/mpesa_amount_policy.php';

$cases = [
    ['whole KES amount accepted', 100, 100],
    ['numeric whole amount accepted', '250.00', 250],
    ['fractional KES amount rejected', 100.50, null],
    ['zero amount rejected', 0, null],
    ['negative amount rejected', -1, null],
    ['nonnumeric amount rejected', 'invalid', null],
    ['infinite amount rejected', INF, null],
];
$failed = 0;
foreach ($cases as [$name, $input, $expected]) {
    try {
        $actual = flexihubMpesaChargeAmount($input);
        $passed = $expected !== null && $actual === $expected;
    } catch (InvalidArgumentException $e) {
        $passed = $expected === null;
    }
    if (!$passed) {
        fwrite(STDERR, "FAIL: {$name}\n");
        $failed++;
    } else {
        echo "PASS: {$name}\n";
    }
}
if ($failed > 0) {
    fwrite(STDERR, "{$failed} M-Pesa amount policy regression test(s) failed.\n");
    exit(1);
}
echo count($cases) . " M-Pesa amount policy regression tests passed.\n";
