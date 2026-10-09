<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/ip_pool_validation.php';
if(isTenantUser()) requireTenant();
global $conn;
$cols=[];$q=$conn->query("SHOW COLUMNS FROM ip_pools");if(!$q)die('Unable to read IP pool schema.');while($x=$q->fetch_assoc())$cols[]=$x['Field'];
$has=fn($c)=>in_array($c,$cols,true);$tid=(int)getCurrentTenantId();$errors=[];
$data=['name'=>'','network'=>'','cidr'=>'','gateway'=>'','start_ip'=>'','end_ip'=>'','status'=>'active','description'=>''];
if($_SERVER['REQUEST_METHOD']==='POST'){
 requireCsrf();
 foreach($data as $k=>$v)$data[$k]=trim((string)($_POST[$k]??$v));
 $data['status']=strtolower($data['status']);
 if(!in_array($data['status'],['active','inactive'],true))$errors[]='Invalid pool status.';
 $errors=array_merge($errors,flexihubValidateIpPool($conn,$tid,$data,$cols));
 if(!$errors){
  $map=['name'=>$data['name'],'pool_name'=>$data['name'],'network'=>$data['network'],'cidr'=>$data['cidr']!==''?$data['cidr']:$data['network'],'gateway'=>$data['gateway'],'start_ip'=>$data['start_ip'],'end_ip'=>$data['end_ip'],'status'=>$data['status'],'description'=>$data['description']];
  $f=[];$v=[];$types='';
  foreach($map as $c=>$value)if($has($c)&&!in_array($c,$f,true)){$f[]=$c;$v[]=$value;$types.='s';}
  if($has('tenant_id')){$f[]='tenant_id';$v[]=$tid;$types.='i';}
  if(!$f)$errors[]='No compatible IP pool fields were found.';
  else{$st=$conn->prepare('INSERT INTO ip_pools ('.implode(',',$f).') VALUES ('.implode(',',array_fill(0,count($f),'?')).')');
   if($st){$st->bind_param($types,...$v);if($st->execute()){ $st->close();redirect('index.php'); }$errors[]='Unable to save IP pool: '.$st->error;$st->close();}
   else $errors[]='Unable to prepare IP pool save.';
  }
 }
}
?>
<?php require '../includes/header.php';?>
<div class="page-content"><div class="page-header"><div><h1>Add IP Pool</h1><p>Validate subnet and allocation ranges before they are used for subscriber services.</p></div><a class="btn btn-secondary" href="index.php">Back</a></div>
<?php if($errors):?><div class="alert alert-danger"><ul><?php foreach(array_unique($errors) as $error):?><li><?=e($error)?></li><?php endforeach;?></ul></div><?php endif;?>
<div class="card" style="max-width:800px;padding:22px"><form method="post"><?=csrfField()?><div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
<label>Name<input name="name" required value="<?=e($data['name'])?>"></label>
<label>Network / CIDR<input name="network" value="<?=e($data['network'])?>" placeholder="192.168.10.0/24"><small>Enter the subnet address with prefix length.</small></label>
<label>Gateway<input name="gateway" value="<?=e($data['gateway'])?>" placeholder="192.168.10.1"></label>
<label>Start IP<input name="start_ip" value="<?=e($data['start_ip'])?>" placeholder="192.168.10.10"></label>
<label>End IP<input name="end_ip" value="<?=e($data['end_ip'])?>" placeholder="192.168.10.240"></label>
<label>Status<select name="status"><option value="active" <?=$data['status']==='active'?'selected':''?>>Active</option><option value="inactive" <?=$data['status']==='inactive'?'selected':''?>>Inactive</option></select></label>
<label style="grid-column:1/-1">Description<input name="description" value="<?=e($data['description'])?>"></label></div>
<button class="btn btn-primary" style="margin-top:18px">Save IP Pool</button></form></div></div>
<?php require '../includes/footer.php';?>
