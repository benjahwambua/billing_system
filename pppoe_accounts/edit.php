<?php
require_once '../includes/auth.php';require_once '../includes/functions.php';if(isTenantUser())requireTenant();requireModulePermission('pppoe','edit');global $conn;
$id=(int)($_GET['id']??0);if(!$id)die('Invalid PPPoE account.');
$cols=[];$q=$conn->query("SHOW COLUMNS FROM pppoe_accounts");while($x=$q->fetch_assoc())$cols[]=$x['Field'];$has=fn($c)=>in_array($c,$cols,true);$tid=getCurrentTenantId();
$where='pa.id=?';$params=[$id];$types='i';if(isTenantUser()){if(!$has('tenant_id')||$tid<=0)die('Tenant-scoped PPPoE account access is unavailable for this schema.');$where.=' AND pa.tenant_id=?';$params[]=$tid;$types.='i';}elseif($has('tenant_id')&&$tid>0){$where.=' AND pa.tenant_id=?';$params[]=$tid;$types.='i';}
$s=$conn->prepare("SELECT pa.*,ia.account_number FROM pppoe_accounts pa LEFT JOIN internet_accounts ia ON ia.id=pa.internet_account_id WHERE $where LIMIT 1");$s->bind_param($types,...$params);$s->execute();$row=$s->get_result()->fetch_assoc();if(!$row)die('PPPoE account not found.');$s->close();$errors=[];
if($_SERVER['REQUEST_METHOD']==='POST'){requireCsrf();$f=[];$v=[];$t='';$map=['username'=>trim($_POST['username']??''),'status'=>$_POST['status']??'active'];foreach($map as $c=>$x)if($has($c)){$f[]="$c=?";$v[]=$x;$t.='s';}if($has('password')&&($_POST['password']??'')!==''){$f[]='password=?';$v[]=$_POST['password'];$t.='s';}if($f){$v[]=$id;$t.='i';$sql="UPDATE pppoe_accounts SET ".implode(',',$f)." WHERE id=?";if(isTenantUser()){if(!$has('tenant_id')||$tid<=0){$errors[]='Tenant-scoped PPPoE account updates are unavailable for this schema.';$sql='';}else{$sql.=' AND tenant_id=?';$v[]=$tid;$t.='i';}}elseif($has('tenant_id')&&$tid>0){$sql.=' AND tenant_id=?';$v[]=$tid;$t.='i';}$u=$sql!==''?$conn->prepare($sql):false;if($u){$u->bind_param($t,...$v);if($u->execute()){
 $oldUsername=trim((string)($row['username']??''));$newUsername=trim((string)($_POST['username']??$oldUsername));
 $newPassword=(string)($_POST['password']??'');$syncPassword=$newPassword!==''?$newPassword:(string)($row['password']??'');
 $newStatus=strtolower(trim((string)($_POST['status']??($row['status']??'active'))));
 try{
  if(empty($row['pppoe_server_id']))throw new Exception('This account has no assigned PPPoE server.');
  $srvQ=$conn->prepare("SELECT ps.*,mr.* FROM pppoe_servers ps LEFT JOIN mikrotik_routers mr ON mr.id=ps.router_id AND mr.tenant_id=ps.tenant_id WHERE ps.id=? AND ps.tenant_id=? LIMIT 1");
  if(!$srvQ)throw new Exception('Unable to load the assigned PPPoE server.');
  $sid=(int)$row['pppoe_server_id'];$srvQ->bind_param('ii',$sid,$tid);$srvQ->execute();$router=$srvQ->get_result()->fetch_assoc();$srvQ->close();
  if(!$router)throw new Exception('The assigned PPPoE server or router was not found for this tenant.');
  $routerCols=flexihubTableColumns('mikrotik_routers');
  $pick=function($names,$default='')use($router,$routerCols){foreach($names as $n)if(in_array($n,$routerCols,true)&&isset($router[$n])&&$router[$n]!=='')return $router[$n];return $default;};
  $host=$pick(['host','ip_address','ip']);$ruser=$pick(['username','user']);$rpass=$pick(['password','api_password']);$rport=(int)$pick(['api_port','port'],8728);
  if($host===''||$ruser==='')throw new Exception('MikroTik connection details are incomplete.');
  $ros=new FlexihubRouterOS($host,$ruser,$rpass,$rport,8);
  try{
   $profileName='default';$poolId=(int)($router['ip_pool_id']??0);
   if($poolId>0){
    $poolCols=flexihubTableColumns('ip_pools');if(!in_array('tenant_id',$poolCols,true))throw new Exception('IP pool schema is not tenant-scoped.');
    $poolSql="SELECT * FROM ip_pools WHERE id=? AND tenant_id=?".(in_array('status',$poolCols,true)?" AND status='active'":'')." LIMIT 1";
    $poolStmt=$conn->prepare($poolSql);if(!$poolStmt)throw new Exception('Unable to load assigned IP pool.');
    $poolStmt->bind_param('ii',$poolId,$tid);$poolStmt->execute();$pool=$poolStmt->get_result()->fetch_assoc();$poolStmt->close();
    if(!$pool)throw new Exception('Assigned IP pool is missing, inactive, or belongs to another tenant.');
    $start=trim((string)($pool['start_ip']??''));$end=trim((string)($pool['end_ip']??''));$gateway=trim((string)($pool['gateway']??''));
    if(!filter_var($start,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4)||!filter_var($end,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4)||ip2long($start)>ip2long($end))throw new Exception('Assigned IP pool range is invalid.');
    if($gateway!==''&&!filter_var($gateway,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4))throw new Exception('Assigned IP pool gateway is invalid.');
    $poolName='fh_t'.(int)$tid.'_pool'.(int)$poolId;$profileName='fh_t'.(int)$tid.'_pppoe'.(int)$sid;
    $ros->addOrUpdateIpPool($poolName,$start.'-'.$end);$ros->addOrUpdatePppProfile($profileName,$poolName,$gateway);
   }
   $ros->updatePppSecret($oldUsername,$newUsername,$syncPassword,$newStatus!=='active','pppoe',$profileName);
   if($newStatus!=='active')$ros->disconnectPppActive($newUsername);
  }finally{$ros->close();}
  redirect('index.php');
 }catch(Throwable $e){
  if($has('status')){$pending=$conn->prepare("UPDATE pppoe_accounts SET status='pending_activation' WHERE id=?".($has('tenant_id')?' AND tenant_id=?':''));if($pending){if($has('tenant_id'))$pending->bind_param('ii',$id,$tid);else$pending->bind_param('i',$id);$pending->execute();$pending->close();}}
  $errors[]='Account saved but MikroTik synchronization failed: '.$e->getMessage();
 }
}else $errors[]=$u->error;}}}
?>
<?php require '../includes/header.php';?><div class="page-content"><div class="page-header"><div><h1>Edit PPPoE Account</h1><p>Update credentials and status.</p></div><a class="btn btn-secondary" href="index.php">Back</a></div><?php if($errors):?><div class="alert alert-danger"><?=e(implode(' ',$errors))?></div><?php endif;?><div class="card" style="max-width:760px;padding:22px"><p><strong>Internet Account:</strong> <?=e($row['account_number']??'')?></p><form method="post"><?=csrfField()?><div style="display:grid;grid-template-columns:1fr 1fr;gap:16px"><?php if($has('username')):?><label>Username<input name="username" value="<?=e($_POST['username']??($row['username']??''))?>" required></label><?php endif;?><?php if($has('password')):?><label>New Password<input type="password" name="password" autocomplete="new-password" placeholder="Leave blank to keep current"></label><?php endif;?><?php if($has('status')):?><label>Status<select name="status"><?php foreach(['active','inactive','suspended'] as $x):?><option value="<?=$x?>" <?=strtolower($_POST['status']??($row['status']??''))===$x?'selected':''?>><?=ucfirst($x)?></option><?php endforeach;?></select></label><?php endif;?></div><button class="btn btn-primary" style="margin-top:18px">Update PPPoE Account</button></form></div></div><?php require '../includes/footer.php';?>