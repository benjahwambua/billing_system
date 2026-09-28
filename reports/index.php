<?php
require_once '../includes/auth.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('reports','view');
$pageTitle='Reports';
require_once '../includes/header.php';
?>
<div class="dashboard-card">
<h2>Reports</h2>
<p style="color:#6b7280">Tenant-scoped operational and financial reporting.</p>
<div style="margin-top:20px;display:flex;gap:10px;flex-wrap:wrap">
<a class="btn" href="revenue.php">Revenue</a>
<a class="btn" href="collections.php">Collections</a>
<a class="btn" href="customers.php">Customers</a>
<a class="btn" href="usage.php">Usage</a>
<a class="btn" href="network.php">Network</a>
<a class="btn" href="financial.php">Financial</a>
</div>
</div>
<?php require_once '../includes/footer.php'; ?>