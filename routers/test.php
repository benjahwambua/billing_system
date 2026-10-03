<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/mikrotik_api.php';
requireActiveUser();
if (isTenantUser()) requireTenant();
global $conn;
$tenantId=(int)getCurrentTenantId();
$id=(int)($_GET['id']??0);
if(!$id) die('Router ID is required.');
$stmt=$conn->prepare("SELECT * FROM mikrotik_routers WHERE id=? AND tenant_id=? LIMIT 1");
if(!$stmt) die('Unable to load router.');
$stmt->bind_param('ii',$id,$tenantId);$stmt->execute();$router=$stmt->get_result()->fetch_assoc();$stmt->close();
if(!$router) die('Router not found.');
$cols=flexihubTableColumns('mikrotik_routers');
$pick=function($names,$default='')use($router,$cols){foreach($names as $n)if(in_array($n,$cols,true)&&isset($router[$n])&&$router[$n]!=='' )return $router[$n];return $default;};
$host=$pick(['host','ip_address','ip']);$username=$pick(['username','user']);$password=$pick(['password','api_password']);$port=(int)$pick(['api_port','port'],8728);
$message='';$ok=false;
if($_SERVER['REQUEST_METHOD']==='POST'){
 requireCsrf();
 try{
  $ros=new FlexihubRouterOS($host,$username,$password,$port,8);
  $rows=$ros->command(['/system/identity/print','=.proplist=name']);
  $identity='RouterOS';
  foreach($rows as $row)if(($row['!type']??'')==='!re'&&!empty($row['name'])){$identity=$row['name'];break;}
  $ros->close();$ok=true;$message='Connection successful. Router identity: '.$identity.'.';
 }catch(Throwable $e){$message=$e->getMessage();}
}
?>
<?php require '../includes/header.php';?>
<div class="page-content">
 <div class="page-header"><div><h1>Test MikroTik Connection</h1><p>Verify that Flexihub can reach and authenticate to this router using the configured API credentials.</p></div><a class="btn btn-secondary" href="edit.php?id=<?=e($id)?>">Back</a></div>
 <?php if($message):?><div class="alert <?= $ok?'alert-success':'alert-danger' ?>"><?=e($message)?></div><?php endif;?>
 <div class="card" style="max-width:760px;padding:22px">
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px">
   <div><strong>Router</strong><div><?=e($pick(['name','router_name'],'Router #'.$id))?></div></div>
   <div><strong>Host / IP</strong><div><?=e($host?:'Not configured')?></div></div>
   <div><strong>API Port</strong><div><?=e($port)?></div></div>
   <div><strong>Username</strong><div><?=e($username?:'Not configured')?></div></div>
  </div>
  <form method="post"><?=csrfField()?><button class="btn btn-primary">Test Connection</button></form>
 </div>
</div>
<?php require '../includes/footer.php';?>