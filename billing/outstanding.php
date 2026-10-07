<?php
require_once '../includes/auth.php';
requireLogin();
$tenantId=getCurrentTenantId();$rows=[];$q=$conn->query("SHOW TABLES LIKE 'invoices');
if($q&&$q->num_rows){$sql="SELECT * FROM invoices WHERE status NOT IN ('paid','cancelled')";if($tenantId)$sql.=" AND tenant_id=".(int)$tenantId;$sql.=" ORDER BY id DESC LIMIT 500";$r=$conn->query($sql);if($r)while($x=$r->fetch_assoc())$rows[]=$x;}
$pageTitle='Outstanding';require_once '../includes/header.php';
?>
<div class="dashboard-card"><h2>Outstanding / Debtors</h2><div class="table-responsive"><table><thead><tr><th>Invoice</th><th>Customer</th><th>Total</th><th>Paid</th><th>Balance</th><th>Due</th><th>Status</th></tr></thead><tbody>
<?php foreach($rows as $row): ?><tr><td><?=e($row['invoice_number']??$row['number']??$row['id'])?></td><td><?=e(($row['customer_number']??'').' — '.trim(($row['first_name']??'').' '.($row['last_name']??'')))?></td><td><?=e(formatMoney($row['total_amount']??$row['total']??0))?></td><td><?=e(formatMoney($row['actual_paid']??0))?></td><td><?=e(formatMoney(max(0,(float)($row['total_amount']??$row['total']??0)-(float)($row['actual_paid']??0))))?></td><td><?=e($row['due_date']??'—')?></td><td><?=e($row['status']??'')?></td></tr><?php endforeach; ?>
<?php if(!$rows): ?><tr><td colspan="7">No outstanding invoices.</td></tr><?php endif; ?></tbody></table></div></div>
<?php require_once '../includes/footer.php'; ?>