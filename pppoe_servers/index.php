<?php
require_once '../includes/auth.php';require_once '../includes/functions.php';
if(isTenantUser())requireTenant();
requireModulePermission('pppoe','view');
global $conn;
$cols=[];$q=$conn->query("SHOW COLUMNS FROM pppoe_servers");if(!$q)die('Unable to read PPPoE server schema.');while($x=$q->fetch_assoc())$cols[]=$x['Field'];
$has=fn($c)=>in_array($c,$cols,true);$tid=(int)getCurrentTenantId();
$where=[];$params=[];$types='';
if($has('tenant_id')){if($tid<=0)die('A valid tenant context is required.');$where[]='s.tenant_id=?';$params[]=$tid;$types.='i';}
$sql="SELECT s.* FROM pppoe_servers s".($where?' WHERE '.implode(' AND ',$where):'')." ORDER BY s.id DESC LIMIT 200";
$st=$conn->prepare($sql);if(!$st)die('Unable to load PPPoE servers.');if($params)$st->bind_param($types,...$params);$st->execute();$res=$st->get_result();$rows=[];while($r=$res->fetch_assoc())$rows[]=$r;$st->close();
$poolNames=[];
if($has('ip_pool_id')){
 $poolCols=[];$pc=$conn->query("SHOW COLUMNS FROM ip_pools");if($pc)while($x=$pc->fetch_assoc())$poolCols[]=$x['Field'];
 $poolTenant=in_array('tenant_id',$poolCols,true);$labelCol=in_array('name',$poolCols,true)?'name':(in_array('pool_name',$poolCols,true)?'pool_name':null);
 $ids=array_values(array_unique(array_filter(array_map(fn($r)=>(int)($r['ip_pool_id']??0),$rows))));
 if($ids&&$labelCol&&(!$poolTenant||$tid>0)){
  $sql='SELECT id,'.$labelCol.' AS pool_label FROM ip_pools WHERE id IN ('.implode(',',array_map('intval',$ids)).')'.($poolTenant?' AND tenant_id='.(int)$tid:'');
  $pr=$conn->query($sql);if($pr)while($x=$pr->fetch_assoc())$poolNames[(int)$x['id']]=$x['pool_label'];
 }
}
?>
<?php require '../includes/header.php';?>
<div class="page-content"><div class="page-header"><div><h1>PPPoE Servers</h1><p>Manage PPPoE server records and their tenant IP pool associations.</p></div><a class="btn btn-primary" href="add.php">+ Add Server</a></div>
<div class="card"><div style="overflow:auto"><table class="table"><thead><tr><th>Name</th><th>Router</th><th>Interface</th><th>IP Pool</th><th>Status</th><th>Action</th></tr></thead><tbody>
<?php if(!$rows):?><tr><td colspan="6" style="text-align:center;padding:30px">No PPPoE servers found.</td></tr><?php else:foreach($rows as $r):?><tr><td><?=e($r['name']??$r['server_name']??('#'.$r['id']))?></td><td><?=e($r['router_id']??'—')?></td><td><?=e($r['interface']??'—')?></td><td><?php $pid=(int)($r['ip_pool_id']??0);?><?= $pid ? e($poolNames[$pid]??('Pool #'.$pid.' (unresolved)')) : '—' ?></td><td><?=e(ucfirst($r['status']??'active'))?></td><td><a class="btn btn-secondary" href="edit.php?id=<?=$r['id']?>">Edit</a></td></tr><?php endforeach;endif;?>
</tbody></table></div></div></div>
<?php require '../includes/footer.php';?>
