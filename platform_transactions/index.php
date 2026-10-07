<?php
require_once '../includes/auth.php';requireLogin();requireHostContext();
$rows=[];$available=false;$total=0;
$check=$conn->query("SHOW TABLES LIKE 'platform_payments'");
if($check&&$check->num_rows){
    $available=true;
    $q=$conn->query("SELECT pp.id,pp.amount,pp.payment_date,pp.payment_method,pp.reference,pp.provider,pp.external_transaction_id,pp.status,pp.tenant_id,pi.invoice_number FROM platform_payments pp LEFT JOIN platform_invoices pi ON pi.id=pp.invoice_id ORDER BY pp.id DESC LIMIT 500");
    if($q)while($r=$q->fetch_assoc()){$rows[]=$r;$total+=(float)($r['amount']??0);}
}
$pageTitle='Platform Transactions';require_once '../includes/header.php';require_once '../includes/sidebar.php';?>
<div class="main-content"><div class="page-header"><div><h1>Platform Transactions</h1><p>Payments made by tenants for Flexihub hosting subscriptions. ISP customer payments remain in the tenant billing ledger.</p></div></div>
<div class="stats-grid"><div class="stat-card"><h3><?=e(formatMoney($total))?></h3><p>Displayed SaaS Payments</p></div><div class="stat-card"><h3><?=e(count($rows))?></h3><p>Recent Payments</p></div></div>
<div class="dashboard-card" style="margin-top:20px"><?php if(!$available):?><div class="notice">The SaaS payment schema has not been installed yet.</div><?php else:?><div class="table-responsive"><table><thead><tr><th>Date</th><th>Tenant</th><th>Invoice</th><th>Reference</th><th>Method</th><th>Status</th><th>Amount</th></tr></thead><tbody>
<?php foreach($rows as $r):?><tr><td><?=e($r['payment_date'])?></td><td><?=e($r['tenant_id'])?></td><td><?=e($r['invoice_number']??'—')?></td><td><?=e($r['reference']??$r['external_transaction_id']??$r['id'])?></td><td><?=e($r['payment_method'])?></td><td><?=e(ucfirst($r['status']))?></td><td><?=e(formatMoney($r['amount']))?></td></tr><?php endforeach;?>
<?php if(!$rows):?><tr><td colspan="7" class="empty">No SaaS payments found.</td></tr><?php endif;?></tbody></table></div><?php endif;?></div></div>
<?php require_once '../includes/footer.php';?>