<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();
if (isTenantUser()) requireTenant();
$tenantId=(int)getCurrentTenantId();
if(!$tenantId) die('Tenant context is required.');
$routerRows=[];
$stmt=$conn->prepare("SELECT * FROM mikrotik_routers WHERE tenant_id=? ORDER BY id DESC");
if($stmt){$stmt->bind_param('i',$tenantId);$stmt->execute();$res=$stmt->get_result();while($x=$res->fetch_assoc())$routerRows[]=$x;$stmt->close();}
$online=$offline=$unknown=0;
foreach($routerRows as $r){$s=strtolower((string)($r['status']??''));if($s==='online')$online++;elseif($s==='offline')$offline++;else $unknown++;}
$pageTitle='Network Monitoring'; require_once '../includes/header.php';
?>
<div class="page-content">
<div class="page-header"><div><h1>Network Monitoring</h1><p>Tenant-scoped MikroTik reachability, latency and polling health.</p></div><a class="btn btn-secondary" href="../routers/health.php">Live Network Health</a></div>
<div class="dashboard-card" style="margin-bottom:20px;padding:20px"><div style="display:flex;gap:30px;flex-wrap:wrap"><div><small>Total</small><h2><?=count($routerRows)?></h2></div><div><small>Online</small><h2><?=e($online)?></h2></div><div><small>Offline</small><h2><?=e($offline)?></h2></div><div><small>Unknown</small><h2><?=e($unknown)?></h2></div></div></div>
<div class="dashboard-card"><div class="table-responsive"><table><thead><tr><th>Router</th><th>Host</th><th>Status</th><th>Latency</th><th>Last Seen</th><th>Last Poll</th><th>Last Error</th></tr></thead><tbody>
<?php foreach($routerRows as $row): ?><tr><td><strong><?=e($row['name']??$row['router_name']??$row['identity']??('Router #'.$row['id']))?></strong></td><td><?=e($row['host']??$row['ip_address']??$row['ip']??'')?></td><td><?=e(ucfirst($row['status']??'unknown'))?></td><td><?=isset($row['latency_ms'])&&$row['latency_ms']!==null?e($row['latency_ms']).' ms':'—'?></td><td><?=e($row['last_seen_at']??'—')?></td><td><?=e($row['last_poll_at']??'—')?></td><td><?=e($row['last_poll_error']??'')?></td></tr><?php endforeach; ?>
<?php if(!$routerRows): ?><tr><td colspan="7">No routers configured.</td></tr><?php endif; ?></tbody></table></div></div></div>
<?php require_once '../includes/footer.php'; ?>
