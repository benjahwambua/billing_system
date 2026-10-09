<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/mikrotik_api.php';
if(isTenantUser()) requireTenant();
requireModulePermission('pppoe', 'create');
global $conn;
$cols=[];$q=$conn->query("SHOW COLUMNS FROM pppoe_accounts");while($x=$q->fetch_assoc())$cols[]=$x['Field'];
$has=fn($c)=>in_array($c,$cols,true);$tid=getCurrentTenantId();$errors=[];
$accounts=[];$servers=[];$srv=$conn->prepare("SELECT id,name,server_name FROM pppoe_servers WHERE tenant_id=? AND status='active' ORDER BY id DESC");if($srv){$srv->bind_param('i',$tid);$srv->execute();$zr=$srv->get_result();while($x=$zr->fetch_assoc())$servers[]=$x;$srv->close();}$sql="SELECT ia.id,ia.account_number,c.first_name,c.last_name FROM internet_accounts ia LEFT JOIN customers c ON c.id=ia.customer_id".($tid?" WHERE ia.tenant_id=".(int)$tid:"")." ORDER BY ia.id DESC";
$r=$conn->query($sql);if($r)while($x=$r->fetch_assoc())$accounts[]=$x;
if($_SERVER['REQUEST_METHOD']==='POST'){requireCsrf();$ia=(int)($_POST['internet_account_id']??0);$serverId=(int)($_POST['pppoe_server_id']??0);$user=trim($_POST['username']??'');$secret=$_POST['password']??'';$status=$_POST['status']??'active';
if($has('internet_account_id')&&!$ia)$errors[]='Internet account is required.';
if($has('pppoe_server_id')&&!$serverId)$errors[]='PPPoE server is required.';if($has('username')&&!$user)$errors[]='Username is required.';
if($has('password')&&!$secret)$errors[]='Password is required.';
// Never trust the submitted account ID: confirm it belongs to this tenant before inserting credentials.
if($ia>0){
 if($tid<=0){$errors[]='A valid tenant context is required to create a PPPoE account.';}
 else{
  $accountCheck=$conn->prepare('SELECT id FROM internet_accounts WHERE id=? AND tenant_id=? LIMIT 1');
  if(!$accountCheck){$errors[]='Unable to validate the selected internet account.';}
  else{$accountCheck->bind_param('ii',$ia,$tid);$accountCheck->execute();$accountFound=$accountCheck->get_result()->fetch_assoc();$accountCheck->close();if(!$accountFound)$errors[]='The selected internet account was not found for this tenant.';}
 }
}
if(!$errors){$map=['internet_account_id'=>$ia,'pppoe_server_id'=>$serverId,'username'=>$user,'password'=>$secret,'status'=>$status];$f=[];$v=[];$t='';
foreach($map as $c=>$x)if($has($c)){$f[]=$c;$v[]=$x;$t.=is_int($x)?'i':'s';}
if($has('tenant_id')){$f[]='tenant_id';$v[]=$tid;$t.='i';}
$st=$conn->prepare("INSERT INTO pppoe_accounts (".implode(',',$f).") VALUES (".implode(',',array_fill(0,count($f),'?')).")");
if($st){
 $st->bind_param($t,...$v);
 if($st->execute()){
  $pppoeId=(int)$conn->insert_id;
  try{
   $srvQ=$conn->prepare("SELECT ps.*,mr.* FROM pppoe_servers ps LEFT JOIN mikrotik_routers mr ON mr.id=ps.router_id AND mr.tenant_id=ps.tenant_id WHERE ps.id=? AND ps.tenant_id=? AND ps.status='active' LIMIT 1");
   if(!$srvQ)throw new Exception('Unable to load the selected PPPoE server.');
   $srvQ->bind_param('ii',$serverId,$tid);$srvQ->execute();$router=$srvQ->get_result()->fetch_assoc();$srvQ->close();
   if(!$router)throw new Exception('The selected PPPoE server has no valid MikroTik router assignment.');
   $routerCols=flexihubTableColumns('mikrotik_routers');
   $pick=function($names,$default='')use($router,$routerCols){foreach($names as $n)if(in_array($n,$routerCols,true)&&isset($router[$n])&&$router[$n]!=='')return $router[$n];return $default;};
   $host=$pick(['host','ip_address','ip']);$ruser=$pick(['username','user']);$rpass=$pick(['password','api_password']);$rport=(int)$pick(['api_port','port'],8728);
   if($host===''||$ruser==='')throw new Exception('MikroTik connection details are incomplete.');
   $ros=new FlexihubRouterOS($host,$ruser,$rpass,$rport,8);
   try{
    $profileName='default';
    $serverPoolId=(int)($router['ip_pool_id']??0);
    if($serverPoolId>0){
     $poolCols=flexihubTableColumns('ip_pools');
     if(!in_array('tenant_id',$poolCols,true))throw new Exception('IP pool schema is not tenant-scoped; refusing cross-tenant pool provisioning.');
     $poolSql="SELECT * FROM ip_pools WHERE id=? AND tenant_id=?".(in_array('status',$poolCols,true)?" AND status='active'":"")." LIMIT 1";
     $poolStmt=$conn->prepare($poolSql);
     if(!$poolStmt)throw new Exception('Unable to load the assigned tenant IP pool.');
     $poolStmt->bind_param('ii',$serverPoolId,$tid);$poolStmt->execute();$pool=$poolStmt->get_result()->fetch_assoc();$poolStmt->close();
     if(!$pool)throw new Exception('The PPPoE server assigned pool is missing, inactive, or belongs to another tenant.');
     $poolLabel=trim((string)($pool['name']??$pool['pool_name']??('pool_'.$serverPoolId)));
     $poolStart=trim((string)($pool['start_ip']??''));$poolEnd=trim((string)($pool['end_ip']??''));
     if($poolStart===''||$poolEnd==='')throw new Exception('The assigned IP pool needs both Start IP and End IP configured before RouterOS provisioning.');
     if(!filter_var($poolStart,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4)||!filter_var($poolEnd,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4)||ip2long($poolStart)>ip2long($poolEnd))throw new Exception('The assigned IP pool allocation range is invalid.');
     $routerPoolName='fh_t'.(int)$tid.'_pool'.(int)$serverPoolId;
     $routerProfileName='fh_t'.(int)$tid.'_pppoe'.(int)$serverId;
     $gateway=trim((string)($pool['gateway']??''));
     if($gateway!==''&&!filter_var($gateway,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4))throw new Exception('The assigned IP pool gateway is invalid.');
     $ros->addOrUpdateIpPool($routerPoolName,$poolStart.'-'.$poolEnd);
     $ros->addOrUpdatePppProfile($routerProfileName,$routerPoolName,$gateway);
     $profileName=$routerProfileName;
    }
    $ros->addOrUpdatePppSecret($user,$secret,$status!=='active','pppoe',$profileName);
   }finally{$ros->close();}
   $done=$conn->prepare("UPDATE pppoe_accounts SET status=? WHERE id=? AND tenant_id=?");
   if($done){$doneStatus=$status==='active'?'active':($status?:'inactive');$done->bind_param('sii',$doneStatus,$pppoeId,$tid);$done->execute();$done->close();}
   if(function_exists('flexihubWorkflowInsert')){
    flexihubWorkflowInsert('router_sync_logs',['tenant_id'=>$tid,'router_id'=>(int)$router['router_id'],'service_type'=>'pppoe','external_username'=>$user,'action'=>'provision','status'=>'success','message'=>'PPPoE secret provisioned on MikroTik using profile '.$profileName.'.']);
   }
   redirect('index.php');
  }catch(Throwable $e){
   $msg='Account saved but MikroTik provisioning failed: '.$e->getMessage();
   $pending=$conn->prepare("UPDATE pppoe_accounts SET status='pending_activation' WHERE id=? AND tenant_id=?");
   if($pending){$pending->bind_param('ii',$pppoeId,$tid);$pending->execute();$pending->close();}
   $errors[]=$msg;
  }
 } else $errors[]=$st->error;
}}
}
?>
<?php require '../includes/header.php';?><div class="page-content"><div class="page-header"><div><h1>Add PPPoE Account</h1><p>Create PPPoE credentials for an internet account.</p></div><a class="btn btn-secondary" href="index.php">Back</a></div>
<?php if($errors):?><div class="alert alert-danger"><?=e(implode(' ',$errors))?></div><?php endif;?>
<div class="card" style="max-width:760px;padding:22px"><form method="post"><?=csrfField()?>
<label>PPPoE Server<select name="pppoe_server_id" required><option value="">Select server</option><?php foreach($servers as $s):?><option value="<?=$s['id']?>"><?=e($s['name']??$s['server_name']??('Server #'.$s['id']))?></option><?php endforeach;?></select></label>
<label>Internet Account<select name="internet_account_id" required><option value="">Select account</option><?php foreach($accounts as $a):?><option value="<?=$a['id']?>"><?=e($a['account_number'].' — '.trim(($a['first_name']??'').' '.($a['last_name']??'')))?></option><?php endforeach;?></select></label>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px"><?php if($has('username')):?><label>Username<input name="username" required></label><?php endif;?><?php if($has('password')):?><label>Credential<input type="password" name="password" required autocomplete="new-password"></label><?php endif;?><?php if($has('status')):?><label>Status<select name="status"><option value="active">Active</option><option value="inactive">Inactive</option><option value="suspended">Suspended</option></select></label><?php endif;?></div>
<button class="btn btn-primary" style="margin-top:18px">Save PPPoE Account</button></form></div></div><?php require '../includes/footer.php';?>