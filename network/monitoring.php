<?php
require_once '../includes/auth.php';
requireLogin();
$tenantId=getCurrentTenantId(); $routerRows=[];
$check=$conn->query("SHOW TABLES LIKE 'mikrotik_routers'");
if($check&&$check->num_rows){$sql="SELECT * FROM mikrotik_routers";if($tenantId)$sql.=" WHERE tenant_id=".(int)$tenantId;$sql.=" ORDER BY id DESC";$r=$conn->query($sql);if($r)while($x=$r->fetch_assoc())$routerRows[]=$x;}
$pageTitle='Network Monitoring'; require_once '../includes/header.php';
?>
<div class="dashboard-card"><h2>Network Monitoring</h2>
<div class="table-responsive"><table><thead><tr><th>Router</th><th>Host</th><th>Status</th><th>Last Seen</th></tr></thead><tbody>
<?php foreach($routerRows as $row): ?><tr><td><?=e($row['name']??$row['router_name']??$row['identity']??('Router #'.$row['id']))?></td><td><?=e($row['host']??$row['ip_address']??$row['address']??'')?></td><td><?=e($row['status']??'configured')?></td><td><?=e($row['last_seen_at']??$row['updated_at']??'')?></td></tr><?php endforeach; ?>
<?php if(!$routerRows): ?><tr><td colspan="4">No routers configured.</td></tr><?php endif; ?></tbody></table></div></div>
<?php require_once '../includes/footer.php'; ?>