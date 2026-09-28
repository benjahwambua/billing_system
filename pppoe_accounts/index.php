<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
if (isTenantUser()) requireTenant();
global $conn;
$cols=[];$q=$conn->query("SHOW COLUMNS FROM pppoe_accounts");while($x=$q->fetch_assoc())$cols[]=$x['Field'];
$has=fn($c)=>in_array($c,$cols,true);$tid=getCurrentTenantId();$where=[];$params=[];$types='';
if($has('tenant_id')&&$tid){$where[]='pa.tenant_id=?';$params[]=$tid;$types.='i';}
$search=trim($_GET['search']??'');
if($search!==''&&$has('username')){$where[]='pa.username LIKE ?';$params[]='%'.$search.'%';$types.='s';}
$sql="SELECT pa.*,ia.account_number,c.first_name,c.last_name FROM pppoe_accounts pa LEFT JOIN internet_accounts ia ON ia.id=pa.internet_account_id LEFT JOIN customers c ON c.id=ia.customer_id".($where?' WHERE '.implode(' AND ',$where):'')." ORDER BY pa.id DESC LIMIT 200";
$s=$conn->prepare($sql);if($params)$s->bind_param($types,...$params);$s->execute();$rows=$s->get_result();
?>
<?php require '../includes/header.php';?><div class="page-content"><div class="page-header"><div><h1>PPPoE Accounts</h1><p>Manage PPPoE credentials assigned to internet accounts.</p></div><a class="btn btn-primary" href="add.php">+ Add PPPoE Account</a></div>
<form method="get" class="card" style="padding:14px;margin-bottom:18px;display:flex;gap:10px"><input name="search" value="<?=e($search)?>" placeholder="Search username" style="flex:1"><button class="btn btn-secondary">Search</button></form>
<div class="card"><div style="overflow:auto"><table class="table"><thead><tr><th>Username</th><th>Customer</th><th>Internet Account</th><th>Status</th><th>Action</th></tr></thead><tbody>
<?php if(!$rows->num_rows):?><tr><td colspan="5" style="text-align:center;padding:30px">No PPPoE accounts found.</td></tr><?php else:while($r=$rows->fetch_assoc()):?><tr><td><?=e($r['username']??'')?></td><td><?=e(trim(($r['first_name']??'').' '.($r['last_name']??'')))?></td><td><?=e($r['account_number']??'')?></td><td><?=e(ucfirst($r['status']??'active'))?></td><td><a class="btn btn-secondary" href="edit.php?id=<?=$r['id']?>">Edit</a></td></tr><?php endwhile;endif;?></tbody></table></div></div></div><?php require '../includes/footer.php';?>