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
   $existing=$ros->command(['/ip/hotspot/user/print','=.proplist=.id,name','=name='.$username]);
   $id=null;foreach($existing as $row)if(($row['!type']??'')==='!re'&&($row['name']??'')===$username)$id=$row['.id']??null;
   if($id){$words=['/ip/hotspot/user/set','=.id='.$id,'=password='.$password,'=limit-uptime='.$minutes.'m','=disabled=no'];if($macAddress!=='')$words[]='=mac-address='.$macAddress;$ros->command($words);}
   else{$words=['/ip/hotspot/user/add','=name='.$username,'=password='.$password,'=limit-uptime='.$minutes.'m','=disabled=no'];if($macAddress!=='')$words[]='=mac-address='.$macAddress;$ros->command($words);}
 } finally {$ros->close();}
 $started=date('Y-m-d H:i:s');$expires=date('Y-m-d H:i:s',time()+($minutes*60));$sid='HS-'.date('YmdHis').'-'.strtoupper(substr(bin2hex(random_bytes(5)),0,8));
 $insert=flexihubWorkflowInsert('hotspot_sessions',['tenant_id'=>$tenantId,'package_id'=>$packageId,'router_id'=>$routerId,'session_identifier'=>$sid,'username'=>$username,'mac_address'=>$macAddress?:null,'started_at'=>$started,'expires_at'=>$expires,'status'=>'authorized']);
 if(!$insert)throw new Exception('Router authorization succeeded but the hotspot session could not be recorded.');
 flexihubWorkflowInsert('router_sync_logs',['tenant_id'=>$tenantId,'router_id'=>$routerId,'service_type'=>'hotspot','external_username'=>$username,'action'=>'authorize','status'=>'success','message'=>'Hotspot user authorized on MikroTik.']);
 return ['session_id'=>$insert,'session_identifier'=>$sid,'expires_at'=>$expires,'router_id'=>$routerId];
}}

if(!function_exists('flexihubRenewHotspotAuthorization')) {
function flexihubRenewHotspotAuthorization($tenantId,$packageId,$username,$password,$macAddress='',$previousSessionId=0,$previousExpiresAt=null) {
 global $conn;
 $tenantId=(int)$tenantId;$packageId=(int)$packageId;$username=trim($username);$password=(string)$password;$macAddress=trim($macAddress);$previousSessionId=(int)$previousSessionId;
 if(!$tenantId||!$packageId||$username===''||$password==='')throw new Exception('Hotspot renewal credentials are required.');
 $q=$conn->prepare("SELECT * FROM hotspot_packages WHERE id=? AND tenant_id=? AND status='active' LIMIT 1");
 if(!$q)throw new Exception('Unable to load hotspot renewal package.');
 $q->bind_param('ii',$packageId,$tenantId);$q->execute();$pkg=$q->get_result()->fetch_assoc();$q->close();
 if(!$pkg)throw new Exception('Hotspot renewal package not found or inactive.');
 $routerId=(int)($pkg['router_id']??0);if(!$routerId)throw new Exception('Hotspot package has no MikroTik router assigned.');
 $router=flexihubHotspotRouter($tenantId,$routerId);if(!$router||$router['host']===''||$router['username']==='')throw new Exception('Hotspot router connection details are incomplete.');
 $baseRemaining=0;
 if($previousExpiresAt){$remaining=strtotime((string)$previousExpiresAt)-time();if($remaining>0)$baseRemaining=$remaining;}
 $minutes=max(1,(int)($pkg['duration_minutes']??0))+intdiv($baseRemaining+59,60);
 $ros=new FlexihubRouterOS($router['host'],$router['username'],$router['password'],$router['port'],8);
 try {
   if($username!=='')$ros->disconnectHotspotActive($username);
   $existing=$ros->findHotspotUser($username);$id=null;
   foreach($existing as $row)if(($row['!type']??'')==='!re'&&($row['name']??'')===$username)$id=$row['.id']??null;
   $words=$id?['/ip/hotspot/user/set','=.id='.$id,'=password='.$password,'=limit-uptime='.$minutes.'m','=disabled=no']:['/ip/hotspot/user/add','=name='.$username,'=password='.$password,'=limit-uptime='.$minutes.'m','=disabled=no'];
   if($macAddress!=='')$words[]='=mac-address='.$macAddress;$ros->command($words);
 } finally {$ros->close();}
 $started=date('Y-m-d H:i:s');$expires=date('Y-m-d H:i:s',time()+($minutes*60));$sid='HS-'.date('YmdHis').'-'.strtoupper(substr(bin2hex(random_bytes(5)),0,8));
 if($previousSessionId){$u=$conn->prepare("UPDATE hotspot_sessions SET status='renewed',ended_at=NOW(),disconnect_reason='renewed' WHERE id=? AND tenant_id=? AND status IN ('authorized','active')");if($u){$u->bind_param('ii',$previousSessionId,$tenantId);$u->execute();$u->close();}}
 $insert=flexihubWorkflowInsert('hotspot_sessions',['tenant_id'=>$tenantId,'package_id'=>$packageId,'router_id'=>$routerId,'session_identifier'=>$sid,'username'=>$username,'mac_address'=>$macAddress?:null,'started_at'=>$started,'expires_at'=>$expires,'status'=>'authorized']);
 if(!$insert)throw new Exception('Router renewal succeeded but the new hotspot session could not be recorded.');
 flexihubWorkflowInsert('router_sync_logs',['tenant_id'=>$tenantId,'router_id'=>$routerId,'service_type'=>'hotspot','external_username'=>$username,'action'=>'renew','status'=>'success','message'=>'Hotspot user renewed on MikroTik.']);
 return ['session_id'=>$insert,'session_identifier'=>$sid,'expires_at'=>$expires,'router_id'=>$routerId];
}}

