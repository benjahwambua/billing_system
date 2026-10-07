<?php
require_once '../includes/auth.php';requireLogin();requireHostContext();
$rows=[];$q=$conn->query("SELECT t.id,t.tenant_code,t.name,t.status,
 COALESCE((SELECT SUM(COALESCE(i.total_amount,i.total,i.amount,0)) FROM invoices i WHERE i.tenant_id=t.id AND LOWER(COALESCE(i.status,''))<>'cancelled'),0) invoiced,
 COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.tenant_id=t.id),0) collected
 FROM tenants t ORDER BY t.id DESC LIMIT 500");
if($q)while($r=$q->fetch_assoc()){$r['balance']=max(0,(float)$r['invoiced']-(float)$r['collected']);$rows[]=$r;}
$pageTitle='Platform Billing';require_once '../includes/header.php';require_once '../includes/sidebar.php';?>
<div class="main-content"><div class="page-header"><div><h1>Platform Billing</h1><p>Tenant billing readiness dashboard using the current billing engine. SaaS subscription billing is kept separate until its platform subscription data model is enabled.</p></div><a class="btn" href="../platform_revenue/index.php">Revenue Overview</a></div>
<div class="dashboard-card"><div class="table-responsive"><table><thead><tr><th>Tenant</th><th>Status</th><th>Tenant Invoiced</th><th>Tenant Collected</th><th>Tenant Balance</th></tr></thead><tbody>
<?php foreach($rows as $r):?><tr><td><?=e(($r['tenant_code']??'').' — '.($r['name']??''))?></td><td><?=e($r['status']??'')?></td><td><?=e(formatMoney($r['invoiced']))?></td><td><?=e(formatMoney($r['collected']))?></td><td><b><?=e(formatMoney($r['balance']))?></b></td></tr><?php endforeach;?>
<?php if(!$rows):?><tr><td colspan="5" class="empty">No tenant billing records found.</td></tr><?php endif;?></tbody></table></div></div></div>
<?php require_once '../includes/footer.php';?>