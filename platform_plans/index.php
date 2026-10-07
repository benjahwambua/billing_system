<?php
require_once '../includes/auth.php';
requireLogin();
requireHostContext();

$pageTitle='Subscription Plans';
$plans=[];$available=false;
$check=$conn->query("SHOW TABLES LIKE 'internet_plans'");
if($check&&$check->num_rows){$available=true;$result=$conn->query("SELECT id,name,price,billing_cycle,billing_days,download_speed,upload_speed,status FROM internet_plans ORDER BY price ASC,id ASC LIMIT 500");if($result)while($x=$result->fetch_assoc())$plans[]=$x;}
require_once '../includes/header.php';require_once '../includes/sidebar.php';
?>
<div class="main-content"><div class="page-header"><div><h1>Subscription Plans</h1><p>Current ISP service plans. SaaS hosting plans remain a separate platform data model.</p></div></div>
<div class="card"><div class="notice">This workspace now provides host visibility into service plans already used by the billing engine. Dedicated Flexihub SaaS subscription plans will be enabled separately.</div>
<?php if($available):?><div class="table-responsive"><table><thead><tr><th>Plan</th><th>Price</th><th>Cycle</th><th>Billing Days</th><th>Download</th><th>Upload</th><th>Status</th></tr></thead><tbody><?php foreach($plans as $p):?><tr><td><strong><?=e($p['name'])?></strong></td><td><?=e(formatMoney($p['price']))?></td><td><?=e(ucfirst($p['billing_cycle']??''))?></td><td><?=e($p['billing_days']??'—')?></td><td><?=e($p['download_speed']??'—')?></td><td><?=e($p['upload_speed']??'—')?></td><td><?=e(ucfirst($p['status']??''))?></td></tr><?php endforeach;?><?php if(!$plans):?><tr><td colspan="7" class="empty">No service plans found.</td></tr><?php endif;?></tbody></table></div><?php else:?><p>No internet plan table is available.</p><?php endif;?></div></div>
<style>.main-content{padding:24px}.page-header{margin-bottom:24px}.page-header h1{margin:0 0 6px}.page-header p{margin:0;color:#6b7280}.card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden}.notice{padding:18px;background:#f9fafb;border-bottom:1px solid #e5e7eb;color:#4b5563}.table-responsive{overflow:auto}table{width:100%;border-collapse:collapse;min-width:850px}th,td{padding:13px 15px;border-bottom:1px solid #e5e7eb;text-align:left}th{background:#f9fafb;font-size:12px;color:#4b5563}.empty{text-align:center;padding:40px;color:#6b7280}</style>
<?php require_once '../includes/footer.php'; ?>