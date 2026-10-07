<?php
require_once '../includes/auth.php';requireLogin();requireHostContext();
$rows=[];$available=false;
if ($_SERVER['REQUEST_METHOD']==='POST') {
    requireCsrf();
    $tenantId=(int)($_POST['tenant_id']??0);
    $planId=(int)($_POST['plan_id']??0);
    if($tenantId<=0 || $planId<=0){
        setFlash('error','Tenant and SaaS plan are required.');
    } else {
        $plan=dbFetchOne("SELECT id,trial_days,billing_cycle FROM platform_plans WHERE id=? AND status='active' LIMIT 1",'i',$planId);
        $tenant=dbFetchOne("SELECT id,status FROM tenants WHERE id=? LIMIT 1",'i',$tenantId);
        if(!$plan || !$tenant){
            setFlash('error','Invalid tenant or inactive SaaS plan.');
        } else {
            $existing=dbFetchOne("SELECT id,status FROM tenant_platform_subscriptions WHERE tenant_id=? LIMIT 1",'i',$tenantId);
            $now=date('Y-m-d H:i:s');
            if($existing){
                dbExecute("UPDATE tenant_platform_subscriptions SET plan_id=?,updated_at=? WHERE id=?",'isi',$planId,$now,(int)$existing['id']);
                setFlash('success','SaaS plan assignment updated.');
            } else {
                $trial=(int)$plan['trial_days'];
                $trialEnds=$trial>0?date('Y-m-d H:i:s',strtotime("+{$trial} days")):null;
                $status=$trial>0?'trial':'active';
                $start=date('Y-m-d');
                $periodStart=$trial>0?null:$start;
                $periodEnd=$trial>0?null:(new DateTime($start))->modify($plan['billing_cycle']==='yearly'?'+1 year':($plan['billing_cycle']==='quarterly'?'+3 months':'+1 month'))->format('Y-m-d');
                dbExecute("INSERT INTO tenant_platform_subscriptions (tenant_id,plan_id,status,started_at,trial_ends_at,current_period_start,current_period_end,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?)",'iisssssss',$tenantId,$planId,$status,$now,$trialEnds,$periodStart,$periodEnd,$now,$now);
                setFlash('success','SaaS plan assigned and subscription created.');
            }
        }
    }
    redirect('index.php');
}
$check=$conn->query("SHOW TABLES LIKE 'tenant_platform_subscriptions'");
if($check&&$check->num_rows){
 $available=true;
 $q=$conn->query("SELECT s.id,s.tenant_id,s.status,s.started_at,s.trial_ends_at,s.current_period_start,s.current_period_end,s.grace_ends_at,p.code plan_code,p.name plan_name,p.price,t.tenant_code,t.name tenant_name,COALESCE(i.invoice_number,'') invoice_number,COALESCE(i.total_amount,0) invoice_total,COALESCE(i.paid_amount,0) invoice_paid,COALESCE(i.status,'') invoice_status FROM tenant_platform_subscriptions s JOIN platform_plans p ON p.id=s.plan_id JOIN tenants t ON t.id=s.tenant_id LEFT JOIN platform_invoices i ON i.id=s.last_invoice_id ORDER BY s.id DESC LIMIT 500");
 if($q)while($r=$q->fetch_assoc())$rows[]=$r;
}
$tenants=dbFetchAll("SELECT id,tenant_code,name FROM tenants WHERE LOWER(COALESCE(status,'')) NOT IN ('deleted','archived') ORDER BY name ASC");
$plans=dbFetchAll("SELECT id,code,name,price,billing_cycle FROM platform_plans WHERE status='active' ORDER BY price ASC,id ASC");
$pageTitle='SaaS Subscriptions';require_once '../includes/header.php';require_once '../includes/sidebar.php';?>
<div class="main-content"><div class="page-header"><div><h1>SaaS Subscriptions</h1><p>Host view of Flexihub hosting subscriptions assigned to ISP tenants.</p></div></div>
<div class="card"><?php if(!$available):?><div class="notice">The SaaS subscription schema has not been installed yet.</div><?php else:?><div class="table-responsive"><table><thead><tr><th>Tenant</th><th>Plan</th><th>Status</th><th>Started</th><th>Trial Ends</th><th>Current Period</th><th>Latest Invoice</th><th>Grace Ends</th></tr></thead><tbody>
<?php foreach($rows as $r):?><tr><td><strong><?=e($r['tenant_name'])?></strong><small><?=e($r['tenant_code'])?></small></td><td><?=e($r['plan_name'])?><small><?=e($r['plan_code'])?> · <?=e(formatMoney($r['price']))?></small></td><td><?=e(ucwords(str_replace('_',' ',$r['status'])))?></td><td><?=e($r['started_at']??'—')?></td><td><?=e($r['trial_ends_at']??'—')?></td><td><?=e(($r['current_period_start']??'—').' → '.($r['current_period_end']??'—'))?></td><td><?=e($r['invoice_number']?:'—')?><small><?=e($r['invoice_status']?ucfirst($r['invoice_status']):'—')?> · <?=e(formatMoney((float)$r['invoice_paid']))?> / <?=e(formatMoney((float)$r['invoice_total']))?></small></td><td><?=e($r['grace_ends_at']??'—')?></td></tr><?php endforeach;?>
<?php if(!$rows):?><tr><td colspan="8" class="empty">No SaaS subscriptions found. Enable SaaS billing to provision subscriptions.</td></tr><?php endif;?></tbody></table></div><?php endif;?></div></div>
<style>.assign-card{margin-bottom:20px}.assign-form{display:grid;grid-template-columns:1fr 1fr auto;gap:14px;align-items:end;padding:20px}.assign-form label{display:block;font-size:12px;font-weight:600;margin-bottom:6px;color:#4b5563}.assign-form select{width:100%;padding:10px;border:1px solid #d1d5db;border-radius:6px;background:#fff}.main-content{padding:24px}.page-header{margin-bottom:24px}.page-header h1{margin:0 0 6px}.page-header p{margin:0;color:#6b7280}.card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden}.notice{padding:18px;color:#4b5563}.table-responsive{overflow:auto}table{width:100%;border-collapse:collapse;min-width:1100px}th,td{padding:13px 15px;border-bottom:1px solid #e5e7eb;text-align:left;vertical-align:top}th{background:#f9fafb;font-size:12px;color:#4b5563}small{display:block;color:#6b7280;margin-top:4px}.empty{text-align:center;padding:40px;color:#6b7280}</style>
<?php require_once '../includes/footer.php';?>