<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/mikrotik_api.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('pppoe','view');
global $conn;
$tenantId=(int)getCurrentTenantId();
$routerCols=[];
$cr=$conn->query("SHOW COLUMNS FROM mikrotik_routers");
if($cr){while($c=$cr->fetch_assoc())$routerCols[]=$c['Field'];}
$hasRouterCol=fn($c)=>in_array($c,$routerCols,true);
$hostCol=null;foreach(['host','ip_address','ip'] as $c)if($hasRouterCol($c)){$hostCol=$c;break;}
$nameCol=null;foreach(['name','router_name'] as $c)if($hasRouterCol($c)){$nameCol=$c;break;}
$portCol=$hasRouterCol('api_port')?'api_port':($hasRouterCol('port')?'port':null);
$routers=[];$errors=[];$sessions=[];
if($hostCol && $hasRouterCol('username') && $hasRouterCol('password')){
 $where=[];$params=[];$types='';
 if($hasRouterCol('tenant_id')){$where[]='tenant_id=?';$params[]=$tenantId;$types='i';}
 if($hasRouterCol('status'))$where[]="LOWER(status)='active'";
 $sql="SELECT id".($nameCol?",$nameCol AS router_name":",id AS router_name").", $hostCol AS host, username, password".($portCol?",$portCol AS api_port":",8728 AS api_port")." FROM mikrotik_routers".($where?' WHERE '.implode(' AND ',$where):'')." ORDER BY id";
 $st=$conn->prepare($sql);
 if($st){if($params)$st->bind_param($types,...$params);$st->execute();$rs=$st->get_result();while($router=$rs->fetch_assoc())$routers[]=$router;$st->close();}
}
foreach($routers as $router){
 try{
  $api=new FlexihubRouterOS($router['host'],$router['username'],$router['password'],(int)$router['api_port'],5);
  $rows=$api->command(['/ppp/active/print','=proplist=.id,name,service,caller-id,address,uptime,encoding']);
  foreach($rows as $row){
   if(($row['!type']??'')!=='!re')continue;
   $sessions[]=['router'=>$router['router_name'],'router_id'=>$router['id'],'username'=>$row['name']??'','service'=>$row['service']??'pppoe','caller_id'=>$row['caller-id']??'','address'=>$row['address']??'','uptime'=>$row['uptime']??'','encoding'=>$row['encoding']??''];
  }
  $api->close();
 }catch(Throwable $e){$errors[]=['router'=>$router['router_name'],'error'=>$e->getMessage()];}
}
$pageTitle='PPPoE Active Sessions';
require_once '../includes/header.php';
?>
<div class="page-content">
 <div class="page-header"><div><h1>PPPoE Active Sessions</h1><p>Live sessions retrieved from tenant-configured MikroTik routers. Refresh the page to poll again.</p></div><a class="btn btn-secondary" href="sessions.php">Refresh</a></div>
 <div class="pppoe-live-metrics"><div class="card"><span>Live Sessions</span><strong><?=count($sessions)?></strong></div><div class="card"><span>Routers Polled</span><strong><?=count($routers)-count($errors)?> / <?=count($routers)?></strong></div><div class="card"><span>Router Errors</span><strong><?=count($errors)?></strong></div></div>
 <?php if(!$routers):?><div class="alert alert-warning">No active, tenant-configured MikroTik routers with API credentials were found. Check router configuration and tenant association.</div><?php endif;?>
 <?php if($errors):?><div class="card" style="margin-bottom:16px;padding:18px"><h2>Router Polling Issues</h2><div style="overflow:auto"><table class="table"><thead><tr><th>Router</th><th>Issue</th></tr></thead><tbody><?php foreach($errors as $er):?><tr><td><?=e($er['router'])?></td><td><?=e($er['error'])?></td></tr><?php endforeach;?></tbody></table></div></div><?php endif;?>
 <div class="card" style="padding:18px"><div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap"><h2>Connected Subscribers</h2><input id="sessionSearch" placeholder="Filter username, IP, caller ID or router" style="max-width:320px"></div><div style="overflow:auto"><table class="table" id="sessionTable"><thead><tr><th>Username</th><th>Router</th><th>IP Address</th><th>Caller ID / MAC</th><th>Service</th><th>Uptime</th></tr></thead><tbody>
 <?php if(!$sessions):?><tr><td colspan="6" style="text-align:center;padding:28px">No active PPPoE sessions were returned by the routers.</td></tr><?php else:foreach($sessions as $s):?><tr><td><strong><?=e($s['username'])?></strong></td><td><?=e($s['router'])?></td><td><?=e($s['address'])?></td><td><?=e($s['caller_id'])?></td><td><?=e($s['service'])?></td><td><?=e($s['uptime'])?></td></tr><?php endforeach;endif;?>
 </tbody></table></div></div>
 <p style="color:#94a3b8;font-size:12px;margin-top:12px">This page performs a live read-only RouterOS query. It does not disconnect subscribers or change router configuration. Router API access must be enabled and reachable from the PHP server.</p>
</div>
<style>.pppoe-live-metrics{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-bottom:18px}.pppoe-live-metrics .card{padding:18px;background:linear-gradient(145deg,#101827,#080d16);border:1px solid rgba(96,165,250,.16);border-radius:12px}.pppoe-live-metrics span{display:block;color:#94a3b8;font-size:12px}.pppoe-live-metrics strong{display:block;color:#f8fafc;font-size:26px;margin-top:8px}.page-content{color:#f8fafc}.page-content .card{background:linear-gradient(145deg,rgba(16,24,39,.97),rgba(7,12,20,.96));border:1px solid rgba(148,163,184,.14);border-radius:14px}.page-content input{background:#0b111b;color:#f8fafc;border:1px solid rgba(148,163,184,.2);padding:10px;border-radius:8px}.table{width:100%;border-collapse:collapse}.table th,.table td{padding:11px;border-bottom:1px solid rgba(148,163,184,.14);text-align:left}.table th{color:#cbd5e1;background:#0b111b}.table td{color:#e2e8f0}@media(max-width:700px){.pppoe-live-metrics{grid-template-columns:1fr}}</style>
<script>document.getElementById('sessionSearch')?.addEventListener('input',function(){const q=this.value.toLowerCase();document.querySelectorAll('#sessionTable tbody tr').forEach(r=>r.style.display=r.innerText.toLowerCase().includes(q)?'':'none')});</script>
<?php require_once '../includes/footer.php'; ?>
