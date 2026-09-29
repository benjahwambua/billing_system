<?php
require_once '../includes/auth.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('invoices','view');
require_once '../includes/billing_workflow.php';

global $conn;
$tid=(int)getCurrentTenantId();
$id=(int)($_GET['id']??0);
if(!$id){setFlash('error','Invoice not found.');redirect('index.php');}

$stmt=$conn->prepare("SELECT i.*,c.customer_number,c.first_name,c.last_name,c.phone,c.email
                      FROM invoices i
                      LEFT JOIN customers c ON c.id=i.customer_id AND c.tenant_id=i.tenant_id
                      WHERE i.id=? AND i.tenant_id=? LIMIT 1");
$stmt->bind_param('ii',$id,$tid);
$stmt->execute();
$invoice=$stmt->get_result()->fetch_assoc();
$stmt->close();
if(!$invoice){setFlash('error','Invoice not found.');redirect('index.php');}

$total=flexihubInvoiceTotal($invoice);
$paid=flexihubInvoicePaid($id,$tid);
$balance=max(0,$total-$paid);
$status=$invoice['status']??'unpaid';
if($status!=='paid' && $status!=='cancelled' && !empty($invoice['due_date']) && $invoice['due_date']<date('Y-m-d'))$status='overdue';

$payments=[];
$stmt=$conn->prepare("SELECT * FROM payments WHERE invoice_id=? AND tenant_id=? ORDER BY id DESC");
if($stmt){
    $stmt->bind_param('ii',$id,$tid);
    $stmt->execute();
    $result=$stmt->get_result();
    while($row=$result->fetch_assoc())$payments[]=$row;
    $stmt->close();
}

$pageTitle='Invoice '.($invoice['invoice_number']??$id);
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<div class="main-content">
<div class="page-header"><div><h1><?=e($invoice['invoice_number']??'Invoice')?></h1><p>Tenant-scoped invoice details.</p></div><a class="btn btn-secondary" href="index.php">Back</a></div>
<div class="card" style="padding:20px">
<h3><?=e(trim(($invoice['customer_number']??'').' — '.($invoice['first_name']??'').' '.($invoice['last_name']??'')) ?: 'Customer')?></h3>
<p>Invoice date: <?=e($invoice['invoice_date']??'')?> &nbsp; | &nbsp; Due: <?=e($invoice['due_date']??'')?></p>
<?php if(!empty($invoice['description'])): ?><p><?=e($invoice['description'])?></p><?php endif; ?>
<div class="stats-grid">
<div class="stat-card"><h3><?=e(formatMoney($total))?></h3><p>Total</p></div>
<div class="stat-card"><h3><?=e(formatMoney($paid))?></h3><p>Paid</p></div>
<div class="stat-card"><h3><?=e(formatMoney($balance))?></h3><p>Balance</p></div>
<div class="stat-card"><h3><?=e($status)?></h3><p>Status</p></div>
</div>
<h3 style="margin-top:25px">Payments</h3>
<div class="table-responsive"><table><thead><tr><th>Payment</th><th>Date</th><th>Method</th><th>Reference</th><th>Amount</th></tr></thead><tbody>
<?php foreach($payments as $p): ?><tr><td><?=e($p['payment_number']??$p['id'])?></td><td><?=e($p['payment_date']??$p['created_at']??'')?></td><td><?=e($p['payment_method']??'')?></td><td><?=e($p['reference']??'')?></td><td><?=e(formatMoney((float)($p['amount']??0)))?></td></tr><?php endforeach; ?>
<?php if(!$payments): ?><tr><td colspan="5">No payments recorded.</td></tr><?php endif; ?>
</tbody></table></div>
<?php if($balance>0 && userCan('payments','create')): ?><div style="margin-top:20px"><a class="btn btn-primary" href="../payments/add.php?invoice_id=<?=$id?>">Record Payment</a></div><?php endif; ?>
</div></div>
<?php require_once '../includes/footer.php'; ?>