<?php
require_once '../includes/auth.php';
requireLogin();
requireHostContext();

$totals=['invoiced'=>0.0,'collected'=>0.0,'outstanding'=>0.0,'overdue'=>0.0];
$stats=[];
$available=false;

$check=$conn->query("SHOW TABLES LIKE 'platform_invoices'");
if($check && $check->num_rows){
    $available=true;
    $q=$conn->query("SELECT t.id,t.tenant_code,t.name,t.status AS tenant_status,
        COALESCE(SUM(pi.total_amount),0) AS invoiced,
        COALESCE(SUM(pi.paid_amount),0) AS collected,
        COALESCE(SUM(CASE WHEN pi.status IN ('unpaid','partial','overdue') THEN GREATEST(pi.total_amount-pi.paid_amount,0) ELSE 0 END),0) AS outstanding,
        COALESCE(SUM(CASE WHEN pi.status='overdue' THEN GREATEST(pi.total_amount-pi.paid_amount,0) ELSE 0 END),0) AS overdue
        FROM tenants t LEFT JOIN platform_invoices pi ON pi.tenant_id=t.id
        WHERE LOWER(COALESCE(t.status,'')) NOT IN ('deleted','archived')
        GROUP BY t.id,t.tenant_code,t.name,t.status ORDER BY t.id DESC LIMIT 500");
    if($q){
        while($r=$q->fetch_assoc()){
            foreach(['invoiced','collected','outstanding','overdue'] as $k)$r[$k]=(float)$r[$k];
            $stats[]=$r;
            foreach(['invoiced','collected','outstanding','overdue'] as $k)$totals[$k]+=$r[$k];
        }
    }
}

$pageTitle='Platform Revenue';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<div class="main-content">
<div class="page-header"><div><h1>Platform Revenue</h1><p>Flexihub SaaS hosting revenue from ISP tenants. Customer internet collections remain in the tenant billing ledger.</p></div></div>
<?php if(!$available): ?>
<div class="card notice">The SaaS platform invoice schema is not available in this installation yet.</div>
<?php else: ?>
<div class="stats-grid">
<div class="stat-card"><h3><?=e(formatMoney($totals['invoiced']))?></h3><p>SaaS Invoiced</p></div>
<div class="stat-card"><h3><?=e(formatMoney($totals['collected']))?></h3><p>SaaS Collected</p></div>
<div class="stat-card"><h3><?=e(formatMoney($totals['outstanding']))?></h3><p>SaaS Outstanding</p></div>
<div class="stat-card"><h3><?=e(formatMoney($totals['overdue']))?></h3><p>Overdue Balance</p></div>
</div>
<div class="dashboard-card" style="margin-top:20px">
<div class="section-heading"><h2>SaaS Revenue by ISP Tenant</h2><p>Only Flexihub hosting subscription invoices are included here.</p></div>
<div class="table-responsive"><table><thead><tr><th>Tenant</th><th>Tenant Status</th><th>SaaS Invoiced</th><th>SaaS Collected</th><th>Outstanding</th><th>Overdue</th></tr></thead><tbody>
<?php foreach($stats as $r): ?><tr>
<td><strong><?=e($r['name']??'')?></strong><small><?=e($r['tenant_code']??'')?></small></td>
<td><?=e(ucwords(str_replace('_',' ',(string)($r['tenant_status']??''))))?></td>
<td><?=e(formatMoney($r['invoiced']))?></td><td><?=e(formatMoney($r['collected']))?></td>
<td><strong><?=e(formatMoney($r['outstanding']))?></strong></td><td><?=e(formatMoney($r['overdue']))?></td>
</tr><?php endforeach; ?>
<?php if(!$stats): ?><tr><td colspan="6" class="empty">No SaaS invoices found yet.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php endif; ?>
</div>
<style>
.main-content{padding:24px}.page-header{margin-bottom:24px}.page-header h1{margin:0 0 6px}.page-header p{margin:0;color:#6b7280}
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:16px}.stat-card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px}.stat-card h3{margin:0 0 7px;font-size:24px}.stat-card p{margin:0;color:#6b7280;font-size:13px}
.dashboard-card,.card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden}.section-heading{padding:20px;border-bottom:1px solid #e5e7eb}.section-heading h2{margin:0 0 5px;font-size:18px}.section-heading p{margin:0;color:#6b7280;font-size:13px}
.table-responsive{overflow:auto}table{width:100%;border-collapse:collapse;min-width:900px}th,td{padding:13px 15px;border-bottom:1px solid #e5e7eb;text-align:left;vertical-align:top}th{background:#f9fafb;font-size:12px;color:#4b5563}small{display:block;color:#6b7280;margin-top:4px}.empty{text-align:center;padding:40px;color:#6b7280}.notice{padding:20px}
</style>
<?php require_once '../includes/footer.php'; ?>