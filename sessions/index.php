<?php
require_once '../includes/auth.php';requireActiveUser();requireTenantContext();
$rows=[];$q=$conn->query("SHOW TABLES LIKE 'user_sessions'");
if($q&&$q->num_rows&&flexihubTableHasColumn('user_sessions','tenant_id')){
 $st=$conn->prepare("SELECT id,user_id,ip_address,user_agent,last_activity_at,expires_at,created_at,revoked_at FROM user_sessions WHERE tenant_id=? ORDER BY id DESC LIMIT 200");
 if($st){$tenantId=getCurrentTenantId();$st->bind_param('i',$tenantId);$st->execute();$r=$st->get_result();while($x=$r->fetch_assoc())$rows[]=$x;$st->close();}
}
$pageTitle='Sessions';require_once '../includes/header.php';?>
<div class="dashboard-card"><h2>Active & Recent Sessions</h2><?php if(!$rows):?><p>No session records are available.</p><?php else:?><div class="table-responsive"><table><thead><tr><th>User</th><th>IP</th><th>Last Activity</th><th>Expires</th><th>Created</th><th>Status</th></tr></thead><tbody><?php foreach($rows as $x):?><tr><td><?=e($x['user_id'])?></td><td><?=e($x['ip_address'])?></td><td><?=e($x['last_activity_at'])?></td><td><?=e($x['expires_at'])?></td><td><?=e($x['created_at'])?></td><td><?=e($x['revoked_at']?'Revoked':'Active')?></td></tr><?php endforeach;?></tbody></table></div><?php endif;?></div><?php require_once '../includes/footer.php';?>