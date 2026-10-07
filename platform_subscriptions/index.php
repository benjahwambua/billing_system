<?php
require_once '../includes/auth.php';requireLogin();requireHostContext();
$rows=[];$available=false;
$check=$conn->query("SHOW TABLES LIKE 'tenant_platform_subscriptions'");
if($check&&$check->num_rows){
 $available=true;
 $q=$conn->query("SELECT s.id,s.tenant_id,s.status,s.started_at,s.trial_ends_at,s.current_period_start,s.current_period_end,s.grace_ends_at,p.code plan_code,p.name plan_name,p.price,t.tenant_code,t.name tenant_name FROM tenant_platform_subscriptions s JOIN platform_plans p ON p.id=s.plan_id JOIN tenants t ON t.id=s.tenant_id ORDER BY s.id DESC LIMIT 500");
 if($q)while($r=$q->fetch_assoc())$rows[]=$r;
}
$pageTitle='SaaS Subscriptions';require_once '../includes/header.php';require_once '../includes/sidebar.php';?>
<div class="main-content"><div class="page-header"><div><h1>SaaS Subscriptions</h1><p>Host view of Flexihub hosting subscriptions assigned to ISP tenants.</p></div></div>
<div class="card"><?php if(!$available):?><div class="notice">The SaaS subscription schema has not been installed yet.</div><?php else:?><div class="table-responsive"><table><thead><tr><th>Tenant</th><th>Plan</th><th>Status</th><th>Started</th><th>Trial Ends</th><th>Current Period</th><th>Grace Ends</th></tr></thead><tbody>
<?php foreach($rows as $r):?><tr><td><strong><?=e($r['tenant_name'])?></strong><small><?=e($r['tenant_code'])?></small></td><td><?=e($r['plan_name'])?><small><?=e($r['plan_code'])?> · <?=e(formatMoney($r['price']))?></small></td><td><?=e(ucwords(str_replace('_',' ',$r['status'])))?></td><td><?=e($r['started_at']??'—')?></td><td><?=e($r['trial_ends_at']??'—')?></td><td><?=e(($r['current_period_start']??'—').' → '.($r['current_period_end']??'—'))?></td><td><?=e($r['grace_ends_at']??'—')?></td></tr><?php endforeach;?>
<?php if(!$rows):?><tr><td colspan="7" class="empty">No SaaS subscriptions found. Enable SaaS billing to provision subscriptions.</td></tr><?php endif;?></tbody></table></div><?php endif;?></div></div>
<style>.main-content{padding:24px}.page-header{margin-bottom:24px}.page-header h1{margin:0 0 6px}.page-header p{margin:0;color:#6b7280}.card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden}.notice{padding:18px;color:#4b5563}.table-responsive{overflow:auto}table{width:100%;border-collapse:collapse;min-width:1100px}th,td{padding:13px 15px;border-bottom:1px solid #e5e7eb;text-align:left;vertical-align:top}th{background:#f9fafb;font-size:12px;color:#4b5563}small{display:block;color:#6b7280;margin-top:4px}.empty{text-align:center;padding:40px;color:#6b7280}</style>
<?php require_once '../includes/footer.php';?>