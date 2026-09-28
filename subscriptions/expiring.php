<?php
require_once '../includes/auth.php'; requireActiveUser(); requireTenantContext(); requireModulePermission('subscriptions','view');
global $conn; $tenantId=(int)getCurrentTenantId(); $rows=[]; $table='internet_accounts'; $display='expiry_date';
if(flexihubTableHasColumn($table,'tenant_id')){
 $sql="SELECT * FROM ".$table." WHERE tenant_id=? ORDER BY id DESC LIMIT 200"; $q=$conn->prepare($sql);
 if($q){$q->bind_param('i',$tenantId);$q->execute();$z=$q->get_result();while($r=$z->fetch_assoc())$rows[]=$r;$q->close();}
}
$pageTitle='Expiring Accounts'; require_once '../includes/header.php';?>
<div class="dashboard-card"><h2><?=e($pageTitle)?></h2><p>Tenant-scoped expiring accounts workspace.</p>
<?php if(!$rows):?><p>No records available yet.</p><?php else:?><div class="table-responsive"><table><thead><tr><?php foreach(array_keys($rows[0]) as $k):?><th><?=e(ucwords(str_replace('_',' ',$k)))?></th><?php endforeach;?></tr></thead><tbody><?php foreach($rows as $row):?><tr><?php foreach($row as $v):?><td><?=e($v)?></td><?php endforeach;?></tr><?php endforeach;?></tbody></table></div><?php endif;?></div><?php require_once '../includes/footer.php';?>