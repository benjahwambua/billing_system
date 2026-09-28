<?php
require_once '../includes/auth.php'; requireActiveUser(); requireTenantContext(); requireModulePermission('staff','view');
global $conn; $tenantId=(int)getCurrentTenantId();
$modules=['dashboard'=>'Dashboard','customers'=>'Customers','internet_plans'=>'Internet Plans','internet_accounts'=>'Internet Accounts','subscriptions'=>'Subscriptions','routers'=>'Routers','pppoe'=>'PPPoE','network'=>'Network','billing'=>'Billing','invoices'=>'Invoices','payments'=>'Payments','expenses'=>'Expenses','reports'=>'Reports','communication'=>'Communication','hotspot'=>'Hotspot','ai'=>'AI','staff'=>'Staff','settings'=>'Settings'];
$roles=[];
if(flexihubTableHasColumn('access_roles','tenant_id')){
 $q=$conn->prepare("SELECT id,name,description,is_system,status FROM access_roles WHERE tenant_id=? ORDER BY name");
 if($q){$q->bind_param('i',$tenantId);$q->execute();$z=$q->get_result();while($x=$z->fetch_assoc())$roles[]=$x;$q->close();}
}
if($_SERVER['REQUEST_METHOD']==='POST' && userCan('staff','edit')){
 requireCsrf();
 $roleId=(int)($_POST['role_id']??0); $name=trim($_POST['name']??''); $description=trim($_POST['description']??'');
 if($roleId>0 && $name!==''){
  $q=$conn->prepare("UPDATE access_roles SET name=?,description=? WHERE id=? AND tenant_id=?");
  if($q){$q->bind_param('ssii',$name,$description,$roleId,$tenantId);$q->execute();$q->close();}
 } elseif($name!==''){
  $q=$conn->prepare("INSERT INTO access_roles(tenant_id,name,description) VALUES(?,?,?)");
  if($q){$q->bind_param('iss',$tenantId,$name,$description);$q->execute();$roleId=$q->insert_id;$q->close();}
 }
 if($roleId>0){
  foreach($modules as $key=>$label){
   $v=isset($_POST['p'][$key]['view'])?1:0;$c=isset($_POST['p'][$key]['create'])?1:0;$e=isset($_POST['p'][$key]['edit'])?1:0;$d=isset($_POST['p'][$key]['delete'])?1:0;$a=isset($_POST['p'][$key]['approve'])?1:0;
   $q=$conn->prepare("INSERT INTO access_role_permissions(tenant_id,role_id,module_key,can_view,can_create,can_edit,can_delete,can_approve) VALUES(?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE can_view=VALUES(can_view),can_create=VALUES(can_create),can_edit=VALUES(can_edit),can_delete=VALUES(can_delete),can_approve=VALUES(can_approve)");
   if($q){$q->bind_param('iisiiiii',$tenantId,$roleId,$key,$v,$c,$e,$d,$a);$q->execute();$q->close();}
  }
 }
 header('Location: index.php?saved=1');exit;
}
$selected=(int)($_GET['role']??($roles[0]['id']??0)); $perms=[];
if($selected && flexihubTableHasColumn('access_role_permissions','module_key')){
 $q=$conn->prepare("SELECT * FROM access_role_permissions WHERE role_id=? AND tenant_id=?");if($q){$q->bind_param('ii',$selected,$tenantId);$q->execute();$z=$q->get_result();while($x=$z->fetch_assoc())$perms[$x['module_key']]=$x;$q->close();}
}
$pageTitle='Roles & Permissions';require_once '../includes/header.php';?>
<div class="dashboard-card"><h2>Roles & Permissions</h2><p>Create roles and control each module with View, Create, Edit, Delete and Approve.</p>
<form method="post"><?=csrfField()?><input type="hidden" name="role_id" value="<?=e($selected)?>">
<div class="form-group"><label>Role name</label><input name="name" required value="<?=e($roles[array_search($selected,array_column($roles,'id'))]['name']??'')?>"></div>
<div class="form-group"><label>Description</label><input name="description" value="<?=e($roles[array_search($selected,array_column($roles,'id'))]['description']??'')?>"></div>
<div class="table-responsive"><table><thead><tr><th>Module</th><th>View</th><th>Create</th><th>Edit</th><th>Delete</th><th>Approve</th></tr></thead><tbody>
<?php foreach($modules as $k=>$label):$p=$perms[$k]??[];?><tr><td><?=e($label)?></td><?php foreach(['view','create','edit','delete','approve'] as $act):?><td><input type="checkbox" name="p[<?=e($k)?>][<?=e($act)?>]" <?=!empty($p['can_'.$act])?'checked':''?>></td><?php endforeach;?></tr><?php endforeach;?></tbody></table></div>
<?php if(userCan('staff','edit')):?><button type="submit">Save Role & Permissions</button><?php endif;?></form></div><?php require_once '../includes/footer.php';?>