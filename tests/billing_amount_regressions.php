<?php
require_once __DIR__ . '/../includes/invoice_amounts.php';

$cases = [
    ['zero payment is unpaid', 100.00, 0.00, 'unpaid', 100.00, false],
    ['partial payment remains partial', 100.00, 35.50, 'partial', 64.50, false],
    ['exact payment is paid', 100.00, 100.00, 'paid', 0.00, true],
    ['overpaid amount never creates negative balance', 100.00, 125.00, 'paid', 0.00, true],
    ['one cent short remains partial', 100.00, 99.99, 'partial', 0.01, false],
    ['floating point tolerance accepts tiny remainder', 100.00, 99.999995, 'paid', 0.000005, true],
    ['zero total is treated as fully paid', 0.00, 0.00, 'unpaid', 0.00, true],
];
$failed = 0;
foreach ($cases as [$name, $total, $paid, $status, $balance, $fullyPaid]) {
    $actualStatus = flexihubInvoicePaymentStatus($total, $paid);
    $actualBalance = flexihubInvoiceOutstandingBalance($total, $paid);
    $actualFullyPaid = flexihubInvoiceIsFullyPaid($total, $paid);
    $ok = $actualStatus === $status && abs($actualBalance - $balance) < 0.000001 && $actualFullyPaid === $fullyPaid;
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$name}; status={$actualStatus}; balance={$actualBalance}; fully_paid=" . ($actualFullyPaid ? 'yes' : 'no') . "\n");
        $failed++;
    } else {
        echo "PASS: {$name}\n";
    }
}
if ($failed > 0) {
    fwrite(STDERR, "{$failed} invoice amount regression test(s) failed.\n");
    exit(1);
}
echo count($cases) . " invoice amount regression tests passed.\n";
