<?php
require_once '../includes/auth.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('receipts','view');

$tenantId=(int)getCurrentTenantId();
$rows=[];

$stmt=$conn->prepare(
    "SELECT r.*,
            p.payment_number,
            COALESCE(r.customer_id,p.customer_id,i.customer_id) AS resolved_customer_id,
            c.customer_number,
            c.first_name,
            c.last_name
     FROM receipts r
     LEFT JOIN payments p
       ON p.id=r.payment_id AND p.tenant_id=r.tenant_id
     LEFT JOIN invoices i
       ON i.id=p.invoice_id AND i.tenant_id=p.tenant_id
     LEFT JOIN customers c
       ON c.id=COALESCE(r.customer_id,p.customer_id,i.customer_id)
      AND c.tenant_id=r.tenant_id
     WHERE r.tenant_id=?
     ORDER BY r.id DESC
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

$pageTitle='Receipts';
require_once '../includes/header.php';
?>
<div class="dashboard-card">
    <div class="page-header">
        <div>
            <h2>Receipts</h2>
            <p>Receipts generated from customer payments.</p>
        </div>
    </div>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Receipt</th>
                    <th>Payment</th>
                    <th>Customer</th>
                    <th>Amount</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($rows as $row):
                $customerName=trim(($row['first_name']??'').' '.($row['last_name']??''));
                ?>
                <tr>
                    <td><?=e($row['receipt_number']??$row['number']??$row['id'])?></td>
                    <td><?=e($row['payment_number']??$row['payment_id']??'')?></td>
                    <td>
                        <?=e($row['customer_number']??$row['resolved_customer_id']??'')?>
                        <?php if($customerName): ?><br><small><?=e($customerName)?></small><?php endif; ?>
                    </td>
                    <td><?=e(formatMoney($row['amount']??0))?></td>
                    <td><?=e($row['receipt_date']??$row['created_at']??'')?></td>
                </tr>
            <?php endforeach; ?>
            <?php if(!$rows): ?><tr><td colspan="5">No receipts found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>