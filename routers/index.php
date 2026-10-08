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
<?php if($result->num_rows===0):?><tr><td colspan="6" style="text-align:center;padding:30px">No routers found.</td></tr><?php else:while($r=$result->fetch_assoc()):?><tr><td><?=e(routerValue($r,['name','router_name'],(string)$r['id']))?></td><td><?=e(routerValue($r,['host','ip_address','ip']))?></td><td><?=e(routerValue($r,['api_port','port'],'8728'))?></td><td><?=e(routerValue($r,['username']))?></td><td><?=e(ucfirst((string)routerValue($r,['status'],'active')))?></td><td><a class="btn btn-secondary" href="test.php?id=<?=e($r['id'])?>">Test</a> <a class="btn btn-secondary" href="edit.php?id=<?=e($r['id'])?>">Edit</a></td></tr><?php endwhile;endif;?></tbody></table></div></div></div>
<?php require '../includes/footer.php';?>
<style>
/* Flexihub sleek black / blue module polish */
.page-content,.dashboard-card{color:#f8fafc}.page-header h1,.page-header h2{color:#f8fafc;letter-spacing:-.02em}.page-header p{color:#94a3b8!important}.card,.dashboard-card{background:linear-gradient(145deg,rgba(16,24,39,.96),rgba(11,17,27,.94))!important;border:1px solid rgba(148,163,184,.14)!important;box-shadow:0 16px 40px rgba(0,0,0,.22);border-radius:14px}.page-content input,.dashboard-card input{background:#0b111b!important;color:#f8fafc!important;border-color:rgba(148,163,184,.18)!important;border-radius:8px}.page-content input:focus,.dashboard-card input:focus{border-color:#3b82f6!important;box-shadow:0 0 0 3px rgba(37,99,235,.14);outline:none}.table,.dashboard-card table{width:100%;border-collapse:collapse}.table th,.dashboard-card th{background:#0b111b!important;color:#cbd5e1;border-color:rgba(148,163,184,.14)!important}.table td,.dashboard-card td{color:#e2e8f0;border-color:rgba(148,163,184,.14)!important}.table tbody tr:hover,.dashboard-card tbody tr:hover{background:rgba(37,99,235,.07)}.btn-primary{background:linear-gradient(135deg,#2563eb,#3b82f6)!important;color:#fff!important;border-color:transparent!important;box-shadow:0 8px 22px rgba(37,99,235,.18)}.btn-secondary{background:#172235!important;color:#cbd5e1!important;border:1px solid rgba(148,163,184,.14)!important}.btn-secondary:hover{background:#1d2b42!important;color:#fff!important}
</style>