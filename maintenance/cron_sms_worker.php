<?php
/**
 * Flexihub SMS retry worker.
 * Run from the server scheduler: php maintenance/cron_sms_worker.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/sms_gateway.php';

$lockPath=__DIR__.'/sms_worker.lock';
$lock=fopen($lockPath,'c');
if(!$lock || !flock($lock,LOCK_EX|LOCK_NB)) exit("Another SMS worker is already running.\n");

$maxAttempts=3; $limit=100; $processed=0; $retried=0; $sent=0; $failed=0;
try {
    $q=$conn->query("SELECT id,tenant_id,gateway_id,recipient,message,attempts
                     FROM sms_messages
                     WHERE status='failed' AND attempts < {$maxAttempts}
                       AND (next_attempt_at IS NULL OR next_attempt_at <= NOW())
                     ORDER BY id ASC LIMIT {$limit}");
    if(!$q) throw new Exception('Unable to load SMS retry queue.');

    while($row=$q->fetch_assoc()){
        $id=(int)$row['id']; $tenant=(int)$row['tenant_id']; $attempt=(int)$row['attempts']+1;
        $claim=$conn->prepare("UPDATE sms_messages SET status='retrying',last_attempt_at=NOW(),next_attempt_at=NULL WHERE id=? AND tenant_id=? AND status='failed' AND attempts < ?");
        if(!$claim) continue;
        $claim->bind_param('iii',$id,$tenant,$maxAttempts);
        $claim->execute(); $claimed=$claim->affected_rows===1; $claim->close();
        if(!$claimed) continue;

        $processed++; $retried++;
        $result=flexihubSmsSendMessage($tenant,(int)$row['gateway_id'],(string)$row['recipient'],(string)$row['message'],$id,$attempt);
        if(!empty($result['success'])) {
            $sent++;
        } else {
            $failed++;
            if($attempt < $maxAttempts) {
                $delay=(int)pow(2,$attempt)*60;
                $u=$conn->prepare("UPDATE sms_messages SET status='failed',next_attempt_at=DATE_ADD(NOW(),INTERVAL ? SECOND) WHERE id=? AND tenant_id=?");
                if($u){$u->bind_param('iii',$delay,$id,$tenant);$u->execute();$u->close();}
            }
        }
    }
    $q->free();
    echo json_encode(['processed'=>$processed,'retried'=>$retried,'sent'=>$sent,'failed'=>$failed,'max_attempts'=>$maxAttempts],JSON_UNESCAPED_SLASHES)."\n";
    exit($failed>0?2:0);
} catch(Throwable $e) {
    fwrite(STDERR,"Flexihub SMS worker failed: ".$e->getMessage()."\n"); exit(1);
} finally {
    flock($lock,LOCK_UN); fclose($lock);
}
