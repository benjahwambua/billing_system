<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireActiveUser();requireTenantContext();requireModulePermission('pppoe','view');
global $conn;$tenantId=(int)getCurrentTenantId();if($tenantId<=0){http_response_code(403);exit('A valid tenant context is required.');}
$ready=flexihubTableHasColumn('ip_pool_allocations','tenant_id')&&flexihubTableHasColumn('ip_pool_allocations','pool_id')&&flexihubTableHasColumn('ip_pool_allocations','ip_address')&&flexihubTableHasColumn('ip_pool_allocations','status');
$errors=[];$notice='';
if($ready&&$_SERVER['REQUEST_METHOD']==='POST'){
 requireCsrf();$action=(string)($_POST['action']??'');$poolId=(int)($_POST['pool_id']??0);$ip=trim((string)($_POST['ip_address']??''));$accountId=(int)($_POST['internet_account_id']??0);
 if($action==='allocate'){
  if(filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4)===false)$errors[]='Enter a valid IPv4 address.';
  if(!$errors&&!$conn->begin_transaction())$errors[]='Unable to start the allocation transaction.';
  if(!$errors)try{
   $pool=$conn->prepare("SELECT id,network,cidr,gateway,start_ip,end_ip,status FROM ip_pools WHERE id=? AND tenant_id=? LIMIT 1 FOR UPDATE");
   if(!$pool)throw new RuntimeException('Unable to verify IP pool.');
   $pool->bind_param('ii',$poolId,$tenantId);$pool->execute();$p=$pool->get_result()->fetch_assoc();$pool->close();
   if(!$p||strtolower((string)$p['status'])!=='active')throw new RuntimeException('Select an active IP pool belonging to this tenant.');
   if(!$errors){
    $ipN=(float)sprintf('%u',ip2long($ip));$startN=($p['start_ip']??'')!==''?(float)sprintf('%u',ip2long($p['start_ip'])):null;$endN=($p['end_ip']??'')!==''?(float)sprintf('%u',ip2long($p['end_ip'])):null;
    if($startN!==null&&($ipN<$startN||$ipN>$endN))throw new RuntimeException('The address is outside this pool allocation range.');
    if($ip===(string)($p['gateway']??''))throw new RuntimeException('The gateway address cannot be allocated.');
    $cidr=(string)($p['cidr']??$p['network']??'');if(strpos($cidr,'/')!==false){[$net,$bits]=explode('/',$cidr,2);$bits=(int)$bits;$mask=$bits===0?0:((0xFFFFFFFF << (32-$bits))&0xFFFFFFFF);$ipInt=(int)sprintf('%u',ip2long($ip));$netInt=(int)sprintf('%u',ip2long($net));if(($ipInt&$mask)!==($netInt&$mask))throw new RuntimeException('The address is outside the configured subnet.');if($bits<=30&&($ipInt===$netInt||$ipInt===($netInt|((~$mask)&0xFFFFFFFF))))throw new RuntimeException('Network and broadcast addresses cannot be allocated.');}
    if($accountId>0){$a=$conn->prepare("SELECT id FROM internet_accounts WHERE id=? AND tenant_id=? LIMIT 1");if(!$a)throw new RuntimeException('Unable to verify subscriber account.');$a->bind_param('ii',$accountId,$tenantId);$a->execute();$ok=(bool)$a->get_result()->fetch_assoc();$a->close();if(!$ok)throw new RuntimeException('The selected subscriber account does not belong to this tenant.');}
    $existing=$conn->prepare("SELECT id,status FROM ip_pool_allocations WHERE tenant_id=? AND ip_address=? LIMIT 1 FOR UPDATE");if(!$existing)throw new RuntimeException('Unable to check existing address allocation.');$existing->bind_param('is',$tenantId,$ip);$existing->execute();$old=$existing->get_result()->fetch_assoc();$existing->close();
    if($old&&strtolower((string)$old['status'])==='allocated')throw new RuntimeException('This IP address is already allocated.');
    if($old){$up=$conn->prepare("UPDATE ip_pool_allocations SET pool_id=?,internet_account_id=NULLIF(?,0),status='allocated',allocated_at=NOW(),released_at=NULL,notes=NULL WHERE id=? AND tenant_id=?");$up->bind_param('iiii',$poolId,$accountId,$old['id'],$tenantId);if(!$up->execute())throw new RuntimeException('Unable to reallocate address.');$up->close();}
    else{$ins=$conn->prepare("INSERT INTO ip_pool_allocations(tenant_id,pool_id,ip_address,internet_account_id,status) VALUES(?,?,?,NULLIF(?,0),'allocated')");if(!$ins)throw new RuntimeException('Unable to prepare address allocation.');$ins->bind_param('iisi',$tenantId,$poolId,$ip,$accountId);if(!$ins->execute())throw new RuntimeException('Unable to allocate address: '.$ins->error);$ins->close();}
   }
   $conn->commit();$notice='IP address allocated successfully.';
  }catch(Throwable $e){$conn->rollback();$errors[]=$e->getMessage();}
 }elseif($action==='release'){
  $allocationId=(int)($_POST['allocation_id']??0);$q=$conn->prepare("UPDATE ip_pool_allocations SET status='released',released_at=NOW() WHERE id=? AND tenant_id=? AND status='allocated'");if($q){$q->bind_param('ii',$allocationId,$tenantId);if($q->execute()&&$q->affected_rows===1)$notice='IP address released.';else$errors[]='Active allocation not found.';$q->close();}else$errors[]='Unable to release IP address.';
 }else $errors[]='Unsupported allocation action.';
}
$pools=[];$q=$conn->prepare("SELECT id,name,network,cidr,gateway,start_ip,end_ip FROM ip_pools WHERE tenant_id=? AND LOWER(status)='active' ORDER BY name");if($q){$q->bind_param('i',$tenantId);$q->execute();$z=$q->get_result();while($x=$z->fetch_assoc())$pools[]=$x;$q->close();}
$accountCols=[];$accountSchema=$conn->query("SHOW COLUMNS FROM internet_accounts");if($accountSchema){while($col=$accountSchema->fetch_assoc())$accountCols[]=$col['Field'];}
$accountLabelColumn=in_array('account_number',$accountCols,true)?'account_number':(in_array('username',$accountCols,true)?'username':null);
$accountSelect='id'.($accountLabelColumn?', '.$accountLabelColumn:'');$accounts=[];$q=$conn->prepare("SELECT ".$accountSelect." FROM internet_accounts WHERE tenant_id=? ORDER BY id DESC LIMIT 500");if($q){$q->bind_param('i',$tenantId);$q->execute();$z=$q->get_result();while($x=$z->fetch_assoc())$accounts[]=$x;$q->close();}
$poolCols=[];$poolSchema=$conn->query("SHOW COLUMNS FROM ip_pools");if($poolSchema){while($col=$poolSchema->fetch_assoc())$poolCols[]=$col['Field'];}$poolLabel=in_array('name',$poolCols,true)?'name':'pool_name';
$rows=[];if($ready){$q=$conn->prepare("SELECT a.*,p.".$poolLabel." pool_name FROM ip_pool_allocations a INNER JOIN ip_pools p ON p.id=a.pool_id AND p.tenant_id=a.tenant_id WHERE a.tenant_id=? ORDER BY a.id DESC LIMIT 500");if($q){$q->bind_param('i',$tenantId);$q->execute();$z=$q->get_result();while($x=$z->fetch_assoc())$rows[]=$x;$q->close();}}
$pageTitle='IP Address Allocation';require_once '../includes/header.php';require_once '../includes/sidebar.php';
?>
<div class="page-content"><div class="page-header"><div><h1>IP Address Allocation</h1><p>Track subscriber address assignments and releases within tenant-owned pools.</p></div><a class="btn btn-secondary" href="index.php">Back to IP Pools</a></div>
<?php if(!$ready):?><div class="alert alert-warning">Address allocation ledger is not installed. Apply <code>database/044_ip_pool_allocations.sql</code> during the database update phase.</div><?php endif;?>
<?php foreach($errors as $e):?><div class="alert alert-danger"><?=e($e)?></div><?php endforeach;?><?php if($notice):?><div class="alert alert-success"><?=e($notice)?></div><?php endif;?>
<?php if($ready&&userCan('pppoe','edit')):?><section class="dashboard-card" style="padding:20px;margin-bottom:18px"><h2>Allocate Address</h2><form method="post"><?=csrfField()?><input type="hidden" name="action" value="allocate"><div class="form-grid"><label>IP Pool<select name="pool_id" required><option value="">Select pool</option><?php foreach($pools as $p):?><option value="<?=(int)$p['id']?>"><?=e(($p['name']??'Pool '.$p['id']).' · '.($p['start_ip']??''). ' – '.($p['end_ip']??''))?></option><?php endforeach;?></select></label><label>IPv4 Address<input name="ip_address" required placeholder="10.10.0.25"></label><label>Subscriber Account (optional)<select name="internet_account_id"><option value="0">Unassigned</option><?php foreach($accounts as $a):?><option value="<?=(int)$a['id']?>"><?=e(($accountLabelColumn&&isset($a[$accountLabelColumn])?$a[$accountLabelColumn]:'Account #'.$a['id']))?></option><?php endforeach;?></select></label></div><button class="btn btn-primary">Allocate IP</button></form></section><?php endif;?>
<section class="dashboard-card" style="padding:20px"><h2>Allocation Ledger</h2><div class="table-responsive"><table><thead><tr><th>IP Address</th><th>Pool</th><th>Account</th><th>Status</th><th>Allocated</th><th>Released</th><th>Action</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><strong><?=e($r['ip_address'])?></strong></td><td><?=e($r['pool_name'])?></td><td><?=e($r['internet_account_id']??'—')?></td><td><?=e(ucfirst($r['status']))?></td><td><?=e($r['allocated_at'])?></td><td><?=e($r['released_at']??'—')?></td><td><?php if($r['status']==='allocated'&&userCan('pppoe','edit')):?><form method="post" style="display:inline"><?=csrfField()?><input type="hidden" name="action" value="release"><input type="hidden" name="allocation_id" value="<?=(int)$r['id']?>"><button class="btn btn-secondary btn-sm">Release</button></form><?php else:?>—<?php endif;?></td></tr><?php endforeach;?><?php if(!$rows):?><tr><td colspan="7">No IP allocations recorded yet.</td></tr><?php endif;?></tbody></table></div></section></div>
<?php require_once '../includes/footer.php';?>
