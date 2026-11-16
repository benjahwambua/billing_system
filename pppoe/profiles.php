<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/billing_workflow.php';
require_once '../includes/mikrotik_api.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('pppoe','view');

global $conn;
$tenantId=(int)getCurrentTenantId();
$routerColumns=flexihubTableColumns('mikrotik_routers');
$pickColumn=static function(array $columns,array $names){foreach($names as $name)if(in_array($name,$columns,true))return $name;return null;};
$hostColumn=$pickColumn($routerColumns,['host','ip_address','ip']);
$nameColumn=$pickColumn($routerColumns,['name','router_name']);
$portColumn=$pickColumn($routerColumns,['api_port','port']);
$routers=[];
$profiles=[];
$errors=[];

if($hostColumn && in_array('username',$routerColumns,true) && in_array('password',$routerColumns,true) && in_array('tenant_id',$routerColumns,true)){
    $sql="SELECT id".($nameColumn?", \`{$nameColumn}\` AS router_name":", CONCAT('Router #',id) AS router_name").", \`{$hostColumn}\` AS host,username,password".($portColumn?", \`{$portColumn}\` AS api_port":",8728 AS api_port")." FROM mikrotik_routers WHERE tenant_id=?";
    if(in_array('status',$routerColumns,true))$sql.=" AND LOWER(status)='active'";
    $sql.=" ORDER BY id";
    $stmt=$conn->prepare($sql);
    if($stmt){
        $stmt->bind_param('i',$tenantId);
        if($stmt->execute()){$result=$stmt->get_result();while($row=$result->fetch_assoc())$routers[]=$row;}
        $stmt->close();
    }
}

foreach($routers as $router){
    try{
        if(trim((string)$router['host'])===''||trim((string)$router['username'])==='')throw new RuntimeException('Router API connection details are incomplete.');
        $api=new FlexihubRouterOS((string)$router['host'],(string)$router['username'],(string)$router['password'],(int)$router['api_port'],5);
        try{
            $result=$api->command(['/ppp/profile/print','=.proplist=.id,name,local-address,remote-address,rate-limit,only-one,use-compression']);
            foreach($result as $row){
                if(($row['!type']??'')!=='!re')continue;
                $profiles[]=[
                    'router'=>$router['router_name'],
                    'router_id'=>(int)$router['id'],
                    'name'=>(string)($row['name']??''),
                    'local_address'=>(string)($row['local-address']??''),
                    'remote_address'=>(string)($row['remote-address']??''),
                    'rate_limit'=>(string)($row['rate-limit']??''),
                    'only_one'=>(string)($row['only-one']??''),
                    'compression'=>(string)($row['use-compression']??'')
                ];
            }
        }finally{$api->close();}
    }catch(Throwable $e){$errors[]=['router'=>$router['router_name'],'error'=>$e->getMessage()];}
}

$pageTitle='PPPoE Profiles';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<div class="main-content pppoe-profiles">
 <div class="page-header"><div><h1>PPPoE Profiles</h1><p>Read-only profile inventory polled from the active MikroTik routers assigned to this organisation.</p></div><a class="btn btn-secondary" href="profiles.php">Refresh</a></div>
 <div class="profile-metrics"><div><small>Routers polled</small><strong><?=count($routers)-count($errors)?> / <?=count($routers)?></strong></div><div><small>Profiles discovered</small><strong><?=count($profiles)?></strong></div><div><small>Router errors</small><strong><?=count($errors)?></strong></div></div>
 <?php if(!$routers): ?><div class="profile-alert">No active tenant MikroTik routers with API credentials were found. Profile discovery is read-only and does not change router configuration.</div><?php endif; ?>
 <?php if($errors): ?><section class="profile-panel"><h2>Router Polling Issues</h2><?php foreach($errors as $error): ?><p><strong><?=e($error['router'])?>:</strong> <?=e($error['error'])?></p><?php endforeach; ?></section><?php endif; ?>
 <section class="profile-panel"><h2>Discovered Profiles</h2><div class="profile-table-wrap"><table><thead><tr><th>Router</th><th>Profile</th><th>Local Address</th><th>Remote Address / Pool</th><th>Rate Limit</th><th>Only One</th></tr></thead><tbody>
 <?php foreach($profiles as $profile): ?><tr><td><?=e($profile['router'])?></td><td><strong><?=e($profile['name'])?></strong></td><td><?=e($profile['local_address']?:'—')?></td><td><?=e($profile['remote_address']?:'—')?></td><td><?=e($profile['rate_limit']?:'—')?></td><td><?=e($profile['only_one']?:'—')?></td></tr><?php endforeach; ?>
 <?php if(!$profiles): ?><tr><td colspan="6">No PPP profiles were returned by the configured routers.</td></tr><?php endif; ?>
 </tbody></table></div></section>
</div>
<style>
.pppoe-profiles{padding:24px;color:#e2e8f0}.pppoe-profiles .page-header{display:flex;justify-content:space-between;align-items:center;gap:14px;margin-bottom:18px}.pppoe-profiles h1{margin:0 0 5px;color:#f8fafc}.pppoe-profiles .page-header p{margin:0;color:#94a3b8}.profile-metrics{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-bottom:16px}.profile-metrics>div,.profile-panel{padding:18px;border:1px solid rgba(148,163,184,.15);border-radius:14px;background:linear-gradient(145deg,#101827,#0b111b)}.profile-metrics small{display:block;color:#94a3b8}.profile-metrics strong{display:block;color:#f8fafc;font-size:23px;margin-top:6px}.profile-panel{margin-bottom:16px}.profile-panel h2{margin:0 0 14px;color:#f8fafc;font-size:17px}.profile-panel p{color:#cbd5e1}.profile-table-wrap{overflow:auto}.profile-table-wrap table{width:100%;min-width:760px;border-collapse:collapse}.profile-table-wrap th,.profile-table-wrap td{padding:12px;border-bottom:1px solid rgba(148,163,184,.13);text-align:left;color:#e2e8f0}.profile-table-wrap th{background:#0b111b;color:#94a3b8}.profile-alert{padding:14px;border-radius:9px;margin-bottom:16px;background:rgba(245,158,11,.12);color:#fcd34d}.pppoe-profiles .btn{padding:9px 13px;border-radius:8px;text-decoration:none}.pppoe-profiles .btn-secondary{background:#172235;color:#cbd5e1;border:1px solid rgba(148,163,184,.16)}@media(max-width:700px){.pppoe-profiles{padding:14px}.profile-metrics{grid-template-columns:1fr}.pppoe-profiles .page-header{align-items:flex-start;flex-direction:column}}
</style>
<?php require_once '../includes/footer.php'; ?>
