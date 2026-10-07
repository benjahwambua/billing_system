<?php
require_once '../includes/auth.php';requireLogin();requireHostContext();
$currency=function_exists('getCurrency')?getCurrency():'KES';
$totals=['invoiced'=>0.0,'collected'=>0.0,'expenses'=>0.0,'outstanding'=>0.0];
$stats=[];
$q=$conn->query("SELECT t.id,t.tenant_code,t.name,t.status,
 COALESCE((SELECT SUM(COALESCE(i.total_amount,i.total,i.amount,0)) FROM invoices i WHERE i.tenant_id=t.id AND LOWER(COALESCE(i.status,''))<>'cancelled'),0) invoiced,
 COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.tenant_id=t.id),0) collected,
 COALESCE((SELECT SUM(e.amount) FROM expenses e WHERE e.tenant_id=t.id),0) expenses
 FROM tenants t ORDER BY t.id DESC LIMIT 500");
if($q)while($r=$q->fetch_assoc()){
 $r['outstanding']=max(0,(float)$r['invoiced']-(float)$r['collected']);$stats[]=$r;
 foreach(['invoiced','collected','expenses','outstanding'] as $k)$totals[$k]+=(float)$r[$k];
}
$pageTitle='Platform Revenue';require_once '../includes/header.php';require_once '../includes/sidebar.php';?>
<div class="main-content"><div class="page-header"><div><h1>Platform Revenue</h1><p>Host-wide financial view across all ISP tenants. Tenant funds remain tenant-scoped.</p></div></div>
<div class="stats-grid">
<div class="stat-card"><h3><?=e(formatMoney($totals['invoiced']))?></h3><p>Total Tenant Invoiced</p></div>
<div class="stat-card"><h3><?=e(formatMoney($totals['collected']))?></h3><p>Total Tenant Collections</p></div>
<div class="stat-card"><h3><?=e(formatMoney($totals['outstanding']))?></h3><p>Total Tenant Receivables</p></div>
<div class="stat-card"><h3><?=e(formatMoney($totals['expenses']))?></h3><p>Total Recorded Expenses</p></div>
</div>
<div class="dashboard-card" style="margin-top:20px"><h2>Tenant Financial Summary</h2><div class="table-responsive"><table><thead><tr><th>Tenant</th><th>Status</th><th>Invoiced</th><th>Collected</th><th>Outstanding</th><th>Expenses</th></tr></thead><tbody>
<?php foreach($stats as $r):?><tr><td><?=e(($r['tenant_code']??'').' — '.($r['name']??''))?></td><td><?=e($r['status']??'')?></td><td><?=e(formatMoney($r['invoiced']))?></td><td><?=e(formatMoney($r['collected']))?></td><td><b><?=e(formatMoney($r['outstanding']))?></b></td><td><?=e(formatMoney($r['expenses']))?></td></tr><?php endforeach;?>
<?php if(!$stats):?><tr><td colspan="6" class="empty">No tenants found.</td></tr><?php endif;?></tbody></table></div></div></div>
<?php require_once '../includes/footer.php';?>