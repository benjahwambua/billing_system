<?php
require_once __DIR__ . '/../includes/auth.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('customers','view');

$id=(int)($_GET['id']??0);
$customer=getCustomer($id);
if(!$customer){http_response_code(404);exit('Customer not found.');}

$accounts=getCustomerAccounts($id);
$invoices=[];
$payments=[];
$tid=(int)getCurrentTenantId();

if(flexihubTableHasColumn('invoices','tenant_id')){
    $q=$conn->prepare("SELECT * FROM invoices WHERE customer_id=? AND tenant_id=? ORDER BY id DESC LIMIT 50");
    if($q){$q->bind_param('ii',$id,$tid);$q->execute();$r=$q->get_result();while($x=$r->fetch_assoc())$invoices[]=$x;$q->close();}
}
if(flexihubTableHasColumn('payments','tenant_id')){
    $q=$conn->prepare("SELECT * FROM payments WHERE customer_id=? AND tenant_id=? ORDER BY id DESC LIMIT 50");
    if($q){$q->bind_param('ii',$id,$tid);$q->execute();$r=$q->get_result();while($x=$r->fetch_assoc())$payments[]=$x;$q->close();}
}

$pageTitle='Customer Profile';
require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/sidebar.php';
?>
<div class="main-content">
<div class="page-header">
 <div><h1><?=e(trim(($customer['first_name']??'').' '.($customer['last_name']??'')) ?: ($customer['customer_number']??'Customer'))?></h1><p><?=e($customer['customer_number']??'')?></p></div>
 <div><?php if(userCan('customers','edit')):?><a class="btn btn-primary" href="edit.php?id=<?=$id?>">Edit Customer</a><?php endif;?> <a class="btn btn-secondary" href="index.php">Back</a></div>
</div>
<div class="stats-grid">
 <div class="stat-card"><h3><?=e(count($accounts))?></h3><p>Internet Accounts</p></div>
 <div class="stat-card"><h3><?=e(count($invoices))?></h3><p>Invoices</p></div>
 <div class="stat-card"><h3><?=e(count($payments))?></h3><p>Payments</p></div>
</div>
<div class="card" style="padding:20px;margin-bottom:20px">
 <h2>Customer Details</h2>
 <div class="detail-grid">
  <div><strong>Customer Number</strong><span><?=e($customer['customer_number']??'—')?></span></div>
  <div><strong>Status</strong><span><?=e(ucfirst($customer['status']??'active'))?></span></div>
  <div><strong>Phone</strong><span><?=e($customer['phone']??'—')?></span></div>
  <div><strong>Email</strong><span><?=e($customer['email']??'—')?></span></div>
  <div class="full"><strong>Address / Location</strong><span><?=e($customer['address']??'—')?></span></div>
 </div>
</div>
<div class="card" style="padding:20px;margin-bottom:20px"><h2>Internet Accounts</h2>
<div class="table-responsive"><table class="data-table"><thead><tr><th>Account</th><th>Plan</th><th>Status</th><th>Expiry</th></tr></thead><tbody>
<?php foreach($accounts as $a):?><tr><td><?=e($a['account_number']??$a['id'])?></td><td><?=e($a['plan_name']??$a['plan_id']??'—')?></td><td><?=e(ucfirst(getInternetAccountStatus($a)))?></td><td><?=e($a['expiry_date']??$a['billing_date']??'—')?></td></tr><?php endforeach;if(!$accounts):?><tr><td colspan="4">No internet accounts assigned.</td></tr><?php endif;?>
</tbody></table></div></div>
<div class="card" style="padding:20px"><h2>Recent Billing</h2>
<div class="table-responsive"><table class="data-table"><thead><tr><th>Type</th><th>Reference</th><th>Amount</th><th>Date</th><th>Status</th></tr></thead><tbody>
<?php foreach($invoices as $x):?><tr><td>Invoice</td><td><?=e($x['invoice_number']??$x['id'])?></td><td><?=e(formatMoney($x['total_amount']??$x['amount']??0))?></td><td><?=e($x['invoice_date']??$x['created_at']??'—')?></td><td><?=e($x['status']??'')?></td></tr><?php endforeach;?>
<?php foreach($payments as $x):?><tr><td>Payment</td><td><?=e($x['payment_number']??$x['reference']??$x['id'])?></td><td><?=e(formatMoney($x['amount']??0))?></td><td><?=e($x['payment_date']??$x['created_at']??'—')?></td><td><?=e($x['status']??'completed')?></td></tr><?php endforeach;?>
<?php if(!$invoices&&!$payments):?><tr><td colspan="5">No billing records found.</td></tr><?php endif;?>
</tbody></table></div></div>
</div>
<style>
.main-content{padding:24px}.page-header{display:flex;justify-content:space-between;align-items:center;gap:15px;margin-bottom:24px}.page-header h1{margin:0 0 5px}.page-header p{margin:0;color:#6b7280}.stats-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:20px}.stat-card{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:18px}.stat-card h3{margin:0 0 5px;font-size:26px}.stat-card p{margin:0;color:#6b7280}.card{background:#fff;border:1px solid #e5e7eb;border-radius:10px}.card h2{margin-top:0}.detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}.detail-grid div{display:flex;flex-direction:column;gap:5px}.detail-grid .full{grid-column:1/-1}.detail-grid span{color:#4b5563}.data-table{width:100%;border-collapse:collapse}.data-table th,.data-table td{padding:12px 14px;border-bottom:1px solid #e5e7eb;text-align:left}.table-responsive{overflow-x:auto}.btn{display:inline-block;padding:9px 14px;border-radius:6px;text-decoration:none;font-size:13px;font-weight:600}.btn-primary{background:#111827;color:#fff}.btn-secondary{background:#f3f4f6;color:#111827}@media(max-width:700px){.main-content{padding:15px}.page-header{flex-direction:column;align-items:flex-start}.stats-grid{grid-template-columns:1fr}.detail-grid{grid-template-columns:1fr}}
</style>
<?php require_once __DIR__.'/../includes/footer.php'; ?>