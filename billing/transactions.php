<?php
require_once '../includes/auth.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('billing','view');

$tenantId=(int)getCurrentTenantId();
$rows=[];

$transactionColumns = [];
$columnResult = $conn->query("SHOW COLUMNS FROM account_transactions");
if ($columnResult) {
    while ($columnRow = $columnResult->fetch_assoc()) $transactionColumns[] = $columnRow['Field'];
    $columnResult->free();
}
$hasPaymentId = in_array('payment_id', $transactionColumns, true);
$hasReferenceIdentity = in_array('reference_type', $transactionColumns, true)
    && in_array('reference_id', $transactionColumns, true);
$hasInvoiceId = in_array('invoice_id', $transactionColumns, true);

$paymentJoin = '';
$paymentNumberExpr = 'NULL AS payment_number';
$paymentInvoiceExpr = 'NULL';
if ($hasPaymentId) {
    $paymentJoin = ' LEFT JOIN payments p ON p.id=at.payment_id AND p.tenant_id=at.tenant_id';
    $paymentNumberExpr = 'p.payment_number';
    $paymentInvoiceExpr = 'p.invoice_id';
} elseif ($hasReferenceIdentity) {
    $paymentJoin = " LEFT JOIN payments p ON p.id=at.reference_id AND at.reference_type='payment' AND p.tenant_id=at.tenant_id";
    $paymentNumberExpr = 'p.payment_number';
    $paymentInvoiceExpr = 'p.invoice_id';
}
$invoiceIdExpr = $hasInvoiceId
    ? "COALESCE(at.invoice_id, {$paymentInvoiceExpr})"
    : $paymentInvoiceExpr;
$invoiceJoin = $invoiceIdExpr !== 'NULL'
    ? " LEFT JOIN invoices i ON i.id={$invoiceIdExpr} AND i.tenant_id=at.tenant_id"
    : ' LEFT JOIN invoices i ON 1=0';

$stmt=$conn->prepare(
    "SELECT at.*,
            {$paymentNumberExpr},
            i.invoice_number,
            c.customer_number,
            c.first_name,
            c.last_name
     FROM account_transactions at
     {$paymentJoin}
     {$invoiceJoin}
     LEFT JOIN customers c
       ON c.id=at.customer_id AND c.tenant_id=at.tenant_id
     WHERE at.tenant_id=?
     ORDER BY at.id DESC
     LIMIT 500"
);

if($stmt){
    $stmt->bind_param('i',$tenantId);
    if($stmt->execute()){
        $result=$stmt->get_result();
        while($row=$result->fetch_assoc()) $rows[]=$row;
    }
    $stmt->close();
}

$pageTitle='Transactions';
require_once '../includes/header.php';
?>
<div class="dashboard-card">
    <div class="page-header">
        <div>
            <h2>Transactions</h2>
            <p>Tenant-scoped financial transaction ledger.</p>
        </div>
    </div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr><th>Date</th><th>Reference</th><th>Customer</th><th>Invoice</th><th>Payment</th><th>Type</th><th>Amount</th><th>Description</th></tr>
            </thead>
            <tbody>
            <?php foreach($rows as $row):
                $customerName=trim(($row['first_name']??'').' '.($row['last_name']??''));
                $date=$row['transaction_date']??$row['created_at']??'';
                $type=$row['transaction_type']??$row['type']??'';
            ?>
                <tr>
                    <td><?=e($date)?></td>
                    <td><?=e($row['reference']??$row['transaction_number']??$row['id'])?></td>
                    <td><?=e($row['customer_number']??$row['customer_id']??'')?><?php if($customerName): ?><br><small><?=e($customerName)?></small><?php endif; ?></td>
                    <td><?=e($row['invoice_number']??$row['invoice_id']??'')?></td>
                    <td><?=e($row['payment_number']??$row['payment_id']??'')?></td>
                    <td><?=e(ucwords(str_replace('_',' ',$type)))?></td>
                    <td><?=e(formatMoney($row['amount']??0))?></td>
                    <td><?=e($row['description']??'')?></td>
                </tr>
            <?php endforeach; ?>
            <?php if(!$rows): ?><tr><td colspan="8">No transactions found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>