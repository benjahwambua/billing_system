<?php
require_once '../includes/auth.php';
requireActiveUser();
$tenantId=requireTenantContext();
requireModulePermission('expenses','view');
global $conn;

$rows=[];$total=0.0;$today=0.0;$month=0.0;
$columns=[];$check=$conn->query("SHOW COLUMNS FROM expenses");if($check)while($c=$check->fetch_assoc())$columns[]=$c['Field'];
$amountColumn=in_array('amount',$columns,true)?'amount':(in_array('total',$columns,true)?'total':null);
$dateColumn=in_array('expense_date',$columns,true)?'expense_date':(in_array('date',$columns,true)?'date':null);
if($amountColumn){
    $stmt=$conn->prepare("SELECT * FROM expenses WHERE tenant_id=? ORDER BY id DESC LIMIT 500");
    if($stmt){$stmt->bind_param('i',$tenantId);$stmt->execute();$r=$stmt->get_result();while($row=$r->fetch_assoc()){$rows[]=$row;$amount=(float)($row[$amountColumn]??0);$total+=$amount;$date=substr((string)($dateColumn?$row[$dateColumn]:($row['created_at']??'')),0,10);if($date===systemDate())$today+=$amount;if(substr($date,0,7)===substr(systemDate(),0,7))$month+=$amount;}$stmt->close();}
}
$pageTitle='Expenses';require_once '../includes/header.php';
?>
<div class="dashboard-card"><h2>Expenses</h2><p>Operating expenses for this tenant.</p>
<div style="display:flex;gap:16px;flex-wrap:wrap;margin:18px 0">
<div class="dashboard-card" style="margin:0;min-width:180px"><small>All Time</small><h3><?=e(formatMoney($total))?></h3></div>
<div class="dashboard-card" style="margin:0;min-width:180px"><small>Today</small><h3><?=e(formatMoney($today))?></h3></div>
<div class="dashboard-card" style="margin:0;min-width:180px"><small>This Month</small><h3><?=e(formatMoney($month))?></h3></div>
</div>
<div class="table-responsive"><table><thead><tr><th>Description</th><th>Category</th><th>Amount</th><th>Date</th><th>Status</th></tr></thead><tbody>
<?php foreach($rows as $row):?><tr><td><?=e($row['description']??$row['name']??'')?></td><td><?=e($row['category']??$row['category_id']??'')?></td><td><?=e(formatMoney($amountColumn?$row[$amountColumn]:0))?></td><td><?=e($dateColumn?$row[$dateColumn]:($row['created_at']??''))?></td><td><?=e($row['status']??'')?></td></tr><?php endforeach;?>
<?php if(!$rows):?><tr><td colspan="5">No expenses found.</td></tr><?php endif;?></tbody></table></div>
</div>
<?php require_once '../includes/footer.php'; ?>