if(!function_exists('flexihubQueueHotspotFinalization')) {
function flexihubQueueHotspotFinalization($tenantId,$saleId,$gatewayTransactionId=0) {
 global $conn;
 $q=$conn->prepare("INSERT INTO hotspot_finalization_queue (tenant_id,sale_id,gateway_transaction_id,status,attempts,available_at) VALUES (?,?,?,'pending',0,NOW()) ON DUPLICATE KEY UPDATE gateway_transaction_id=VALUES(gateway_transaction_id),status=IF(status='completed','completed','pending'),available_at=NOW(),last_error=NULL");
 if(!$q)return false;$q->bind_param('iii',$tenantId,$saleId,$gatewayTransactionId);$ok=$q->execute();$q->close();return $ok;
}}

if(!function_exists('flexihubProcessHotspotFinalizationQueue')) {
function flexihubProcessHotspotFinalizationQueue($tenantId,$limit=25) {
 global $conn;
 $rows=[];$limit=max(1,min(100,(int)$limit));
 $q=$conn->prepare("SELECT * FROM hotspot_finalization_queue WHERE tenant_id=? AND status='pending' AND available_at<=NOW() AND attempts<8 ORDER BY id ASC LIMIT ".$limit);
 if(!$q)return ['processed'=>0,'completed'=>0,'failed'=>0];
 $q->bind_param('i',$tenantId);$q->execute();$rs=$q->get_result();while($r=$rs->fetch_assoc())$rows[]=$r;$q->close();
 $processed=0;$completed=0;$failed=0;
 foreach($rows as $job){
  $id=(int)$job['id'];$saleId=(int)$job['sale_id'];
  $claim=$conn->prepare("UPDATE hotspot_finalization_queue SET status='processing',attempts=attempts+1 WHERE id=? AND tenant_id=? AND status='pending'");
  $claimed=false;
  if($claim){$claim->bind_param('ii',$id,$tenantId);$claim->execute();$claimed=($claim->affected_rows===1);$claim->close();}
  if(!$claimed)continue;
  $processed++;
  try {
   $q=$conn->prepare("SELECT * FROM hotspot_sales WHERE id=? AND tenant_id=? LIMIT 1");if(!$q)throw new Exception('Unable to load hotspot sale.');
   $q->bind_param('ii',$saleId,$tenantId);$q->execute();$sale=$q->get_result()->fetch_assoc();$q->close();if(!$sale)throw new Exception('Hotspot sale not found.');
   if(in_array($sale['status'],['access_active','completed','renewed'],true)){
    $u=$conn->prepare("UPDATE hotspot_finalization_queue SET status='completed',processed_at=NOW(),last_error=NULL WHERE id=? AND tenant_id=? AND status='processing'");if($u){$u->bind_param('ii',$id,$tenantId);$u->execute();$u->close();}$completed++;continue;
   }
   $phone=(string)($sale['phone_number']??'');$mac=trim((string)($sale['device_mac']??''));
   $sql="SELECT hs.*,hss.id AS previous_session_id,hss.expires_at AS previous_expires_at FROM hotspot_sales hs LEFT JOIN hotspot_sessions hss ON hss.id=(SELECT x.id FROM hotspot_sessions x WHERE x.tenant_id=hs.tenant_id AND x.username=hs.hotspot_username AND x.status IN ('authorized','active') ORDER BY x.id DESC LIMIT 1) WHERE hs.tenant_id=? AND hs.id<>? AND hs.status='access_active' AND hs.hotspot_username IS NOT NULL AND hs.hotspot_username<>'' AND hs.expires_at>NOW() AND ((?<>'' AND hs.device_mac=?) OR (?<>'' AND hs.phone_number=?)) ORDER BY hs.id DESC LIMIT 1";
   $q=$conn->prepare($sql);if(!$q)throw new Exception('Unable to inspect previous hotspot access.');$q->bind_param('iissss',$tenantId,$saleId,$mac,$mac,$phone,$phone);$q->execute();$previous=$q->get_result()->fetch_assoc();$q->close();
   $username=$previous?(string)$previous['hotspot_username']:'HS'.strtoupper(substr(hash('sha256',$tenantId.':'.$saleId),0,10));$password=substr(strtoupper(bin2hex(random_bytes(6))),0,12);
   if($previous)$auth=flexihubRenewHotspotAuthorization($tenantId,(int)$sale['package_id'],$username,$password,$mac,(int)($previous['previous_session_id']??0),$previous['previous_expires_at']??$previous['expires_at']);
   else$auth=flexihubAuthorizeHotspot($tenantId,(int)$sale['package_id'],$username,$password,$mac);
   $enc=flexihubGatewayEncrypt($password);$prevId=$previous?(int)$previous['id']:0;
   $u=$conn->prepare("UPDATE hotspot_sales SET status='access_active',hotspot_username=?,hotspot_password_encrypted=?,started_at=NOW(),expires_at=?,renewed_from_sale_id=? WHERE id=? AND tenant_id=?");if(!$u)throw new Exception('Unable to update hotspot sale.');$u->bind_param('sssiii',$username,$enc,$auth['expires_at'],$prevId,$saleId,$tenantId);$u->execute();$u->close();\n   $u=$conn->prepare("UPDATE hotspot_access_codes SET status='activated',activated_at=NOW(),expires_at=? WHERE id=(SELECT access_code_id FROM hotspot_sales WHERE id=? AND tenant_id=?) AND tenant_id=?");if($u){$u->bind_param('siii',$auth['expires_at'],$saleId,$tenantId,$tenantId);$u->execute();$u->close();}
   if($previous){$oldId=(int)$previous['id'];$u=$conn->prepare("UPDATE hotspot_sales SET status='renewed' WHERE id=? AND tenant_id=? AND status='access_active'");if($u){$u->bind_param('ii',$oldId,$tenantId);$u->execute();$u->close();}}
   $u=$conn->prepare("UPDATE hotspot_finalization_queue SET status='completed',processed_at=NOW(),last_error=NULL WHERE id=? AND tenant_id=? AND status='processing'");if($u){$u->bind_param('ii',$id,$tenantId);$u->execute();$u->close();}$completed++;
  } catch(Throwable $e) {
   $failed++;$err=substr($e->getMessage(),0,1000);$attempt=(int)$job['attempts']+1;$status=$attempt>=8?'failed':'pending';$delay=min(3600,max(60,$attempt*120));
   $u=$conn->prepare("UPDATE hotspot_finalization_queue SET status=?,available_at=DATE_ADD(NOW(),INTERVAL ? SECOND),last_error=? WHERE id=? AND tenant_id=? AND status='processing'");if($u){$u->bind_param('sisii',$status,$delay,$err,$id,$tenantId);$u->execute();$u->close();}
  }
 }
 return ['processed'=>$processed,'completed'=>$completed,'failed'=>$failed];
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
   $router=flexihubHotspotRouter($tenantId,$routerId);if(!$router||$router['host']===''||$router['username']==='')throw new Exception('Hotspot router connection details are incomplete.');
   $ros=new FlexihubRouterOS($router['host'],$router['username'],$router['password'],$router['port'],8);
   try{if($username!==''){$ros->disconnectHotspotActive($username);$ros->setHotspotUserDisabled($username,true);}}finally{$ros->close();}
   $u=$conn->prepare("UPDATE hotspot_sessions SET status='expired',ended_at=NOW(),disconnect_reason='package_expired' WHERE id=? AND tenant_id=? AND status IN ('authorized','active')");if($u){$u->bind_param('ii',$sessionId,$tenantId);$u->execute();$u->close();}
   $u=$conn->prepare("UPDATE hotspot_sales SET status='expired' WHERE tenant_id=? AND hotspot_username=? AND status='access_active' AND expires_at<=NOW()");if($u){$u->bind_param('is',$tenantId,$username);$u->execute();$u->close();}
   flexihubWorkflowInsert('router_sync_logs',['tenant_id'=>$tenantId,'router_id'=>$routerId,'service_type'=>'hotspot','external_username'=>$username,'action'=>'expire','status'=>'success','message'=>'Hotspot access expired and router session disconnected.']);
   $expired++;
  }catch(Throwable $e){$failed++;flexihubWorkflowInsert('router_sync_logs',['tenant_id'=>$tenantId,'router_id'=>$routerId,'service_type'=>'hotspot','external_username'=>$username,'action'=>'expire','status'=>'failed','message'=>substr($e->getMessage(),0,500)]);}
 }
 return ['processed'=>$processed,'expired'=>$expired,'failed'=>$failed];
}}

?>