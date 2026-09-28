<?php
require_once '../includes/auth.php';requireActiveUser();requireTenantContext();requireModulePermission('staff','view');
global $conn;$tenantId=getCurrentTenantId();
$modules=['dashboard'=>'Dashboard','customers'=>'Customers','internet_plans'=>'Internet Plans','internet_accounts'=>'Internet Accounts','routers'=>'Routers','pppoe'=>'PPPoE','network'=>'Network','orders'=>'Orders','invoices'=>'Invoices','payments'=>'Payments','receipts'=>'Receipts','expenses'=>'Expenses','reports'=>'Reports','communication'=>'Communication','staff'=>'Staff','settings'=>'Settings','hotspot'=>'Hotspot','ai'=>'AI'];
$roles=[];
if(flexihubTableHasColumn('roles','tenant_id')){
 $st=$conn->prepare("SELECT id,name,description,is_system FROM roles WHERE tenant_id=? ORDER BY name");
 if($st){$st->bind_param('i',$tenantId);$st->execute();$rr=$st->get_result();while($x=$rr->fetch_assoc())$roles[]=$x;$st->close();}
}
$pageTitle='Roles & Permissions';require_once '../includes/header.php';?>
<div class="dashboard-card"><h2>Roles & Permissions</h2><p>Tenant-level access control using View, Create, Edit, Delete and Approve.</p>
<?php if(!$roles):?><p>No roles have been configured yet. Configure tenant roles and permissions before assigning restricted access.</p>
<?php else:?><div class="table-responsive"><table><thead><tr><th>Module</th><?php foreach($roles as $r):?><th><?=e($r['name'])?></th><?php endforeach;?></tr></thead><tbody>
<?php foreach($modules as $key=>$label):?><tr><td><?=e($label)?></td><?php foreach($roles as $r):?><td><small><?php
$st=$conn->prepare("SELECT can_view,can_create,can_edit,can_delete,can_approve FROM role_permissions WHERE tenant_id=? AND role_id=? AND module_key=? LIMIT 1");
$v=['view'=>0,'create'=>0,'edit'=>0,'delete'=>0,'approve'=>0];if($st){$st->bind_param('iis',$tenantId,$r['id'],$key);$st->execute();if($x=$st->get_result()->fetch_assoc()){$v=['view'=>(int)$x['can_view'],'create'=>(int)$x['can_create'],'edit'=>(int)$x['can_edit'],'delete'=>(int)$x['can_delete'],'approve'=>(int)$x['can_approve']];}$st->close();}
echo e('V:'.$v['view'].' C:'.$v['create'].' E:'.$v['edit'].' D:'.$v['delete'].' A:'.$v['approve']);?></small></td><?php endforeach;?></tr><?php endforeach;?></tbody></table></div><?php endif;?></div><?php require_once '../includes/footer.php';?>