<?php
require_once '../includes/auth.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('payments','view');

$tenantId=(int)getCurrentTenantId();
$rows=[];

$stmt=$conn->prepare(
    "SELECT p.*,
            i.invoice_number,
            COALESCE(p.customer_id, i.customer_id) AS resolved_customer_id,
            c.customer_number,
            c.first_name,
            c.last_name
     FROM payments p
     LEFT JOIN invoices i
       ON i.id=p.invoice_id AND i.tenant_id=p.tenant_id
     LEFT JOIN customers c
       ON c.id=COALESCE(p.customer_id,i.customer_id)
      AND c.tenant_id=p.tenant_id
     WHERE p.tenant_id=?
     ORDER BY p.id DESC
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

$pageTitle='Payments';
require_once '../includes/header.php';
?>
<div class="dashboard-card">
    <div class="page-header">
        <div>
            <h2>Payments</h2>
            <p>Customer collections and payment records.</p>
        </div>
        <?php if(userCan('payments','create')): ?>
            <a class="btn btn-primary" href="../payments/add.php">Record Payment</a>
        <?php endif; ?>
    </div>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Payment</th>
                    <th>Invoice</th>
                    <th>Customer</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Date</th>
                    <th>Reference</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($rows as $row):
                $customerName=trim(($row['first_name']??'').' '.($row['last_name']??''));
                ?>
                <tr>
                    <td><?=e($row['payment_number']??$row['reference']??$row['id'])?></td>
                    <td><?=e($row['invoice_number']??$row['invoice_id']??'')?></td>
                    <td>
                        <?=e($row['customer_number']??$row['resolved_customer_id']??'')?>
                        <?php if($customerName): ?><br><small><?=e($customerName)?></small><?php endif; ?>
                    </td>
                    <td><?=e(formatMoney($row['amount']??0))?></td>
                    <td><?=e($row['payment_method']??$row['method']??'')?></td>
                    <td><?=e($row['payment_date']??$row['created_at']??'')?></td>
                    <td><?=e($row['reference']??$row['transaction_reference']??'')?></td>
                </tr>
            <?php endforeach; ?>
            <?php if(!$rows): ?><tr><td colspan="7">No payments found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>