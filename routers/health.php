<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/mikrotik_api.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('network','view');
global $conn;

$tenantId=(int)getCurrentTenantId();
if(!$tenantId) die('Tenant context is required.');

$routers=[];
$stmt=$conn->prepare("SELECT * FROM mikrotik_routers WHERE tenant_id=? ORDER BY id ASC");
if($stmt){
    $stmt->bind_param('i',$tenantId);
    $stmt->execute();
    $res=$stmt->get_result();
    while($row=$res->fetch_assoc()) $routers[]=$row;
    $stmt->close();
}

function routerPick(array $row,array $names,$default=''){
    foreach($names as $name) if(array_key_exists($name,$row) && $row[$name] !== '') return $row[$name];
    return $default;
}

$results=[];
foreach($routers as $router){
    $host=routerPick($router,['host','ip_address','ip']);
    $username=routerPick($router,['username','user']);
    $password=routerPick($router,['password','api_password']);
    $port=(int)routerPick($router,['api_port','port'],8728);
    $name=routerPick($router,['name','router_name'],'Router #'.$router['id']);
    $item=['id'=>(int)$router['id'],'name'=>$name,'host'=>$host,'status'=>'failed','identity'=>'','message'=>''];
    if($host==='' || $username===''){
        $item['message']='Router connection details are incomplete.';
        $results[]=$item;
        continue;
    }
    try{
        $ros=new FlexihubRouterOS($host,$username,$password,$port,6);
        $rows=$ros->command(['/system/identity/print','=.proplist=name']);
        $identity='';
        foreach($rows as $row){
            if(($row['!type']??'')==='!re' && !empty($row['name'])){$identity=(string)$row['name'];break;}
        }
        $ros->close();
        $item['status']='online';
        $item['identity']=$identity ?: 'RouterOS';
        $item['message']='API connection successful.';
    }catch(Throwable $e){
        $item['message']=substr($e->getMessage(),0,240);
    }
    $results[]=$item;
}

$online=0;
foreach($results as $item) if($item['status']==='online') $online++;
$pageTitle='Network Health';
require_once '../includes/header.php';
?>
<div class="page-content">
<div class="page-header">
  <div><h1>Network Health</h1><p>Live MikroTik API connectivity check for all routers in this tenant.</p></div>
  <a class="btn btn-secondary" href="index.php">Back to Routers</a>
</div>

<div class="dashboard-card" style="margin-bottom:20px;padding:20px">
  <div style="display:flex;gap:28px;flex-wrap:wrap">
    <div><small>Total Routers</small><h2><?=count($results)?></h2></div>
    <div><small>Reachable</small><h2><?=e($online)?></h2></div>
    <div><small>Unreachable</small><h2><?=e(count($results)-$online)?></h2></div>
  </div>
</div>

<div class="dashboard-card">
<div class="table-responsive"><table>
<thead><tr><th>Router</th><th>Host</th><th>Status</th><th>Identity</th><th>Result</th><th>Action</th></tr></thead>
<tbody>
<?php foreach($results as $item): ?>
<tr>
<td><strong><?=e($item['name'])?></strong></td>
<td><?=e($item['host'])?></td>
<td><span class="status-badge <?=$item['status']==='online'?'active':'inactive'?>"><?=e(ucfirst($item['status']))?></span></td>
<td><?=e($item['identity'] ?: '—')?></td>
<td><?=e($item['message'])?></td>
<td><a class="btn btn-secondary" href="test.php?id=<?=$item['id']?>">Test</a></td>
</tr>
<?php endforeach; ?>
<?php if(!$results): ?><tr><td colspan="6">No MikroTik routers have been configured for this tenant.</td></tr><?php endif; ?>
</tbody></table></div>
</div>
</div>
<?php require_once '../includes/footer.php'; ?>
