<?php
require_once '../includes/auth.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('reports','view');
$tenantId=(int)getCurrentTenantId(); $total=0;
$q=$conn->query("SHOW TABLES LIKE 'payments'");
if($q&&$q->num_rows){$r=$conn->query("SELECT COALESCE(SUM(amount),0) total FROM payments WHERE tenant_id=".$tenantId);$total=$r?(float)$r->fetch_assoc()['total']:0;}
$pageTitle='Collections Report';require_once '../includes/header.php';?>
<div class="dashboard-card"><h2>Collections Report</h2><div class="stats-grid"><div class="stat-card"><h3><?=e(formatMoney($total))?></h3><p>Total Collections</p></div></div></div>
<?php require_once '../includes/footer.php'; ?>