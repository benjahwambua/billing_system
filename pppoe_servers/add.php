<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
if (isTenantUser()) requireTenant();
requireModulePermission('pppoe', 'create');
global $conn;

$cols=[];
$q=$conn->query("SHOW COLUMNS FROM pppoe_servers");
if(!$q) die('Unable to read PPPoE server schema.');
while($x=$q->fetch_assoc()) $cols[]=$x['Field'];
$has=fn($c)=>in_array($c,$cols,true);
$tid=(int)getCurrentTenantId();
$errors=[];$pools=[];$poolHasTenant=false;$poolHasStatus=false;
$poolCols=[];
$pc=$conn->query("SHOW COLUMNS FROM ip_pools");
if($pc){while($x=$pc->fetch_assoc())$poolCols[]=$x['Field'];}
$poolHasTenant=in_array('tenant_id',$poolCols,true);
$poolHasStatus=in_array('status',$poolCols,true);
if($has('ip_pool_id') && $poolCols && (!$poolHasTenant || $tid>0)){
  $sql="SELECT id,".(in_array('name',$poolCols,true)?"name":(in_array('pool_name',$poolCols,true)?"pool_name":"id"))." AS pool_label".($poolHasStatus?",status":"")." FROM ip_pools";
  $where=[];$params=[];$types='';
  if($poolHasTenant){$where[]='tenant_id=?';$params[]=$tid;$types.='i';}
  if($poolHasStatus)$where[]="status='active'";
  if($where)$sql.=' WHERE '.implode(' AND ',$where);
  $sql.=' ORDER BY id DESC LIMIT 500';
  $ps=$conn->prepare($sql);
  if($ps){if($params)$ps->bind_param($types,...$params);$ps->execute();$pr=$ps->get_result();while($x=$pr->fetch_assoc())$pools[]=$x;$ps->close();}
}
$data=['name'=>'','router_id'=>'','interface'=>'','service_name'=>'','ip_pool_id'=>'','status'=>'active'];
if($_SERVER['REQUEST_METHOD']==='POST'){
  requireCsrf();
  foreach($data as $k=>$v)$data[$k]=trim((string)($_POST[$k]??$v));
  if(($has('name')||$has('server_name')) && $data['name']==='')$errors[]='Server name is required.';
  if(!in_array($data['status'],['active','inactive'],true))$errors[]='Invalid server status.';
  $poolId=(int)$data['ip_pool_id'];
  if($poolId>0){
    if(!$has('ip_pool_id'))$errors[]='Run database migration 034 before assigning an IP pool.';
    elseif(!$poolCols || !$poolHasTenant || $tid<=0)$errors[]='IP pool assignment requires tenant-scoped IP pools and a valid tenant context.';
    else{
      $poolSql="SELECT id FROM ip_pools WHERE id=? AND tenant_id=?".($poolHasStatus?" AND status='active'":"")." LIMIT 1";
      $check=$conn->prepare($poolSql);
      if(!$check)$errors[]='Unable to validate selected IP pool.';
      else{$check->bind_param('ii',$poolId,$tid);$check->execute();$found=$check->get_result()->fetch_assoc();$check->close();if(!$found)$errors[]='Choose an active IP pool belonging to this tenant.';}
    }
  }
  if(!$errors){
    $map=['name'=>$data['name'],'server_name'=>$data['name'],'router_id'=>(int)$data['router_id'],'interface'=>$data['interface'],'service_name'=>$data['service_name'],'ip_pool_id'=>$poolId?:null,'status'=>$data['status']];
    $fields=[];$values=[];$types='';
    foreach($map as $col=>$value)if($has($col)&&!in_array($col,$fields,true)){$fields[]=$col;$values[]=$value;$types.=is_int($value)?'i':($value===null?'s':'s');}
    if($has('tenant_id')){$fields[]='tenant_id';$values[]=$tid;$types.='i';}
    if(!$fields)$errors[]='No compatible PPPoE server fields were found.';
    else{
      $stmt=$conn->prepare('INSERT INTO pppoe_servers ('.implode(',',$fields).') VALUES ('.implode(',',array_fill(0,count($fields),'?')).')');
      if(!$stmt)$errors[]='Unable to prepare PPPoE server save.';
      else{
        // MySQLi binds null values correctly when the parameter type is string.
        $stmt->bind_param($types,...$values);
        if($stmt->execute())redirect('index.php');
        $errors[]='Unable to save PPPoE server: '.$stmt->error;$stmt->close();
      }
    }
  }
}
?>
<?php require '../includes/header.php';?>
<div class="page-content"><div class="page-header"><div><h1>Add PPPoE Server</h1><p>Associate a tenant IP pool with this server for the next provisioning stage.</p></div><a class="btn btn-secondary" href="index.php">Back</a></div>
<?php if($errors):?><div class="alert alert-danger"><ul><?php foreach(array_unique($errors) as $error):?><li><?=e($error)?></li><?php endforeach;?></ul></div><?php endif;?>
<div class="card" style="max-width:800px;padding:22px"><form method="post"><?=csrfField()?>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
<?php if($has('name')||$has('server_name')):?><label>Name<input name="name" required value="<?=e($data['name'])?>"></label><?php endif;?>
<?php if($has('router_id')):?><label>Router ID<input type="number" min="1" name="router_id" value="<?=e($data['router_id'])?>"></label><?php endif;?>
<?php if($has('interface')):?><label>Interface<input name="interface" placeholder="ether1" value="<?=e($data['interface'])?>"></label><?php endif;?>
<?php if($has('service_name')):?><label>Service Name<input name="service_name" value="<?=e($data['service_name'])?>"></label><?php endif;?>
<?php if($has('ip_pool_id')):?><label>IP Pool<select name="ip_pool_id"><option value="">No pool assigned</option><?php foreach($pools as $pool):?><option value="<?=$pool['id']?>" <?=((string)$pool['id']===$data['ip_pool_id']?'selected':'')?>><?=e($pool['pool_label']??('Pool #'.$pool['id']))?> (ID <?=$pool['id']?>)</option><?php endforeach;?></select><small>Only active pools from this tenant are listed.</small></label><?php endif;?>
<?php if($has('status')):?><label>Status<select name="status"><option value="active" <?=$data['status']==='active'?'selected':''?>>Active</option><option value="inactive" <?=$data['status']==='inactive'?'selected':''?>>Inactive</option></select></label><?php endif;?>
</div><button class="btn btn-primary" style="margin-top:18px">Save Server</button></form></div></div>
<?php require '../includes/footer.php';?>
