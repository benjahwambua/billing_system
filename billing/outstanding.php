<?php
require_once '../includes/auth.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('billing','view');
require_once '../includes/billing_workflow.php';

$tenantId=(int)getCurrentTenantId();$rows=[];
$sql="SELECT i.*,c.customer_number,c.first_name,c.last_name,
             COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.invoice_id=i.id AND p.tenant_id=i.tenant_id),0) actual_paid
      FROM invoices i
      LEFT JOIN customers c ON c.id=i.customer_id AND c.tenant_id=i.tenant_id
      WHERE i.tenant_id=? AND LOWER(COALESCE(i.status,'')) NOT IN ('paid','cancelled')
      ORDER BY i.due_date ASC,i.id ASC LIMIT 500";
$stmt=$conn->prepare($sql);
if($stmt){$stmt->bind_param('i',$tenantId);$stmt->execute();$res=$stmt->get_result();while($x=$res->fetch_assoc()){
    $total=flexihubInvoiceTotal($x);$paid=(float)$x['actual_paid'];$x['_balance']=max(0,$total-$paid);$x['_total']=$total;$x['_paid']=$paid;
    if($x['_balance']>0)$rows[]=$x;
}$stmt->close();}
$pageTitle='Outstanding';require_once '../includes/header.php';require_once '../includes/sidebar.php';
?>
<div class="main-content"><div class="page-header"><div><h1>Outstanding / Debtors</h1><p>Open invoices using the actual payment ledger.</p></div><a class="btn btn-primary" href="../reports/aged_receivables.php">Aged Receivables</a></div>
<div class="dashboard-card"><div class="table-responsive"><table><thead><tr><th>Invoice</th><th>Customer</th><th>Total</th><th>Paid</th><th>Balance</th><th>Due</th><th>Status</th></tr></thead><tbody>
<?php foreach($rows as $row): ?><tr><td><?=e($row['invoice_number']??$row['number']??$row['id'])?></td><td><?=e(($row['customer_number']??'').' — '.trim(($row['first_name']??'').' '.($row['last_name']??'')))?></td><td><?=e(formatMoney($row['_total']))?></td><td><?=e(formatMoney($row['_paid']))?></td><td><b><?=e(formatMoney($row['_balance']))?></b></td><td><?=e($row['due_date']??'—')?></td><td><?=e($row['status']??'')?></td></tr><?php endforeach;?>
<?php if(!$rows): ?><tr><td colspan="7" class="empty">No outstanding invoices.</td></tr><?php endif; ?></tbody></table></div></div></div>
<?php require_once '../includes/footer.php'; ?>