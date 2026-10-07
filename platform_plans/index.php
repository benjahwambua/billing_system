<?php
require_once '../includes/auth.php';
requireLogin();
requireHostContext();

$pageTitle='SaaS Subscription Plans';
$plans=[];
$available=false;
$check=$conn->query("SHOW TABLES LIKE 'platform_plans'");
if($check&&$check->num_rows){
    $available=true;
    $result=$conn->query("SELECT id,code,name,description,price,billing_cycle,trial_days,grace_days,max_users,max_customers,max_routers,max_accounts,status FROM platform_plans ORDER BY price ASC,id ASC");
    if($result)while($x=$result->fetch_assoc())$plans[]=$x;
}
require_once '../includes/header.php';require_once '../includes/sidebar.php';
?>
<div class="main-content">
<div class="page-header"><div><h1>SaaS Subscription Plans</h1><p>Flexihub hosting plans billed to ISP tenants. These are separate from ISP internet service plans.</p></div></div>
<div class="card">
<?php if(!$available):?><div class="notice">The SaaS subscription schema has not been installed yet.</div>
<?php else:?><div class="table-responsive"><table><thead><tr><th>Code</th><th>Plan</th><th>Price</th><th>Cycle</th><th>Trial</th><th>Grace</th><th>Limits</th><th>Status</th></tr></thead><tbody>
<?php foreach($plans as $p):?><tr><td><code><?=e($p['code'])?></code></td><td><strong><?=e($p['name'])?></strong><small><?=e($p['description']??'')?></small></td><td><?=e(formatMoney($p['price']))?></td><td><?=e(ucfirst($p['billing_cycle']))?></td><td><?=e($p['trial_days'])?> days</td><td><?=e($p['grace_days'])?> days</td><td><?=e(($p['max_users']??'∞').' users / '.($p['max_customers']??'∞').' customers / '.($p['max_routers']??'∞').' routers / '.($p['max_accounts']??'∞').' accounts')?></td><td><?=e(ucfirst($p['status']))?></td></tr><?php endforeach;?>
<?php if(!$plans):?><tr><td colspan="8" class="empty">No SaaS plans found.</td></tr><?php endif;?></tbody></table></div><?php endif;?>
</div></div>
<style>.main-content{padding:24px}.page-header{margin-bottom:24px}.page-header h1{margin:0 0 6px}.page-header p{margin:0;color:#6b7280}.card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden}.notice{padding:18px;color:#4b5563}.table-responsive{overflow:auto}table{width:100%;border-collapse:collapse;min-width:1100px}th,td{padding:13px 15px;border-bottom:1px solid #e5e7eb;text-align:left;vertical-align:top}th{background:#f9fafb;font-size:12px;color:#4b5563}small{display:block;color:#6b7280;margin-top:4px}.empty{text-align:center;padding:40px;color:#6b7280}code{font-family:monospace}</style>
<?php require_once '../includes/footer.php'; ?>