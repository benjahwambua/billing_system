<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
if(isTenantUser()) requireTenant();
global $conn;
$cols=[];$q=$conn->query("SHOW COLUMNS FROM ip_pools");if(!$q)die('Unable to read IP pool schema.');while($x=$q->fetch_assoc())$cols[]=$x['Field'];
$has=fn($c)=>in_array($c,$cols,true);$tid=(int)getCurrentTenantId();
if($has('tenant_id')&&$tid<=0)die('A valid tenant context is required to view IP pools.');
$where=[];$params=[];$types='';
if($has('tenant_id')){$where[]='tenant_id=?';$params[]=$tid;$types.='i';}
$sql='SELECT * FROM ip_pools'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY id DESC LIMIT 200';
$st=$conn->prepare($sql);if(!$st)die('Unable to load IP pools.');if($params)$st->bind_param($types,...$params);$st->execute();$rows=$st->get_result();
function ipval($r,$a){foreach($a as $c)if(isset($r[$c])&&$r[$c]!=='')return $r[$c];return '';}
?>
<?php require '../includes/header.php';?>
<div class="page-content"><div class="page-header"><div><h1>IP Pools</h1><p>Tenant-scoped address pools with subnet and allocation-range validation.</p></div><a class="btn btn-primary" href="add.php">+ Add IP Pool</a></div>
<div class="card"><div style="overflow:auto"><table class="table"><thead><tr><th>Name</th><th>Network / CIDR</th><th>Gateway</th><th>Allocation Range</th><th>Status</th><th>Action</th></tr></thead><tbody>
<?php if(!$rows->num_rows):?><tr><td colspan="6" style="text-align:center;padding:30px">No IP pools found.</td></tr><?php else:while($r=$rows->fetch_assoc()):?><tr><td><?=e(ipval($r,['name','pool_name'])?:$r['id'])?></td><td><?=e(ipval($r,['network','cidr']))?></td><td><?=e(ipval($r,['gateway']))?></td><td><?=e(ipval($r,['start_ip'])).' - '.e(ipval($r,['end_ip']))?></td><td><?=e(ucfirst(ipval($r,['status'])?:'unknown'))?></td><td><a class="btn btn-secondary" href="edit.php?id=<?=(int)$r['id']?>">Edit</a></td></tr><?php endwhile;endif;?>
</tbody></table></div></div></div>
<?php require '../includes/footer.php';?>
