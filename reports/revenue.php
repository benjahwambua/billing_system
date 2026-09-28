<?php
require_once '../includes/auth.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('reports','view');

$tenantId = (int)getCurrentTenantId();
$income = 0; $expense = 0;
$q = $conn->query("SHOW TABLES LIKE 'payments'");
if ($q && $q->num_rows) {
    $sql = "SELECT COALESCE(SUM(amount),0) total FROM payments WHERE tenant_id=".$tenantId;
    $res = $conn->query($sql); $income = $res ? (float)$res->fetch_assoc()['total'] : 0;
}
$q = $conn->query("SHOW TABLES LIKE 'expenses'");
if ($q && $q->num_rows) {
    $sql = "SELECT COALESCE(SUM(amount),0) total FROM expenses WHERE tenant_id=".$tenantId;
    $res = $conn->query($sql); $expense = $res ? (float)$res->fetch_assoc()['total'] : 0;
}
$pageTitle='Revenue'; require_once '../includes/header.php'; ?>
<div class="dashboard-card"><h2>Revenue & Financial Summary</h2>
<div class="stats-grid"><div class="stat-card"><h3><?=e(formatMoney($income))?></h3><p>Collections</p></div>
<div class="stat-card"><h3><?=e(formatMoney($expense))?></h3><p>Expenses</p></div>
<div class="stat-card"><h3><?=e(formatMoney($income-$expense))?></h3><p>Net</p></div></div></div>
<?php require_once '../includes/footer.php'; ?>