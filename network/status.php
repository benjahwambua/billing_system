<?php
require_once '../includes/auth.php';
requireLogin();
$tenantId = getCurrentTenantId();
$tables = ['mikrotik_routers','pppoe_servers','active_sessions'];
$counts=[];
foreach($tables as $table){
    $safe=$conn->real_escape_string($table);
    $q=$conn->query("SHOW TABLES LIKE '$safe'");
    $counts[$table]=($q&&$q->num_rows)?1:0;
    if($counts[$table]){
        $sql="SELECT COUNT(*) total FROM $safe";
        if($tenantId) $sql.=" WHERE tenant_id=".(int)$tenantId;
        $r=$conn->query($sql); $counts[$table]=$r?(int)$r->fetch_assoc()['total']:0;
    } else $counts[$table]=0;
}
$pageTitle='Network Status';
require_once '../includes/header.php';
?>
<div class="dashboard-card"><h2>Network Status</h2><div class="stats-grid">
<div class="stat-card"><h3><?=e($counts['mikrotik_routers'])?></h3><p>Routers</p></div>
<div class="stat-card"><h3><?=e($counts['pppoe_servers'])?></h3><p>PPPoE Servers</p></div>
<div class="stat-card"><h3><?=e($counts['active_sessions'])?></h3><p>Active Sessions</p></div>
</div><p style="margin-top:15px;color:#6b7280">Live network integration is tenant-scoped. Router credentials are never displayed here.</p></div>
<?php require_once '../includes/footer.php'; ?>