<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
if (isTenantUser()) requireTenant();
global $conn;
$cols=[];$res=$conn->query("SHOW COLUMNS FROM mikrotik_routers");if(!$res)die('Unable to read MikroTik router configuration.');
while($row=$res->fetch_assoc())$cols[]=$row['Field'];$has=fn($c)=>in_array($c,$cols,true);$tenantId=getCurrentTenantId();
$where=[];$params=[];$types='';
if($has('tenant_id')&&$tenantId){$where[]='r.tenant_id = ?';$params[]=$tenantId;$types.='i';}
$search=trim($_GET['search']??'');if($search!==''){ $parts=[];foreach(['name','router_name','host','ip_address','ip','username','description'] as $c)if($has($c))$parts[]="r.$c LIKE ?";if($parts){$where[]='('.implode(' OR ',$parts).')';foreach($parts as $p){$params[]='%'.$search.'%';$types.='s';}}}
$sql="SELECT r.* FROM mikrotik_routers r".($where?' WHERE '.implode(' AND ',$where):'')." ORDER BY r.id DESC LIMIT 200";
$stmt=$conn->prepare($sql);if(!$stmt)die('Unable to load routers.');if($params)$stmt->bind_param($types,...$params);$stmt->execute();$result=$stmt->get_result();
function routerValue($r,$cs,$d=''){foreach($cs as $c)if(array_key_exists($c,$r))return $r[$c];return $d;}
?>
<?php require '../includes/header.php';?><div class="page-content">
<div class="page-header"><div><h1>MikroTik Routers</h1><p>Manage routers connected to this tenant.</p></div><a class="btn btn-primary" href="add.php">+ Add Router</a></div>
<form method="get" class="card" style="margin-bottom:18px;padding:14px;display:flex;gap:10px"><input name="search" value="<?=e($search)?>" placeholder="Search router name, IP or host" style="flex:1"><button class="btn btn-secondary">Search</button><?php if($search!==''):?><a class="btn btn-secondary" href="index.php">Clear</a><?php endif;?></form>
<div class="card"><div style="overflow:auto"><table class="table"><thead><tr><th>Name</th><th>Host / IP</th><th>API Port</th><th>Username</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php if($result->num_rows===0):?><tr><td colspan="6" style="text-align:center;padding:30px">No routers found.</td></tr><?php else:while($r=$result->fetch_assoc()):?><tr><td><?=e(routerValue($r,['name','router_name'],(string)$r['id']))?></td><td><?=e(routerValue($r,['host','ip_address','ip']))?></td><td><?=e(routerValue($r,['api_port','port'],'8728'))?></td><td><?=e(routerValue($r,['username']))?></td><td><?=e(ucfirst((string)routerValue($r,['status'],'active')))?></td><td><a class="btn btn-secondary" href="edit.php?id=<?=e($r['id'])?>">Edit</a></td></tr><?php endwhile;endif;?></tbody></table></div></div></div>
<?php require '../includes/footer.php';?>