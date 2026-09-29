<?php
require_once '../includes/auth.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('billing','view');

$tenantId=(int)getCurrentTenantId();
$rows=[];

$stmt=$conn->prepare(
    "SELECT at.*,
            p.payment_number,
            i.invoice_number,
            c.customer_number,
            c.first_name,
            c.last_name
     FROM account_transactions at
     LEFT JOIN payments p
       ON p.id=at.payment_id AND p.tenant_id=at.tenant_id
     LEFT JOIN invoices i
       ON i.id=COALESCE(at.invoice_id,p.invoice_id) AND i.tenant_id=at.tenant_id
     LEFT JOIN customers c
       ON c.id=COALESCE(at.customer_id,i.customer_id)
      AND c.tenant_id=at.tenant_id
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