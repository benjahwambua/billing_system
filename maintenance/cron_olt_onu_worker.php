<?php
/**
 * Flexihub OLT/ONU live reachability worker.
 * CLI only. This deliberately performs transport-level health checks;
 * vendor-specific SNMP/API optical discovery belongs in adapters.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }
require_once __DIR__ . '/../config/database.php';

$lockPath=__DIR__.'/olt_onu_worker.lock';
$lock=fopen($lockPath,'c');
if(!$lock || !flock($lock,LOCK_EX|LOCK_NB)) exit("Another OLT/ONU worker is already running.\n");

function flexihubProbeHost($host,$port,$timeout=3){
    $host=trim((string)$host); $port=(int)$port;
    if($host==='' || $port<1 || $port>65535) return ['ok'=>false,'error'=>'Invalid host or port.'];
    $errno=0;$errstr='';$start=microtime(true);
    $fp=@fsockopen($host,$port,$errno,$errstr,$timeout);
    $latency=round((microtime(true)-$start)*1000,1);
    if($fp){fclose($fp);return ['ok'=>true,'latency_ms'=>$latency,'error'=>null];}
    return ['ok'=>false,'latency_ms'=>$latency,'error'=>substr($errstr?:('Connection error '.$errno),0,500)];
}

$stats=['olts'=>0,'olt_online'=>0,'olt_offline'=>0,'onus'=>0,'onu_online'=>0,'onu_offline'=>0];
try {
    $q=$conn->query("SELECT id,tenant_id,host,api_port FROM olt_devices WHERE polling_enabled=1 AND status NOT IN ('disabled','deleted')");
    if($q){
        while($o=$q->fetch_assoc()){
            $stats['olts']++;
            $probe=flexihubProbeHost($o['host']??'',(int)($o['api_port']??161));
            $status=$probe['ok']?'online':'offline'; $now=date('Y-m-d H:i:s'); $err=$probe['error'];
            $u=$conn->prepare("UPDATE olt_devices SET status=?,last_seen_at=IF(?='online',?,last_seen_at),last_poll_at=?,last_poll_error=? WHERE id=? AND tenant_id=?");
            if($u){$u->bind_param('ssssiii',$status,$status,$now,$now,$err,$o['id'],$o['tenant_id']);$u->execute();$u->close();}
            $stats[$probe['ok']?'olt_online':'olt_offline']++;
        }
        $q->free();
    }

    $q=$conn->query("SELECT id,tenant_id,olt_id,serial_number FROM onu_devices WHERE olt_id IS NOT NULL AND status NOT IN ('disabled','deleted')");
    if($q){
        while($n=$q->fetch_assoc()){
            $stats['onus']++;
            // ONU reachability is inherited from its OLT until a vendor adapter can
            // query individual optical/registration state.
            $oq=$conn->prepare("SELECT status,last_seen_at FROM olt_devices WHERE id=? AND tenant_id=? LIMIT 1");
            $oq->bind_param('ii',$n['olt_id'],$n['tenant_id']);$oq->execute();$olt=$oq->get_result()->fetch_assoc();$oq->close();
            $online=($olt && $olt['status']==='online');$status=$online?'online':'offline';$now=date('Y-m-d H:i:s');
            $u=$conn->prepare("UPDATE onu_devices SET status=?,last_seen_at=IF(?='online',?,last_seen_at),last_poll_at=?,last_poll_error=? WHERE id=? AND tenant_id=?");
            $err=$online?null:'Parent OLT is unreachable; individual ONU polling requires a vendor adapter.';
            if($u){$u->bind_param('ssssiii',$status,$status,$now,$now,$err,$n['id'],$n['tenant_id']);$u->execute();$u->close();}
            $stats[$online?'onu_online':'onu_offline']++;
        }
        $q->free();
    }
    echo json_encode($stats,JSON_UNESCAPED_SLASHES)."\n";
    exit(0);
} catch(Throwable $e) {
    fwrite(STDERR,"Flexihub OLT/ONU worker failed: ".$e->getMessage()."\n"); exit(1);
} finally {
    flock($lock,LOCK_UN); fclose($lock);
}
