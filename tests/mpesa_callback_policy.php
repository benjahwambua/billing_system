<?php
require_once __DIR__ . '/../includes/mpesa_callback_policy.php';

$cases = [
    ['matching receipt, amount and phone accepted', 'QWE123', 100.00, 100.00, '254712345678', '254712345678', true],
    ['one cent amount tolerance accepted', 'QWE123', 100.01, 100.00, '254712345678', '254712345678', true],
    ['amount mismatch rejected', 'QWE123', 100.02, 100.00, '254712345678', '254712345678', false],
    ['missing receipt rejected', '', 100.00, 100.00, '254712345678', '254712345678', false],
    ['missing amount rejected', 'QWE123', null, 100.00, '254712345678', '254712345678', false],
    ['wrong phone rejected', 'QWE123', 100.00, 100.00, '254700000000', '254712345678', false],
    ['missing callback phone metadata rejected', 'QWE123', 100.00, 100.00, '', '254712345678', false],
    ['missing expected phone rejected', 'QWE123', 100.00, 100.00, '254712345678', '', false],
    ['different phone format is not silently normalized', 'QWE123', 100.00, 100.00, '0712345678', '254712345678', false],
    ['invalid expected amount rejected', 'QWE123', 100.00, 'invalid', '254712345678', '254712345678', false],
];
$failed = 0;
foreach ($cases as [$name, $receipt, $paidAmount, $expectedAmount, $paidPhone, $expectedPhone, $expected]) {
    $actual = flexihubMpesaCallbackMatchesTransaction($receipt, $paidAmount, $expectedAmount, $paidPhone, $expectedPhone);
    if ($actual !== $expected) {
        fwrite(STDERR, "FAIL: {$name}\n");
        $failed++;
    } else {
        echo "PASS: {$name}\n";
    }
}
if ($failed > 0) {
    fwrite(STDERR, "{$failed} M-Pesa callback regression test(s) failed.\n");
    exit(1);
}
echo count($cases) . " M-Pesa callback regression tests passed.\n";
