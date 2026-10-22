<?php
require_once __DIR__ . '/../includes/payment_input_validation.php';

$cases = [
    ['valid cash payment', 'cash', '2026-10-22', 'REF-1', '2026-10-22', []],
    ['valid supported methods are accepted', 'mpesa', '2026-10-21', '', '2026-10-22', []],
    ['unsupported payment method rejected', 'crypto', '2026-10-22', '', '2026-10-22', ['valid payment method']],
    ['malformed date rejected', 'cash', '2026-02-30', '', '2026-10-22', ['valid payment date']],
    ['future payment date rejected', 'bank', '2026-10-23', '', '2026-10-22', ['future']],
    ['reference longer than 100 bytes rejected', 'card', '2026-10-22', str_repeat('X', 101), '2026-10-22', ['100 characters']],
];

$failed = 0;
foreach ($cases as [$name, $method, $date, $reference, $today, $expectedFragments]) {
    $errors = flexihubValidateManualPaymentInput($method, $date, $reference, $today);
    $ok = empty($expectedFragments) ? count($errors) === 0 : true;
    foreach ($expectedFragments as $fragment) {
        $found = false;
        foreach ($errors as $error) {
            if (stripos($error, $fragment) !== false) { $found = true; break; }
        }
        if (!$found) { $ok = false; }
    }
    if (!$ok) {
        fwrite(STDERR, 'FAIL: ' . $name . '; errors=' . implode(' | ', $errors) . PHP_EOL);
        $failed++;
    } else {
        echo 'PASS: ' . $name . PHP_EOL;
    }
}
if ($failed > 0) {
    fwrite(STDERR, $failed . " manual payment input regression test(s) failed." . PHP_EOL);
    exit(1);
}
echo count($cases) . " manual payment input regression tests passed." . PHP_EOL;
