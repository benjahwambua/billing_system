<?php
require_once '../includes/auth.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('invoices','view');
require_once '../includes/billing_workflow.php';

global $conn;
$tid=(int)getCurrentTenantId();
$rows=[];

$sql="SELECT i.*, c.customer_number, c.first_name, c.last_name
      FROM invoices i
      LEFT JOIN customers c ON c.id=i.customer_id AND c.tenant_id=i.tenant_id
      WHERE i.tenant_id=?
      ORDER BY i.id DESC LIMIT 500";
$stmt=$conn->prepare($sql);
if($stmt){
    $stmt->bind_param('i',$tid);
    $stmt->execute();
    $result=$stmt->get_result();
    while($row=$result->fetch_assoc()){
        $row['_total']=flexihubInvoiceTotal($row);
        $row['_paid']=flexihubInvoicePaid((int)$row['id'],$tid);
        $row['_balance']=max(0,$row['_total']-$row['_paid']);
        if(($row['status']??'')!=='paid' && ($row['status']??'')!=='cancelled' && !empty($row['due_date']) && $row['due_date']<date('Y-m-d')){
            $row['_display_status']='overdue';
        }else{
            $row['_display_status']=$row['status']??'unpaid';
        }
        $rows[]=$row;
    }
    $stmt->close();
}

$pageTitle='Invoices';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<div class="main-content">
<div class="page-header">
    <div><h1>Invoices</h1><p>Customer billing, payments and outstanding balances.</p></div>
    <?php if(userCan('invoices','create')): ?><a class="btn btn-primary" href="add.php">Create Invoice</a><?php endif; ?>
</div>
<div class="card" style="padding:20px">
<div class="table-responsive"><table>
<thead><tr><th>Invoice</th><th>Customer</th><th>Date</th><th>Due</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th><th></th></tr></thead>
<tbody>
<?php foreach($rows as $x): ?>
<tr>
<td><?=e($x['invoice_number']??$x['number']??$x['id'])?></td>
<td><?=e(trim(($x['customer_number']??'').' — '.($x['first_name']??'').' '.($x['last_name']??'')) ?: ($x['customer_id']??''))?></td>
<td><?=e($x['invoice_date']??$x['date']??'')?></td>
<td><?=e($x['due_date']??'')?></td>
<td><?=e(formatMoney($x['_total']))?></td>
<td><?=e(formatMoney($x['_paid']))?></td>
<td><?=e(formatMoney($x['_balance']))?></td>
<td><?=e($x['_display_status'])?></td>
<td><a class="btn btn-secondary" href="view.php?id=<?=$x['id']?>">View</a></td>
</tr>
<?php endforeach; ?>
<?php if(!$rows): ?><tr><td colspan="9">No invoices found.</td></tr><?php endif; ?>
</tbody></table></div>
</div></div>
<?php require_once '../includes/footer.php'; ?>