<?php
require_once '../includes/auth.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('reports','view');
$tenantId=(int)getCurrentTenantId();$total=0;$active=0;
$q=$conn->query("SHOW TABLES LIKE 'customers'");
if($q&&$q->num_rows){
$r=$conn->query("SELECT COUNT(*) total FROM customers WHERE tenant_id=".$tenantId);$total=$r?(int)$r->fetch_assoc()['total']:0;
$r=$conn->query("SELECT COUNT(*) total FROM customers WHERE tenant_id=".$tenantId." AND status='active'");$active=$r?(int)$r->fetch_assoc()['total']:0;}
$pageTitle='Customer Report';require_once '../includes/header.php';?>
<div class="dashboard-card"><h2>Customer Report</h2><div class="stats-grid"><div class="stat-card"><h3><?=e($total)?></h3><p>Total Customers</p></div><div class="stat-card"><h3><?=e($active)?></h3><p>Active Customers</p></div></div></div>
<?php require_once '../includes/footer.php'; ?>