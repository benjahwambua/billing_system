<?php
require_once '../includes/auth.php';
requireLogin();
$tenantId = requireTenant();
$pageTitle = 'Customer Internet Accounts';

function accountCols() {
    global $conn;
    $out=[]; $r=$conn->query("SHOW COLUMNS FROM internet_accounts");
    if($r) while($x=$r->fetch_assoc()) $out[]=$x['Field'];
    return $out;
}
$cols=accountCols();
if(!$cols) die('Internet accounts table is not available.');
if(!in_array('tenant_id',$cols,true)){ http_response_code(503); exit('Internet account tenant isolation is unavailable. Please contact the administrator.'); }

$q=trim($_GET['q']??''); $status=trim($_GET['status']??'');
$where=['ia.tenant_id=?'];$params=[$tenantId];$types='i';
if($q!==''){ $where[]='(c.customer_number LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ? OR ia.account_number LIKE ?)'; $v='%'.$q.'%'; for($i=0;$i<4;$i++){$params[]=$v;$types.='s';} }
if($status!=='' && in_array('status',$cols,true)){ $where[]='ia.status=?';$params[]=$status;$types.='s'; }
$sql="SELECT ia.*, c.customer_number, c.first_name, c.last_name, ip.name AS plan_name, ip.price AS plan_price
      FROM internet_accounts ia
      LEFT JOIN customers c ON c.id=ia.customer_id AND c.tenant_id=ia.tenant_id
      LEFT JOIN internet_plans ip ON ip.id=ia.plan_id AND ip.tenant_id=ia.tenant_id";
if($where)$sql.=' WHERE '.implode(' AND ',$where);
$sql.=' ORDER BY ia.id DESC LIMIT 200';
$stmt=$conn->prepare($sql);$accounts=[];
if($stmt){$b=[$types];foreach($params as $k=>$v)$b[]=&$params[$k];if($params)call_user_func_array([$stmt,'bind_param'],$b);if($stmt->execute()){ $r=$stmt->get_result();while($x=$r->fetch_assoc())$accounts[]=$x;}$stmt->close();}
require_once '../includes/header.php';
?>
<div class="page-toolbar" style="display:flex;justify-content:space-between;align-items:center;gap:15px;margin-bottom:20px;">
 <p style="margin:0;">Assign internet plans to registered customers and manage service status.</p>
 <a href="add.php" class="btn btn-primary">+ Create Customer Account</a>
</div>
<div class="dashboard-card" style="margin-bottom:20px;"><form method="get" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;">
 <div><label>Search</label><input type="text" name="q" value="<?=e($q)?>" placeholder="Customer or account"></div>
 <?php if(in_array('status',$cols,true)): ?><div><label>Status</label><select name="status"><option value="">All</option><?php foreach(['active','suspended','expired','pending','inactive'] as $s): ?><option value="<?=$s?>" <?=$status===$s?'selected':''?>><?=ucfirst($s)?></option><?php endforeach;?></select></div><?php endif;?>
 <button class="btn btn-secondary">Filter</button><a href="index.php" class="btn btn-light">Reset</a>
</form></div>
<div class="dashboard-card"><div style="overflow-x:auto;"><table class="data-table" style="width:100%;"><thead><tr><th>Account</th><th>Customer</th><th>Plan</th><th>Price</th><th>Expiry</th><th>Status</th><th>Action</th></tr></thead><tbody>
<?php if(!$accounts):?><tr><td colspan="7">No customer internet accounts found.</td></tr><?php else: foreach($accounts as $a): $computed=getInternetAccountStatus($a);?><tr>
<td><strong><?=e($a['account_number']??('#'.$a['id']))?></strong></td><td><?=e(trim(($a['first_name']??'').' '.($a['last_name']??'')))?><br><small><?=e($a['customer_number']??'')?></small></td>
<td><?=e($a['plan_name']??'—')?></td><td><?=e(formatMoney($a['plan_price']??0))?></td><td><?=e($a['expiry_date']??$a['billing_date']??'—')?></td>
<td><span class="status-badge <?=$computed==='active'?'active':'inactive'?>"><?=e(ucfirst($computed))?></span></td>
<td><a class="btn btn-sm" href="edit.php?id=<?=(int)$a['id']?>">Edit</a></td></tr><?php endforeach; endif;?>
</tbody></table></div></div>
<?php require_once '../includes/footer.php'; ?>