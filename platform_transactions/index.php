<?php
require_once '../includes/auth.php';requireLogin();requireHostContext();
$rows=[];$q=$conn->query("SELECT p.id,p.amount,p.payment_date,p.payment_method,p.reference,p.transaction_id,
 p.tenant_id,t.tenant_code,t.name tenant_name,c.customer_number,c.first_name,c.last_name
 FROM payments p
 LEFT JOIN tenants t ON t.id=p.tenant_id
 LEFT JOIN customers c ON c.id=p.customer_id AND c.tenant_id=p.tenant_id
 ORDER BY p.id DESC LIMIT 500");
if($q)while($r=$q->fetch_assoc())$rows[]=$r;
$total=0;foreach($rows as $r)$total+=(float)($r['amount']??0);
$pageTitle='Platform Transactions';require_once '../includes/header.php';require_once '../includes/sidebar.php';?>
<div class="main-content"><div class="page-header"><div><h1>Platform Transactions</h1><p>Host audit view of recorded tenant payment transactions. These are not Flexihub platform subscription charges.</p></div></div>
<div class="stats-grid"><div class="stat-card"><h3><?=e(formatMoney($total))?></h3><p>Displayed Collections</p></div><div class="stat-card"><h3><?=e(count($rows))?></h3><p>Recent Transactions</p></div></div>
<div class="dashboard-card" style="margin-top:20px"><div class="table-responsive"><table><thead><tr><th>Date</th><th>Tenant</th><th>Customer</th><th>Reference</th><th>Method</th><th>Amount</th></tr></thead><tbody>
<?php foreach($rows as $r):?><tr><td><?=e($r['payment_date']??'')?></td><td><?=e(($r['tenant_code']??'').' — '.($r['tenant_name']??''))?></td><td><?=e(trim(($r['customer_number']??'').' '.($r['first_name']??'').' '.($r['last_name']??'')) ?: '—')?></td><td><?=e($r['reference']??$r['transaction_id']??$r['id'])?></td><td><?=e($r['payment_method']??'—')?></td><td><?=e(formatMoney($r['amount']??0))?></td></tr><?php endforeach;?>
<?php if(!$rows):?><tr><td colspan="6" class="empty">No payment transactions found.</td></tr><?php endif;?></tbody></table></div></div></div>
<?php require_once '../includes/footer.php';?>