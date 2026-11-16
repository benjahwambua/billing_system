<?php
require_once '../includes/auth.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('hotspot','view');
require_once '../includes/billing_workflow.php';

$tenantId=(int)getCurrentTenantId();
$rows=[];
$packageColumns=flexihubTableColumns('hotspot_packages');
$routerColumns=flexihubTableColumns('mikrotik_routers');
$hasRouterLink=in_array('router_id',$packageColumns,true);
$routerNameColumn=in_array('name',$routerColumns,true)?'name':(in_array('router_name',$routerColumns,true)?'router_name':null);
$loadError='';
if($hasRouterLink && $routerNameColumn){
    $sql="SELECT p.*, COALESCE(r.`{$routerNameColumn}`,'—') AS router_display_name
          FROM hotspot_packages p
          LEFT JOIN mikrotik_routers r ON r.id=p.router_id AND r.tenant_id=p.tenant_id
          WHERE p.tenant_id=? ORDER BY p.id DESC LIMIT 200";
}elseif($hasRouterLink){
    $sql="SELECT p.*, '—' AS router_display_name FROM hotspot_packages p WHERE p.tenant_id=? ORDER BY p.id DESC LIMIT 200";
}else{
    $sql="SELECT p.*, 'Router association unavailable' AS router_display_name FROM hotspot_packages p WHERE p.tenant_id=? ORDER BY p.id DESC LIMIT 200";
    $loadError='Router assignment is not available in the installed schema. Apply the reviewed hotspot router migration before activating packages.';
}
$q=$conn->prepare($sql);
if($q){$q->bind_param('i',$tenantId);if($q->execute()){$z=$q->get_result();while($x=$z->fetch_assoc())$rows[]=$x;}else $loadError='Hotspot plans could not be loaded.';$q->close();}
else $loadError='Hotspot plan storage could not be queried.';
$pageTitle='Hotspot Plans';require_once '../includes/header.php';?>
<div class="dashboard-card">
 <div class="page-header"><div><h2>Hotspot Plans</h2><p>Timed Wi-Fi packages and the router that delivers each package.</p></div>
 <?php if(userCan('hotspot','create') && $hasRouterLink): ?><a class="btn btn-primary" href="packages_add.php">Add Hotspot Plan</a><?php endif; ?></div>
 <?php if($loadError): ?><div class="alert alert-warning"><?=e($loadError)?></div><?php endif; ?>
 <div class="table-responsive"><table><thead><tr><th>Plan</th><th>Duration</th><th>Price</th><th>Router</th><th>Status</th><th>Action</th></tr></thead><tbody>
 <?php foreach($rows as $row): ?><tr><td><strong><?=e($row['name']??'—')?></strong></td><td><?=number_format((int)($row['duration_minutes']??0))?> min</td><td><?=e(formatMoney($row['price']??0))?></td><td><?=e($row['router_display_name']??'—')?></td><td><?=e(ucfirst((string)($row['status']??'unknown')))?></td><td><?php if(userCan('hotspot','edit')): ?><a class="btn btn-secondary btn-sm" href="edit.php?id=<?=(int)$row['id']?>">Edit</a><?php else: ?>—<?php endif; ?></td></tr><?php endforeach; ?>
 <?php if(!$rows): ?><tr><td colspan="6">No hotspot plans configured yet.</td></tr><?php endif; ?>
 </tbody></table></div>
</div>
<?php require_once '../includes/footer.php'; ?>
<style>
.dashboard-card{background:linear-gradient(145deg,#101827,#0b111b)!important;border:1px solid rgba(148,163,184,.16)!important;border-radius:14px;color:#f8fafc;box-shadow:0 16px 40px rgba(0,0,0,.22)}.dashboard-card h2,.dashboard-card th{color:#f8fafc}.dashboard-card p{color:#94a3b8}.dashboard-card table{width:100%;border-collapse:collapse}.dashboard-card th,.dashboard-card td{padding:12px 14px;text-align:left;border-bottom:1px solid rgba(148,163,184,.13)}.dashboard-card th{background:#0b111b;color:#cbd5e1}.dashboard-card td{color:#e2e8f0}.dashboard-card tr:hover{background:rgba(37,99,235,.07)}.dashboard-card .btn{padding:8px 12px;border-radius:8px;text-decoration:none}.dashboard-card .btn-primary{background:#2563eb;color:#fff}.dashboard-card .btn-secondary{background:#172235;color:#cbd5e1;border:1px solid rgba(148,163,184,.16)}.dashboard-card .alert-warning{padding:12px;background:rgba(245,158,11,.12);color:#fcd34d;border-radius:8px}
</style>