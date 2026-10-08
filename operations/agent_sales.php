<?php
require_once '../includes/auth.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('operations','view');
global $conn;
$tenantId=(int)getCurrentTenantId();$error='';$success='';$rows=[];

if($_SERVER['REQUEST_METHOD']==='POST'){
 requireCsrf();
 if(isset($_POST['record_sale'])){
  requireModulePermission('operations','create');
  $agent=trim((string)($_POST['agent_name']??''));$phone=trim((string)($_POST['agent_phone']??''));$product=trim((string)($_POST['product_type']??''));
  $reference=trim((string)($_POST['reference']??''));$amount=(float)($_POST['amount']??0);$commission=(float)($_POST['commission']??0);$status=trim((string)($_POST['status']??'pending'));$notes=trim((string)($_POST['notes']??''));
  if($agent===''||$product==='')$error='Agent name and product/service are required.';
  elseif($amount<=0)$error='Sale amount must be greater than zero.';
  elseif($commission<0||$commission>$amount)$error='Commission must be between zero and the sale amount.';
  elseif(!in_array($status,['completed','pending','cancelled','settled'],true))$error='Invalid sale status.';
  else{$q=$conn->prepare("INSERT INTO agent_sales (tenant_id,agent_name,agent_phone,product_type,reference,amount,commission,status,notes) VALUES (?,?,?,?,?,?,?,?,?)");if(!$q)$error='Unable to prepare the sale record.';else{$q->bind_param('issssddss',$tenantId,$agent,$phone,$product,$reference,$amount,$commission,$status,$notes);$success=$q->execute()?'Agent sale recorded successfully.':'Unable to record the agent sale.';$q->close();}}
 }else{
  requireModulePermission('operations','edit');
  $saleId=(int)($_POST['sale_id']??0);$action=trim((string)($_POST['action']??''));$allowed=['completed','settled','cancelled','pending'];
  if(!$saleId||!in_array($action,$allowed,true))$error='Invalid settlement action.';
  else{$q=$conn->prepare("UPDATE agent_sales SET status=? WHERE id=? AND tenant_id=?");if(!$q)$error='Unable to prepare settlement update.';else{$q->bind_param('sii',$action,$saleId,$tenantId);$q->execute();$changed=$q->affected_rows;$q->close();$success=$changed?'Agent sale status updated.':'No matching sale was changed.';}}
 }
}
$q=$conn->prepare("SELECT * FROM agent_sales WHERE tenant_id=? ORDER BY id DESC LIMIT 500");if($q){$q->bind_param('i',$tenantId);$q->execute();$z=$q->get_result();while($r=$z->fetch_assoc())$rows[]=$r;$q->close();}
$totalSales=0;$totalCommission=0;$pending=0;$settled=0;foreach($rows as $r){$totalSales+=(float)$r['amount'];$totalCommission+=(float)$r['commission'];if(($r['status']??'')==='pending')$pending++;if(($r['status']??'')==='settled')$settled++;}
$pageTitle='Agent Sales';require_once '../includes/header.php';
?>
<div class="dashboard-card">
<div class="page-header"><div><h2>Agent Sales</h2><p>Record agent sales, commissions and settlement status.</p></div></div>
<?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?><?php if($success):?><div class="alert alert-success"><?=e($success)?></div><?php endif;?>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin:16px 0">
<div class="card" style="padding:16px"><small>Sales</small><h3><?=e(formatMoney($totalSales))?></h3></div><div class="card" style="padding:16px"><small>Commission</small><h3><?=e(formatMoney($totalCommission))?></h3></div><div class="card" style="padding:16px"><small>Pending</small><h3><?=e((string)$pending)?></h3></div><div class="card" style="padding:16px"><small>Settled</small><h3><?=e((string)$settled)?></h3></div>
</div>
<div class="card" style="padding:20px;margin-bottom:20px"><h3>Record Agent Sale</h3><form method="post"><?=csrfField()?><input type="hidden" name="record_sale" value="1"><div class="form-grid">
<label>Agent Name *<input name="agent_name" maxlength="150" required></label><label>Agent Phone<input name="agent_phone" maxlength="40"></label><label>Product / Service *<input name="product_type" maxlength="50" required></label><label>Reference<input name="reference" maxlength="120"></label><label>Amount *<input type="number" name="amount" min="0.01" step="0.01" required></label><label>Commission *<input type="number" name="commission" min="0" step="0.01" value="0" required></label><label>Status<select name="status"><option value="pending">Pending</option><option value="completed">Completed</option><option value="settled">Settled</option><option value="cancelled">Cancelled</option></select></label><label>Notes<textarea name="notes" rows="2"></textarea></label>
</div><button class="btn btn-primary">Record Sale</button></form></div>
<div class="table-responsive"><table><thead><tr><th>Agent</th><th>Product</th><th>Reference</th><th>Amount</th><th>Commission</th><th>Status</th><th>Date</th><th>Action</th></tr></thead><tbody>
<?php foreach($rows as $x):?><tr><td><?=e($x['agent_name'])?></td><td><?=e($x['product_type'])?></td><td><?=e($x['reference']??'—')?></td><td><?=e(formatMoney($x['amount']))?></td><td><?=e(formatMoney($x['commission']))?></td><td><?=e($x['status'])?></td><td><?=e($x['sold_at'])?></td><td><form method="post" style="display:flex;gap:6px;align-items:center"><?=csrfField()?><input type="hidden" name="sale_id" value="<?=$x['id']?>"><select name="action"><option value="pending">Pending</option><option value="completed">Completed</option><option value="settled">Settled</option><option value="cancelled">Cancelled</option></select><button class="btn btn-secondary">Update</button></form></td></tr><?php endforeach;?>
<?php if(!$rows):?><tr><td colspan="8">No agent sales recorded.</td></tr><?php endif;?></tbody></table></div>
</div><?php require_once '../includes/footer.php';?>