<?php
require_once '../includes/auth.php'; requireActiveUser(); requireTenantContext(); requireModulePermission('hotspot','view');
global $conn; $tenantId=(int)getCurrentTenantId(); $rows=[];
$q=$conn->prepare("SELECT c.*, p.name package_name FROM hotspot_access_codes c LEFT JOIN hotspot_packages p ON p.id=c.package_id AND p.tenant_id=c.tenant_id WHERE c.tenant_id=? ORDER BY c.id DESC LIMIT 200");
if($q){$q->bind_param('i',$tenantId);$q->execute();$z=$q->get_result();while($r=$z->fetch_assoc())$rows[]=$r;$q->close();}
$pageTitle='Access Codes';require_once '../includes/header.php';?>
<div class="dashboard-card"><div style="display:flex;justify-content:space-between;align-items:center"><h2>Hotspot Access Codes</h2><?php if(userCan('hotspot','create')):?><a href="access_codes_add.php">Generate Codes</a><?php endif;?></div>
<?php if(!$rows):?><p>No access codes generated yet.</p><?php else:?><div class="table-responsive"><table><thead><tr><th>Code</th><th>Plan</th><th>Status</th><th>Expires</th><th>Created</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><?=e($r['code'])?></td><td><?=e($r['package_name']??'—')?></td><td><?=e($r['status'])?></td><td><?=e($r['expires_at']??'—')?></td><td><?=e($r['created_at']??'')?></td></tr><?php endforeach;?></tbody></table></div><?php endif;?></div><?php require_once '../includes/footer.php';?>