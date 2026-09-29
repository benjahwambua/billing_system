<?php
require_once __DIR__ . '/../includes/auth.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('internet_plans','view');
$tenantId=(int)getCurrentTenantId();
$pageTitle='Internet Plans';

$search=trim($_GET['q']??'');
$status=trim($_GET['status']??'');
$where=['tenant_id=?'];$params=[$tenantId];$types='i';
if($search!==''){ $where[]='name LIKE ?';$params[]='%'.$search.'%';$types.='s'; }
if(in_array($status,['active','inactive'],true)){ $where[]='status=?';$params[]=$status;$types.='s'; }
$sql='SELECT * FROM internet_plans WHERE '.implode(' AND ',$where).' ORDER BY id DESC LIMIT 200';
$stmt=$conn->prepare($sql);$plans=[];
if($stmt){$bind=[$types];foreach($params as $k=>$v)$bind[]=&$params[$k];call_user_func_array([$stmt,'bind_param'],$bind);if($stmt->execute()){$r=$stmt->get_result();while($x=$r->fetch_assoc())$plans[]=$x;}$stmt->close();}
require_once __DIR__.'/../includes/header.php';require_once __DIR__.'/../includes/sidebar.php';?>
<div class="main-content">
<div class="page-header"><div><h1>Internet Plans</h1><p>Manage the service packages offered to customers.</p></div><?php if(userCan('internet_plans','create')):?><a href="add.php" class="btn btn-primary">+ Add Internet Plan</a><?php endif;?></div>
<div class="dashboard-card" style="margin-bottom:20px"><form method="get" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap"><div><label>Search</label><input name="q" value="<?=e($search)?>" placeholder="Plan name"></div><div><label>Status</label><select name="status"><option value="">All statuses</option><option value="active" <?=$status==='active'?'selected':''?>>Active</option><option value="inactive" <?=$status==='inactive'?'selected':''?>>Inactive</option></select></div><button class="btn btn-secondary">Filter</button><a href="index.php" class="btn btn-light">Reset</a></form></div>
<div class="dashboard-card"><div class="table-responsive"><table class="data-table" style="width:100%"><thead><tr><th>Plan</th><th>Price</th><th>Cycle</th><th>Days</th><th>Download</th><th>Upload</th><th>Status</th><th>Action</th></tr></thead><tbody>
<?php if(!$plans):?><tr><td colspan="8">No internet plans found.</td></tr><?php else:foreach($plans as $p):?><tr><td><strong><?=e($p['name'])?></strong><small><?=e($p['description']??'')?></small></td><td><?=e(formatMoney($p['price']??0))?></td><td><?=e(ucfirst($p['billing_cycle']??''))?></td><td><?=e($p['billing_days']??'—')?></td><td><?=e($p['download_speed']??'—')?></td><td><?=e($p['upload_speed']??'—')?></td><td><?=e(ucfirst($p['status']??''))?></td><td><?php if(userCan('internet_plans','edit')):?><a href="edit.php?id=<?=(int)$p['id']?>" class="btn btn-sm">Edit</a><?php endif;?></td></tr><?php endforeach;endif;?>
</tbody></table></div></div></div>
<?php require_once __DIR__.'/../includes/footer.php';?>