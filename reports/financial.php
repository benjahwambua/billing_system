<?php
require_once '../includes/auth.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('reports','view');
$tenantId=(int)getCurrentTenantId();$income=0;$expense=0;
$q=$conn->query("SHOW TABLES LIKE 'payments'");
if($q&&$q->num_rows){$r=$conn->query("SELECT COALESCE(SUM(amount),0) total FROM payments WHERE tenant_id=".$tenantId);$income=$r?(float)$r->fetch_assoc()['total']:0;}
$q=$conn->query("SHOW TABLES LIKE 'expenses'");
if($q&&$q->num_rows){$r=$conn->query("SELECT COALESCE(SUM(amount),0) total FROM expenses WHERE tenant_id=".$tenantId);$expense=$r?(float)$r->fetch_assoc()['total']:0;}
$pageTitle='Financial Report';require_once '../includes/header.php';?>
<div class="dashboard-card"><h2>Financial Report</h2><div class="stats-grid"><div class="stat-card"><h3><?=e(formatMoney($income))?></h3><p>Income</p></div><div class="stat-card"><h3><?=e(formatMoney($expense))?></h3><p>Expenses</p></div><div class="stat-card"><h3><?=e(formatMoney($income-$expense))?></h3><p>Net</p></div></div></div>
<?php require_once '../includes/footer.php'; ?>