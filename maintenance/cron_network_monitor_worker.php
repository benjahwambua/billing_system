<?php
/** Flexihub MikroTik network monitoring worker. CLI only. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\\n"); }
require_once __DIR__ . '/../config/database.php';
$lockPath=__DIR__.'/network_monitor_worker.lock';
$lock=fopen($lockPath,'c');
if(!$lock || !flock($lock,LOCK_EX|LOCK_NB)) exit("Another network monitor worker is already running.\\n");
function flexihubNetworkProbe($host,$port=8728,$timeout=4){
    $host=trim((string)$host); $port=(int)$port; $start=microtime(true);
    if($host==='' || $port<1 || $port>65535) return ['ok'=>false,'latency_ms'=>null,'error'=>'Invalid host or port.'];
    $errno=0;$errstr=''; $fp=@fsockopen($host,$port,$errno,$errstr,$timeout);
    $lat=round((microtime(true)-$start)*1000,1);
    if($fp){fclose($fp);return ['ok'=>true,'latency_ms'=>$lat,'error'=>null];}
    return ['ok'=>false,'latency_ms'=>$lat,'error'=>substr($errstr?:('Connection error '.$errno),0,500)];
}
try{
 $q=$conn->query("SELECT id,tenant_id,host,ip_address,ip,api_port,port,status FROM mikrotik_routers ORDER BY id ASC");
 $stats=['total'=>0,'online'=>0,'offline'=>0];
 if($q) while($r=$q->fetch_assoc()){
  $stats['total']++; $host=$r['host']??($r['ip_address']??($r['ip']??'')); $port=(int)($r['api_port']??($r['port']??8728));
  $p=flexihubNetworkProbe($host,$port); $now=date('Y-m-d H:i:s'); $status=$p['ok']?'online':'offline';
  $u=$conn->prepare("UPDATE mikrotik_routers SET status=?,last_poll_at=?,last_seen_at=IF(?='online',?,last_seen_at),last_poll_error=?,latency_ms=? WHERE id=? AND tenant_id=?");
  if($u){$u->bind_param('sssssdii',$status,$now,$status,$now,$p['error'],$p['latency_ms'],$r['id'],$r['tenant_id']);$u->execute();$u->close();}
  $stats[$p['ok']?'online':'offline']++;
 }
 echo json_encode($stats,JSON_UNESCAPED_SLASHES)."\\n";
 exit(0);
}catch(Throwable $e){fwrite(STDERR,"Flexihub network monitor failed: ".$e->getMessage()."\\n");exit(1);}
finally{flock($lock,LOCK_UN);fclose($lock);}
