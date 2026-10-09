<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/ip_pool_validation.php';
if(isTenantUser()) requireTenant();
requireModulePermission('pppoe', 'edit');
global $conn;
$id=(int)($_GET['id']??0);
$cols=[];$q=$conn->query("SHOW COLUMNS FROM ip_pools");if(!$q)die('Unable to read IP pool schema.');while($x=$q->fetch_assoc())$cols[]=$x['Field'];
$has=fn($c)=>in_array($c,$cols,true);$tid=(int)getCurrentTenantId();
if($id<=0)die('Invalid IP pool ID.');
$sql='SELECT * FROM ip_pools WHERE id=?';$params=[$id];$types='i';
if($has('tenant_id')){if($tid<=0)die('A valid tenant context is required.');$sql.=' AND tenant_id=?';$params[]=$tid;$types.='i';}
$st=$conn->prepare($sql);$st->bind_param($types,...$params);$st->execute();$row=$st->get_result()->fetch_assoc();$st->close();if(!$row)die('IP pool not found.');
$data=['name'=>(string)($row['name']??$row['pool_name']??''),'network'=>(string)($row['network']??''),'cidr'=>(string)($row['cidr']??''),'gateway'=>(string)($row['gateway']??''),'start_ip'=>(string)($row['start_ip']??''),'end_ip'=>(string)($row['end_ip']??''),'status'=>(string)($row['status']??'active'),'description'=>(string)($row['description']??'')];$errors=[];
if($_SERVER['REQUEST_METHOD']==='POST'){
 requireCsrf();foreach($data as $k=>$v)$data[$k]=trim((string)($_POST[$k]??$v));$data['status']=strtolower($data['status']);
 if(!in_array($data['status'],['active','inactive'],true))$errors[]='Invalid pool status.';
 $errors=array_merge($errors,flexihubValidateIpPool($conn,$tid,$data,$cols,$id));
 if(!$errors){
  $map=['name'=>$data['name'],'pool_name'=>$data['name'],'network'=>$data['network'],'cidr'=>$data['cidr']!==''?$data['cidr']:$data['network'],'gateway'=>$data['gateway'],'start_ip'=>$data['start_ip'],'end_ip'=>$data['end_ip'],'status'=>$data['status'],'description'=>$data['description']];
  $sets=[];$vals=[];$bt='';
  foreach($map as $c=>$value)if($has($c)&&!in_array($c,$sets,true)){$sets[]=$c.'=?';$vals[]=$value;$bt.='s';}
  $vals[]=$id;$bt.='i';$where=' WHERE id=?';if($has('tenant_id')){$where.=' AND tenant_id=?';$vals[]=$tid;$bt.='i';}
  $u=$conn->prepare('UPDATE ip_pools SET '.implode(',',$sets).$where);
  if($u){$u->bind_param($bt,...$vals);if($u->execute()){$u->close();redirect('index.php');}$errors[]='Unable to update IP pool: '.$u->error;$u->close();}
  else $errors[]='Unable to prepare IP pool update.';
 }
}
?>
<?php require '../includes/header.php';?>
<div class="page-content"><div class="page-header"><div><h1>Edit IP Pool</h1><p>Subnet and allocation changes are checked for invalid or overlapping ranges.</p></div><a class="btn btn-secondary" href="index.php">Back</a></div>
<?php if($errors):?><div class="alert alert-danger"><ul><?php foreach(array_unique($errors) as $error):?><li><?=e($error)?></li><?php endforeach;?></ul></div><?php endif;?>
<div class="card" style="max-width:800px;padding:22px"><form method="post"><?=csrfField()?><div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
<label>Name<input name="name" required value="<?=e($data['name'])?>"></label>
<label>Network / CIDR<input name="network" value="<?=e($data['network']!==''?$data['network']:$data['cidr'])?>" placeholder="192.168.10.0/24"><small>Use the subnet network address with prefix length.</small></label>
<label>Gateway<input name="gateway" value="<?=e($data['gateway'])?>"></label>
<label>Start IP<input name="start_ip" value="<?=e($data['start_ip'])?>"></label>
<label>End IP<input name="end_ip" value="<?=e($data['end_ip'])?>"></label>
<label>Status<select name="status"><option value="active" <?=$data['status']==='active'?'selected':''?>>Active</option><option value="inactive" <?=$data['status']==='inactive'?'selected':''?>>Inactive</option></select></label>
<label style="grid-column:1/-1">Description<input name="description" value="<?=e($data['description'])?>"></label></div>
<button class="btn btn-primary" style="margin-top:18px">Update IP Pool</button></form></div></div>
<?php require '../includes/footer.php';?>
