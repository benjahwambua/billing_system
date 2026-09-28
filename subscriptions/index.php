<?php
require_once '../includes/auth.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('subscriptions','view');
require_once '../includes/billing_workflow.php';

$tid=(int)getCurrentTenantId();
$rows=[];
$q=$conn->prepare("SELECT s.id,s.start_date,s.end_date,s.status,s.invoice_id,s.payment_id,
                          c.customer_number,c.first_name,c.last_name,
                          ia.account_number,ia.username,
                          ip.name plan_name
                   FROM service_subscriptions s
                   JOIN customers c ON c.id=s.customer_id AND c.tenant_id=s.tenant_id
                   JOIN internet_accounts ia ON ia.id=s.account_id AND ia.tenant_id=s.tenant_id
                   LEFT JOIN internet_plans ip ON ip.id=s.plan_id AND ip.tenant_id=s.tenant_id
                   WHERE s.tenant_id=? ORDER BY s.id DESC LIMIT 300");
if($q){$q->bind_param('i',$tid);$q->execute();$r=$q->get_result();while($x=$r->fetch_assoc())$rows[]=$x;$q->close();}

$pageTitle='Subscriptions';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<div class="main-content">
<div class="page-header"><div><h1>Subscriptions</h1><p>Track paid service periods from customer accounts through billing.</p></div>
<?php if(userCan('invoices','create')):?><a class="btn btn-primary" href="../invoices/add.php">Create Invoice</a><?php endif;?></div>
<div class="card" style="padding:20px"><div class="table-responsive"><table>
<thead><tr><th>Customer</th><th>Account</th><th>Plan</th><th>Start</th><th>End</th><th>Status</th><th>Invoice</th><th>Payment</th></tr></thead><tbody>
<?php foreach($rows as $x):?><tr>
<td><?=e($x['customer_number'].' — '.trim($x['first_name'].' '.$x['last_name']))?></td>
<td><?=e($x['account_number']?:$x['username'])?></td>
<td><?=e($x['plan_name']?:'—')?></td>
<td><?=e($x['start_date'])?></td><td><?=e($x['end_date'])?></td><td><?=e($x['status'])?></td>
<td><?=e($x['invoice_id']?:'—')?></td><td><?=e($x['payment_id']?:'—')?></td>
</tr><?php endforeach;?>
<?php if(!$rows):?><tr><td colspan="8">No paid subscriptions recorded yet. A subscription is created when a payment renews an internet account.</td></tr><?php endif;?>
</tbody></table></div></div></div>
<?php require_once '../includes/footer.php';?>