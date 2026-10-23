<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('operations', 'view');
global $conn;
$tenantId = (int)getCurrentTenantId();
if ($tenantId <= 0) { http_response_code(403); exit('A valid tenant context is required.'); }
$stats = ['sales_count'=>0,'sales_amount'=>0.0,'commission'=>0.0,'pending_sales'=>0,'settled_sales'=>0,'adverts_total'=>0,'adverts_active'=>0,'adverts_scheduled'=>0];
$tableExists = static function ($table) use ($conn) { $stmt=$conn->prepare('SHOW TABLES LIKE ?'); if(!$stmt)return false; $stmt->bind_param('s',$table); $stmt->execute(); $found=(bool)$stmt->get_result()->fetch_row(); $stmt->close(); return $found; };
if ($tableExists('agent_sales')) {
 $stmt=$conn->prepare("SELECT COUNT(*) AS sales_count, COALESCE(SUM(CASE WHEN status <> 'cancelled' THEN amount ELSE 0 END),0) AS sales_amount, COALESCE(SUM(CASE WHEN status <> 'cancelled' THEN commission ELSE 0 END),0) AS commission, SUM(status='pending') AS pending_sales, SUM(status='settled') AS settled_sales FROM agent_sales WHERE tenant_id=?");
 if($stmt){$stmt->bind_param('i',$tenantId);if($stmt->execute()){$row=$stmt->get_result()->fetch_assoc()?:[];foreach(['sales_count','sales_amount','commission','pending_sales','settled_sales'] as $key)$stats[$key]=$row[$key]??$stats[$key];}$stmt->close();}else error_log('Operations dashboard agent-sales summary failed: '.$conn->error);
}
if ($tableExists('adverts')) {
 $stmt=$conn->prepare("SELECT COUNT(*) AS adverts_total, SUM(status='active') AS adverts_active, SUM(status='scheduled') AS adverts_scheduled FROM adverts WHERE tenant_id=?");
 if($stmt){$stmt->bind_param('i',$tenantId);if($stmt->execute()){$row=$stmt->get_result()->fetch_assoc()?:[];foreach(['adverts_total','adverts_active','adverts_scheduled'] as $key)$stats[$key]=$row[$key]??$stats[$key];}$stmt->close();}else error_log('Operations dashboard advert summary failed: '.$conn->error);
}
$pageTitle='Operations Command Centre'; require_once __DIR__.'/../includes/header.php';
?>
<div class="page-content operations-command-center">
<div class="page-header"><div><span class="operations-kicker">BUSINESS OPERATIONS</span><h1>Operations Command Centre</h1><p>Monitor field sales, commissions and customer communications from one workspace.</p></div></div>
<div class="operations-metrics">
<div class="operations-metric"><span>Agent sales recorded</span><strong><?= (int)$stats['sales_count'] ?></strong><small><?= e(formatMoney((float)$stats['sales_amount'])) ?> value, excluding cancelled sales</small></div>
<div class="operations-metric"><span>Commission recorded</span><strong><?= e(formatMoney((float)$stats['commission'])) ?></strong><small>Calculated from saved agent-sale records</small></div>
<div class="operations-metric"><span>Pending settlements</span><strong><?= (int)$stats['pending_sales'] ?></strong><small><?= (int)$stats['settled_sales'] ?> settled sale records</small></div>
<div class="operations-metric"><span>Active adverts</span><strong><?= (int)$stats['adverts_active'] ?></strong><small><?= (int)$stats['adverts_scheduled'] ?> scheduled · <?= (int)$stats['adverts_total'] ?> total</small></div>
</div>
<div class="operations-workspace">
<section class="card operations-panel"><div class="operations-panel-heading"><div><h2>Sales &amp; commissions</h2><p>Track agent-generated sales, review pending records and update settlement status.</p></div><span class="operations-icon">♙</span></div><div class="operations-quick-stats"><div><small>Sales value</small><strong><?= e(formatMoney((float)$stats['sales_amount'])) ?></strong></div><div><small>Commission</small><strong><?= e(formatMoney((float)$stats['commission'])) ?></strong></div></div><a class="btn btn-primary" href="agent_sales.php">Manage agent sales</a></section>
<section class="card operations-panel"><div class="operations-panel-heading"><div><h2>Adverts &amp; announcements</h2><p>Prepare customer notices, promotional messages and scheduled campaigns.</p></div><span class="operations-icon">▤</span></div><div class="operations-quick-stats"><div><small>Active</small><strong><?= (int)$stats['adverts_active'] ?></strong></div><div><small>Scheduled</small><strong><?= (int)$stats['adverts_scheduled'] ?></strong></div></div><a class="btn btn-primary" href="adverts.php">Manage adverts</a></section>
</div>
<section class="card operations-panel operations-links"><h2>Operational shortcuts</h2><p>Move directly to modules that support day-to-day ISP operations.</p><div class="operations-link-grid"><a href="../customers/index.php"><span>Customers</span><small>Customer records and service ownership</small></a><a href="../internet_accounts/index.php"><span>Internet accounts</span><small>Service accounts and connection lifecycle</small></a><a href="../routers/index.php"><span>Routers</span><small>Router inventory and configuration</small></a><a href="../network/index.php"><span>Network</span><small>Network status and infrastructure</small></a><a href="../billing/index.php"><span>Billing</span><small>Invoices, collections and outstanding balances</small></a><a href="../reports/index.php"><span>Reports</span><small>Operational and financial summaries</small></a></div></section>
</div>
<style>
.operations-command-center{color:#f8fafc}.operations-kicker{font-size:11px;letter-spacing:.16em;color:#60a5fa;font-weight:700}.operations-metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:18px}.operations-metric,.operations-panel{background:linear-gradient(145deg,rgba(16,24,39,.97),rgba(7,12,20,.96));border:1px solid rgba(96,165,250,.14);border-radius:14px;box-shadow:0 14px 35px rgba(0,0,0,.18)}.operations-metric{padding:18px}.operations-metric span,.operations-metric small,.operations-panel p,.operations-quick-stats small,.operations-link-grid small{display:block;color:#94a3b8;font-size:12px}.operations-metric strong{display:block;font-size:25px;margin:8px 0}.operations-metric small{line-height:1.5}.operations-workspace{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;margin-bottom:18px}.operations-panel{padding:20px}.operations-panel h2{margin:0;color:#f8fafc}.operations-panel p{margin:7px 0 0;line-height:1.6}.operations-panel-heading{display:flex;justify-content:space-between;gap:14px}.operations-icon{display:grid;place-items:center;width:42px;height:42px;border-radius:12px;background:rgba(37,99,235,.14);color:#93c5fd;font-size:22px}.operations-quick-stats{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin:20px 0}.operations-quick-stats>div{padding:13px;border-radius:10px;background:rgba(15,23,42,.7)}.operations-quick-stats strong{display:block;color:#f8fafc;font-size:18px;margin-top:6px}.operations-panel .btn{margin-top:4px}.operations-links>p{margin-bottom:16px}.operations-link-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}.operations-link-grid a{padding:14px;border:1px solid rgba(148,163,184,.14);border-radius:10px;text-decoration:none;color:#e2e8f0;background:rgba(15,23,42,.45)}.operations-link-grid a:hover{border-color:#3b82f6;background:rgba(37,99,235,.1)}.operations-link-grid span{display:block;font-weight:650;margin-bottom:5px}.operations-link-grid small{line-height:1.5}@media(max-width:1000px){.operations-metrics{grid-template-columns:repeat(2,minmax(0,1fr))}.operations-link-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:650px){.operations-metrics,.operations-workspace,.operations-link-grid{grid-template-columns:1fr}}
</style>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
