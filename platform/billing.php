<?php
require_once '../includes/auth.php';requireLogin();requireHostContext();
$rows=[];$available=false;
$check=$conn->query("SHOW TABLES LIKE 'platform_invoices'");
if($check&&$check->num_rows){
    $available=true;
    $q=$conn->query("SELECT pi.id,pi.invoice_number,pi.issue_date,pi.due_date,pi.total_amount,pi.paid_amount,pi.status,pi.tenant_id,t.tenant_code,t.name FROM platform_invoices pi LEFT JOIN tenants t ON t.id=pi.tenant_id ORDER BY pi.id DESC LIMIT 500");
    if($q)while($r=$q->fetch_assoc()){$r['balance']=max(0,(float)$r['total_amount']-(float)$r['paid_amount']);$rows[]=$r;}
}
$pageTitle='Platform Billing';require_once '../includes/header.php';require_once '../includes/sidebar.php';?>
<div class="main-content"><div class="page-header"><div><h1>Platform Billing</h1><p>Flexihub hosting subscription invoices for ISP tenants. This ledger is separate from each tenant's customer invoices.</p></div></div>
<div class="dashboard-card"><?php if(!$available):?><div class="notice">The SaaS invoice schema has not been installed yet.</div><?php else:?><div class="table-responsive"><table><thead><tr><th>Invoice</th><th>Date</th><th>Due</th><th>Tenant</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th><th>Action</th></tr></thead><tbody>
<?php foreach($rows as $r):?><tr><td><?=e($r['invoice_number'])?></td><td><?=e($r['issue_date'])?></td><td><?=e($r['due_date'])?></td><td><?=e(($r['tenant_code']??'').' — '.($r['name']??''))?></td><td><?=e(formatMoney($r['total_amount']))?></td><td><?=e(formatMoney($r['paid_amount']))?></td><td><b><?=e(formatMoney($r['balance']))?></b></td><td><?=e(ucfirst($r['status']))?></td><td><?php if(in_array($r['status'],['unpaid','partial','overdue'],true)&&$r['balance']>0):?><a href="../platform_payments/add.php?invoice_id=<?=e($r['id'])?>" class="btn btn-sm">Record Payment</a><?php elseif($r['balance']<=0):?><span>Paid</span><?php else:?><span>Not payable</span><?php endif;?></td></tr><?php endforeach;?>
<?php if(!$rows):?><tr><td colspan="9" class="empty">No SaaS invoices found.</td></tr><?php endif;?></tbody></table></div><?php endif;?></div></div>
<?php require_once '../includes/footer.php';?>