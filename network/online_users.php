<?php
require_once '../includes/auth.php';requireLogin();requireTenantContext();$tenantId=getCurrentTenantId();$rows=[];
if(flexihubTableHasColumn('active_sessions','tenant_id')){
 $st=$conn->prepare("SELECT * FROM active_sessions WHERE tenant_id=? ORDER BY id DESC LIMIT 200");
 if($st){$st->bind_param('i',$tenantId);$st->execute();$r=$st->get_result();while($x=$r->fetch_assoc())$rows[]=$x;$st->close();}
}
$pageTitle='Online Users';require_once '../includes/header.php';?><div class="dashboard-card"><h2>Online Users</h2><?php if(!$rows):?><p>No active users found.</p><?php else:?><div class="table-responsive"><table><thead><tr><?php foreach(array_keys($rows[0]) as $k):?><th><?=e(ucwords(str_replace('_',' ',$k)))?></th><?php endforeach;?></tr></thead><tbody><?php foreach($rows as $x):?><tr><?php foreach($x as $v):?><td><?=e($v)?></td><?php endforeach;?></tr><?php endforeach;?></tbody></table></div><?php endif;?></div><?php require_once '../includes/footer.php';?>