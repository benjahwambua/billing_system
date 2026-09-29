<?php
require_once '../includes/auth.php';
requireActiveUser();
$tenantId=requireTenantContext();
requireModulePermission('revenue','view');
require_once '../includes/billing_workflow.php';
global $conn;

$rows=[];$total=0.0;$today=0.0;$month=0.0;
$stmt=$conn->prepare("
    SELECT p.id,p.payment_number,p.amount,p.payment_method,p.reference,p.payment_date,
           i.invoice_number,c.customer_number,
           COALESCE(NULLIF(TRIM(CONCAT_WS(' ',c.first_name,c.last_name)),''),c.name,c.customer_number) AS customer_name
    FROM payments p
    LEFT JOIN invoices i ON i.id=p.invoice_id AND i.tenant_id=p.tenant_id
    LEFT JOIN customers c ON c.id=i.customer_id AND c.tenant_id=i.tenant_id
    WHERE p.tenant_id=?
    ORDER BY p.id DESC
    LIMIT 500
");
if($stmt){$stmt->bind_param('i',$tenantId);$stmt->execute();$r=$stmt->get_result();while($row=$r->fetch_assoc()){$rows[]=$row;$amount=(float)($row['amount']??0);$total+=$amount;$date=substr((string)($row['payment_date']??''),0,10);if($date===systemDate())$today+=$amount;if(substr($date,0,7)===substr(systemDate(),0,7))$month+=$amount;}$stmt->close();}

$pageTitle='Revenue';require_once '../includes/header.php';
?>
<div class="dashboard-card">
<h2>Revenue</h2><p>Customer collections for this tenant. Tenant wallet activity is reported separately.</p>
<div style="display:flex;gap:16px;flex-wrap:wrap;margin:18px 0">
<div class="dashboard-card" style="margin:0;min-width:180px"><small>All Time</small><h3><?=e(formatMoney($total))?></h3></div>
<div class="dashboard-card" style="margin:0;min-width:180px"><small>Today</small><h3><?=e(formatMoney($today))?></h3></div>
<div class="dashboard-card" style="margin:0;min-width:180px"><small>This Month</small><h3><?=e(formatMoney($month))?></h3></div>
</div>
<div class="table-responsive"><table><thead><tr><th>Date</th><th>Payment</th><th>Customer</th><th>Invoice</th><th>Method</th><th>Reference</th><th>Amount</th></tr></thead><tbody>
<?php foreach($rows as $row):?><tr><td><?=e($row['payment_date']??'')?></td><td><?=e($row['payment_number']??'')?></td><td><?=e(trim(($row['customer_number']??'').' '.($row['customer_name']??'')) )?></td><td><?=e($row['invoice_number']??'')?></td><td><?=e($row['payment_method']??'')?></td><td><?=e($row['reference']??'')?></td><td><?=e(formatMoney($row['amount']??0))?></td></tr><?php endforeach;?>
<?php if(!$rows):?><tr><td colspan="7">No customer revenue recorded yet.</td></tr><?php endif;?></tbody></table></div>
</div>
<?php require_once '../includes/footer.php'; ?>