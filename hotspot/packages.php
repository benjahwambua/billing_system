<?php
require_once '../includes/auth.php';requireActiveUser();requireTenantContext();requireModulePermission('hotspot','view');
$tenantId=(int)getCurrentTenantId();$rows=[];$q=$conn->query("SHOW TABLES LIKE 'hotspot_packages'");
if($q&&$q->num_rows){$r=$conn->query("SELECT * FROM hotspot_packages WHERE tenant_id=".$tenantId." ORDER BY id DESC LIMIT 200");if($r)while($x=$r->fetch_assoc())$rows[]=$x;}
$pageTitle='Hotspot Plans';require_once '../includes/header.php';?>
<div class="dashboard-card"><h2>Hotspot Plans</h2>
<?php if(!$rows):?><p>No hotspot plans configured yet.</p><?php else:?><div class="table-responsive"><table><thead><tr><?php foreach(array_keys($rows[0]) as $k):?><th><?=e(ucwords(str_replace('_',' ',$k)))?></th><?php endforeach;?></tr></thead><tbody><?php foreach($rows as $x):?><tr><?php foreach($x as $v):?><td><?=e($v)?></td><?php endforeach;?></tr><?php endforeach;?></tbody></table></div><?php endif;?></div>
<?php require_once '../includes/footer.php';?>