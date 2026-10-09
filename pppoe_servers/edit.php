<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
if(isTenantUser())requireTenant();
requireModulePermission('pppoe','edit');
global $conn;
$id=(int)($_GET['id']??0);if($id<=0)die('Invalid PPPoE server ID.');
$cols=[];$q=$conn->query("SHOW COLUMNS FROM pppoe_servers");if(!$q)die('Unable to read PPPoE server schema.');while($x=$q->fetch_assoc())$cols[]=$x['Field'];
$has=fn($c)=>in_array($c,$cols,true);$tid=(int)getCurrentTenantId();
$sql="SELECT * FROM pppoe_servers WHERE id=?";$params=[$id];$types='i';
if($has('tenant_id')){if($tid<=0)die('A valid tenant context is required.');$sql.=' AND tenant_id=?';$params[]=$tid;$types.='i';}
$st=$conn->prepare($sql);$st->bind_param($types,...$params);$st->execute();$row=$st->get_result()->fetch_assoc();$st->close();if(!$row)die('PPPoE server not found.');
$poolCols=[];$pc=$conn->query("SHOW COLUMNS FROM ip_pools");if($pc)while($x=$pc->fetch_assoc())$poolCols[]=$x['Field'];
$poolHasTenant=in_array('tenant_id',$poolCols,true);$poolHasStatus=in_array('status',$poolCols,true);$pools=[];
if($has('ip_pool_id')&&$poolCols&&(!$poolHasTenant||$tid>0)){
  $labelCol=in_array('name',$poolCols,true)?'name':(in_array('pool_name',$poolCols,true)?'pool_name':'id');
  $poolSql="SELECT id,$labelCol AS pool_label".($poolHasStatus?",status":"")." FROM ip_pools";
  $w=[];$pp=[];$pt='';
  if($poolHasTenant){$w[]='tenant_id=?';$pp[]=$tid;$pt.='i';}
  if($w)$poolSql.=' WHERE '.implode(' AND ',$w);
  $poolSql.=' ORDER BY id DESC LIMIT 500';$ps=$conn->prepare($poolSql);
  if($ps){if($pp)$ps->bind_param($pt,...$pp);$ps->execute();$rr=$ps->get_result();while($x=$rr->fetch_assoc())$pools[]=$x;$ps->close();}
}
$data=['name'=>(string)($row['name']??$row['server_name']??''),'router_id'=>(string)($row['router_id']??''),'interface'=>(string)($row['interface']??''),'service_name'=>(string)($row['service_name']??''),'ip_pool_id'=>(string)($row['ip_pool_id']??''),'status'=>(string)($row['status']??'active'),'description'=>(string)($row['description']??'')];$errors=[];
if($_SERVER['REQUEST_METHOD']==='POST'){
 requireCsrf();foreach($data as $k=>$v)$data[$k]=trim((string)($_POST[$k]??$v));
 if(!in_array($data['status'],['active','inactive'],true))$errors[]='Invalid server status.';
 $poolId=(int)$data['ip_pool_id'];
 if($poolId>0){
  if(!$has('ip_pool_id'))$errors[]='Run database migration 034 before assigning an IP pool.';
  elseif(!$poolHasTenant||$tid<=0)$errors[]='IP pool assignment requires tenant-scoped IP pools and a valid tenant context.';
  else{$poolSql="SELECT id".($poolHasStatus?",status":"")." FROM ip_pools WHERE id=? AND tenant_id=? LIMIT 1";$check=$conn->prepare($poolSql);if(!$check)$errors[]='Unable to validate selected IP pool.';else{$check->bind_param('ii',$poolId,$tid);$check->execute();$found=$check->get_result()->fetch_assoc();$check->close();if(!$found)$errors[]='Selected IP pool does not belong to this tenant.';elseif($poolHasStatus&&strtolower((string)$found['status'])!=='active')$errors[]='Only active IP pools can be assigned to a PPPoE server.';}}
 }
 if(!$errors){
  $map=['name'=>$data['name'],'server_name'=>$data['name'],'router_id'=>(int)$data['router_id'],'interface'=>$data['interface'],'service_name'=>$data['service_name'],'ip_pool_id'=>$poolId?:null,'status'=>$data['status'],'description'=>$data['description']];
  $sets=[];$vals=[];$bt='';
  foreach($map as $c=>$v)if($has($c)&&!in_array($c,$sets,true)){$sets[]=$c.'=?';$vals[]=$v;$bt.=is_int($v)?'i':'s';}
  if(!$sets)$errors[]='No compatible PPPoE server fields were found.';
  else{$vals[]=$id;$bt.='i';$sql='UPDATE pppoe_servers SET '.implode(',',$sets).' WHERE id=?';if($has('tenant_id')){$sql.=' AND tenant_id=?';$vals[]=$tid;$bt.='i';}$u=$conn->prepare($sql);if(!$u)$errors[]='Unable to prepare PPPoE server update.';else{$u->bind_param($bt,...$vals);if($u->execute())redirect('index.php');$errors[]='Unable to update PPPoE server: '.$u->error;$u->close();}}
 }
}
?>
<?php require '../includes/header.php';?>
<div class="page-content"><div class="page-header"><div><h1>Edit PPPoE Server</h1><p>Assign a tenant IP pool to this PPPoE server.</p></div><a class="btn btn-secondary" href="index.php">Back</a></div>
<?php if($errors):?><div class="alert alert-danger"><ul><?php foreach(array_unique($errors) as $error):?><li><?=e($error)?></li><?php endforeach;?></ul></div><?php endif;?>
<div class="card" style="max-width:800px;padding:22px"><form method="post"><?=csrfField()?><div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
<?php if($has('name')||$has('server_name')):?><label>Name<input name="name" required value="<?=e($data['name'])?>"></label><?php endif;?>
<?php if($has('router_id')):?><label>Router ID<input type="number" min="1" name="router_id" value="<?=e($data['router_id'])?>"></label><?php endif;?>
<?php if($has('interface')):?><label>Interface<input name="interface" value="<?=e($data['interface'])?>"></label><?php endif;?>
<?php if($has('service_name')):?><label>Service Name<input name="service_name" value="<?=e($data['service_name'])?>"></label><?php endif;?>
<?php if($has('ip_pool_id')):?><label>IP Pool<select name="ip_pool_id"><option value="">No pool assigned</option><?php foreach($pools as $pool):$inactive=$poolHasStatus&&strtolower((string)($pool['status']??''))!=='active';?><option value="<?=$pool['id']?>" <?=((string)$pool['id']===$data['ip_pool_id']?'selected':'')?>><?=e($pool['pool_label']??('Pool #'.$pool['id']))?><?= $inactive?' — INACTIVE':'' ?> (ID <?=$pool['id']?>)</option><?php endforeach;?></select><small>Only pools belonging to this tenant are shown. Inactive pools cannot be newly assigned.</small></label><?php endif;?>
<?php if($has('status')):?><label>Status<select name="status"><option value="active" <?=$data['status']==='active'?'selected':''?>>Active</option><option value="inactive" <?=$data['status']==='inactive'?'selected':''?>>Inactive</option></select></label><?php endif;?>
<?php if($has('description')):?><label style="grid-column:1/-1">Description<input name="description" value="<?=e($data['description'])?>"></label><?php endif;?>
</div><button class="btn btn-primary" style="margin-top:18px">Update Server</button></form></div></div>
<?php require '../includes/footer.php';?>
