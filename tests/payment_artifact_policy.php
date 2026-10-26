<?php
require_once __DIR__ . '/../includes/payment_artifact_policy.php';

$cases = [
    ['matching invoice and amount', ['id'=>1,'invoice_id'=>12,'amount'=>'250.00'], ['id','invoice_id','amount'], 12, 250.00, true],
    ['wrong invoice rejected', ['id'=>1,'invoice_id'=>13,'amount'=>'250.00'], ['id','invoice_id','amount'], 12, 250.00, false],
    ['wrong amount rejected', ['id'=>1,'invoice_id'=>12,'amount'=>'249.00'], ['id','invoice_id','amount'], 12, 250.00, false],
    ['null invoice rejected when column exists', ['id'=>1,'invoice_id'=>null,'amount'=>'250.00'], ['id','invoice_id','amount'], 12, 250.00, false],
    ['null amount rejected when column exists', ['id'=>1,'invoice_id'=>12,'amount'=>null], ['id','invoice_id','amount'], 12, 250.00, false],
    ['legacy schema without optional comparison columns remains supported', ['id'=>1], ['id','payment_id','tenant_id'], 12, 250.00, true],
    ['one-cent amount tolerance accepted', ['id'=>1,'invoice_id'=>12,'amount'=>'250.01'], ['id','invoice_id','amount'], 12, 250.00, true],
    ['over-tolerance amount rejected', ['id'=>1,'invoice_id'=>12,'amount'=>'250.02'], ['id','invoice_id','amount'], 12, 250.00, false],
    ['invalid expected invoice rejected', ['id'=>1,'invoice_id'=>12,'amount'=>'250.00'], ['id','invoice_id','amount'], 0, 250.00, false],
    ['invalid expected amount rejected', ['id'=>1,'invoice_id'=>12,'amount'=>'0'], ['id','invoice_id','amount'], 12, 0.00, false],
];

$failed = 0;
foreach ($cases as [$name, $row, $columns, $invoiceId, $amount, $expected]) {
    $actual = flexihubPaymentArtifactMatches($row, $columns, $invoiceId, $amount);
    if ($actual !== $expected) {
        fwrite(STDERR, "FAIL: {$name}; expected=" . ($expected ? 'true' : 'false') . "; actual=" . ($actual ? 'true' : 'false') . "\n");
        $failed++;
    } else {
        echo "PASS: {$name}\n";
    }
}
if ($failed) {
    fwrite(STDERR, "{$failed} payment artifact policy test(s) failed.\n");
    exit(1);
}
echo count($cases) . " payment artifact policy tests passed.\n";
