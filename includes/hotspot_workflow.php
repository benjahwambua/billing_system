<?php
require_once __DIR__.'/mikrotik_api.php';

if(!function_exists('flexihubHotspotRouter')) {
function flexihubHotspotRouter($tenantId,$routerId) {
 global $conn;
 $q=$conn->prepare("SELECT * FROM mikrotik_routers WHERE id=? AND tenant_id=? LIMIT 1");
 if(!$q)return null;
 $q->bind_param('ii',$routerId,$tenantId);$q->execute();$r=$q->get_result()->fetch_assoc();$q->close();
 if(!$r)return null;
 $cols=flexihubTableColumns('mikrotik_routers');
 $pick=function($names,$default='')use($r,$cols){foreach($names as $n)if(in_array($n,$cols,true)&&isset($r[$n])&&$r[$n]!=='')return $r[$n];return $default;};
 return ['id'=>$routerId,'host'=>$pick(['host','ip_address','ip']),'username'=>$pick(['username','user']),'password'=>$pick(['password','api_password']),'port'=>(int)$pick(['api_port','port'],8728)];
}}
if(!function_exists('flexihubAuthorizeHotspot')) {
function flexihubAuthorizeHotspot($tenantId,$packageId,$username,$password,$macAddress='') {
 global $conn;
 $tenantId=(int)$tenantId;$packageId=(int)$packageId;$username=trim($username);$password=(string)$password;$macAddress=trim($macAddress);
 if(!$tenantId||!$packageId||$username===''||$password==='')throw new Exception('Hotspot package and credentials are required.');
 $q=$conn->prepare("SELECT * FROM hotspot_packages WHERE id=? AND tenant_id=? AND status='active' LIMIT 1");
 if(!$q)throw new Exception('Unable to load hotspot package.');
 $q->bind_param('ii',$packageId,$tenantId);$q->execute();$pkg=$q->get_result()->fetch_assoc();$q->close();
 if(!$pkg)throw new Exception('Hotspot package not found or inactive.');
 $routerId=(int)($pkg['router_id']??0); if(!$routerId)throw new Exception('Hotspot package has no MikroTik router assigned.');
 $router=flexihubHotspotRouter($tenantId,$routerId); if(!$router||$router['host']===''||$router['username']==='')throw new Exception('Hotspot router connection details are incomplete.');
 $minutes=max(1,(int)($pkg['duration_minutes']??0));$ros=new FlexihubRouterOS($router['host'],$router['username'],$router['password'],$router['port'],8);
 try {
   $profile='default';$secret=['name'=>$username,'password'=>$password,'profile'=>$profile,'limit-uptime'=>$minutes.'m'];
   if($macAddress!=='')$secret['mac-address']=$macAddress;
   $existing=$ros->command(['/ip/hotspot/user/print','=.proplist=.id,name','=name='.$username]);
   $id=null;foreach($existing as $row)if(($row['!type']??'')==='!re'&&($row['name']??'')===$username)$id=$row['.id']??null;
   if($id){$words=['/ip/hotspot/user/set','=.id='.$id,'=password='.$password,'=limit-uptime='.$minutes.'m'];if($macAddress!=='')$words[]='=mac-address='.$macAddress;$ros->command($words);}
   else{$words=['/ip/hotspot/user/add','=name='.$username,'=password='.$password,'=limit-uptime='.$minutes.'m'];if($macAddress!=='')$words[]='=mac-address='.$macAddress;$ros->command($words);}
 } finally {$ros->close();}
 $started=date('Y-m-d H:i:s');$expires=date('Y-m-d H:i:s',time()+($minutes*60));$sid='HS-'.date('YmdHis').'-'.strtoupper(substr(bin2hex(random_bytes(5)),0,8));
 $insert=flexihubWorkflowInsert('hotspot_sessions',['tenant_id'=>$tenantId,'package_id'=>$packageId,'router_id'=>$routerId,'session_identifier'=>$sid,'username'=>$username,'mac_address'=>$macAddress?:null,'started_at'=>$started,'expires_at'=>$expires,'status'=>'authorized']);
 if(!$insert)throw new Exception('Router authorization succeeded but the hotspot session could not be recorded.');
 flexihubWorkflowInsert('router_sync_logs',['tenant_id'=>$tenantId,'router_id'=>$routerId,'service_type'=>'hotspot','external_username'=>$username,'action'=>'authorize','status'=>'success','message'=>'Hotspot user authorized on MikroTik.']);
 return ['session_id'=>$insert,'session_identifier'=>$sid,'expires_at'=>$expires,'router_id'=>$routerId];
}}
if(!function_exists('flexihubProcessHotspotExpiry')) {
function flexihubProcessHotspotExpiry($tenantId,$limit=50) {
 global $conn;
 $tenantId=(int)$tenantId;$limit=max(1,min(200,(int)$limit));
 $rows=[];$q=$conn->prepare("SELECT hs.*,hp.router_id FROM hotspot_sessions hs JOIN hotspot_packages hp ON hp.id=hs.package_id AND hp.tenant_id=hs.tenant_id WHERE hs.tenant_id=? AND hs.status IN ('authorized','active') AND hs.expires_at IS NOT NULL AND hs.expires_at<=NOW() ORDER BY hs.id ASC LIMIT ".$limit);
 if(!$q)return ['processed'=>0,'expired'=>0,'failed'=>0];
 $q->bind_param('i',$tenantId);$q->execute();$rs=$q->get_result();while($r=$rs->fetch_assoc())$rows[]=$r;$q->close();
 $processed=0;$expired=0;$failed=0;
 foreach($rows as $row){
  $processed++;$sessionId=(int)$row['id'];$routerId=(int)$row['router_id'];$username=trim((string)($row['username']??''));
  try{
   $router=flexihubHotspotRouter($tenantId,$routerId);
   if(!$router||$router['host']===''||$router['username']==='')throw new Exception('Hotspot router connection details are incomplete.');
   $ros=new FlexihubRouterOS($router['host'],$router['username'],$router['password'],$router['port'],8);
   try { if($username!==''){$ros->disconnectHotspotActive($username);$ros->setHotspotUserDisabled($username,true);} } finally {$ros->close();}
   $u=$conn->prepare("UPDATE hotspot_sessions SET status='expired',ended_at=NOW(),disconnect_reason='package_expired' WHERE id=? AND tenant_id=? AND status IN ('authorized','active')");if($u){$u->bind_param('ii',$sessionId,$tenantId);$u->execute();$u->close();}
   flexihubWorkflowInsert('router_sync_logs',['tenant_id'=>$tenantId,'router_id'=>$routerId,'service_type'=>'hotspot','external_username'=>$username,'action'=>'expire','status'=>'success','message'=>'Hotspot access expired and router session disconnected.']);
   $expired++;
  }catch(Throwable $e){
   $failed++;
   flexihubWorkflowInsert('router_sync_logs',['tenant_id'=>$tenantId,'router_id'=>$routerId,'service_type'=>'hotspot','external_username'=>$username,'action'=>'expire','status'=>'failed','message'=>substr($e->getMessage(),0,500)]);
  }
 }
 return ['processed'=>$processed,'expired'=>$expired,'failed'=>$failed];
}}

?>