<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('billing','view');
global $conn;
$tenantId=(int)getCurrentTenantId();
if($tenantId<=0){http_response_code(403);die('A valid tenant context is required to view expenses.');}
$rows=[];$columns=[];
$check=$conn->query("SHOW COLUMNS FROM expenses");
if($check){while($column=$check->fetch_assoc())$columns[]=$column['Field'];}
if(!in_array('tenant_id',$columns,true)){http_response_code(503);die('Expense records are not tenant-scoped in this database schema. Access is blocked until the schema is corrected.');}
$stmt=$conn->prepare("SELECT * FROM expenses WHERE tenant_id=? ORDER BY id DESC LIMIT 500");
if($stmt){$stmt->bind_param('i',$tenantId);if($stmt->execute()){$result=$stmt->get_result();while($row=$result->fetch_assoc())$rows[]=$row;}$stmt->close();}
$pageTitle='Expenses';require_once '../includes/header.php';?>
<div class="dashboard-card"><div class="page-header"><div><h2>Expenses</h2><p>Tenant expense records and operating costs.</p></div></div><div class="table-responsive"><table><thead><tr><th>Description</th><th>Category</th><th>Amount</th><th>Date</th><th>Status</th></tr></thead><tbody>
<?php foreach($rows as $x):?><tr><td><?=e($x['description']??$x['name']??'')?></td><td><?=e($x['category']??$x['category_id']??'')?></td><td><?=e(formatMoney($x['amount']??$x['total']??0))?></td><td><?=e($x['expense_date']??$x['date']??$x['created_at']??'')?></td><td><?=e($x['status']??'')?></td></tr><?php endforeach;if(!$rows):?><tr><td colspan="5">No expenses found.</td></tr><?php endif;?></tbody></table></div></div><?php require_once '../includes/footer.php';?>
