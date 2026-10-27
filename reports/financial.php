<?php
require_once '../includes/auth.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('reports','view');
require_once '../includes/billing_workflow.php';
$tenantId=(int)getCurrentTenantId();

$billed=$collected=$outstanding=$expenses=$overdue=0.0;

// Aggregate payments by invoice once, then use the shared total resolver against
// each row. This avoids SQL referring to optional amount columns absent from older schemas.
$paidByInvoice=[];
$q=$conn->prepare("SELECT invoice_id, COALESCE(SUM(amount),0) AS paid FROM payments WHERE tenant_id=? GROUP BY invoice_id");
if($q){
    $q->bind_param('i',$tenantId);
    if($q->execute()){
        $result=$q->get_result();
        while($row=$result->fetch_assoc()) $paidByInvoice[(int)($row['invoice_id']??0)]=(float)$row['paid'];
    }
    $q->close();
}
$q=$conn->prepare("SELECT * FROM invoices WHERE tenant_id=?");
if($q){
    $q->bind_param('i',$tenantId);
    if($q->execute()){
        $result=$q->get_result();
        while($invoice=$result->fetch_assoc()){
            $status=strtolower(trim((string)($invoice['status']??'')));
            if($status==='cancelled') continue;
            $total=flexihubInvoiceTotal($invoice);
            $paid=(float)($paidByInvoice[(int)$invoice['id']]??0);
            $balance=max(0,$total-$paid);
            $billed+=$total;
            $outstanding+=$balance;
            $dueDate=(string)($invoice['due_date']??'');
            if($balance>0.00001 && $dueDate!=='' && $dueDate<date('Y-m-d')) $overdue+=$balance;
        }
    }
    $q->close();
}
$q=$conn->prepare("SELECT COALESCE(SUM(amount),0) total FROM payments WHERE tenant_id=?");
if($q){$q->bind_param('i',$tenantId);$q->execute();$collected=(float)($q->get_result()->fetch_assoc()['total']??0);$q->close();}
$q=$conn->prepare("SELECT COALESCE(SUM(e.amount),0) total FROM expenses e WHERE e.tenant_id=?");
if($q){$q->bind_param('i',$tenantId);$q->execute();$expenses=(float)($q->get_result()->fetch_assoc()['total']??0);$q->close();}

$pageTitle='Financial Report';require_once '../includes/header.php';require_once '../includes/sidebar.php';?>
<div class="main-content"><div class="page-header"><div><h1>Financial Report</h1><p>Tenant-scoped billing, collections, receivables and operating position.</p></div></div>
<div class="stats-grid">
<div class="stat-card"><h3><?=e(formatMoney($billed))?></h3><p>Total Invoiced</p></div>
<div class="stat-card"><h3><?=e(formatMoney($collected))?></h3><p>Total Collections</p></div>
<div class="stat-card"><h3><?=e(formatMoney($outstanding))?></h3><p>Outstanding Receivables</p></div>
<div class="stat-card"><h3><?=e(formatMoney($overdue))?></h3><p>Overdue Receivables</p></div>
<div class="stat-card"><h3><?=e(formatMoney($expenses))?></h3><p>Recorded Expenses</p></div>
<div class="stat-card"><h3><?=e(formatMoney($collected-$expenses))?></h3><p>Cash Position</p></div>
</div>
<div class="dashboard-card" style="margin-top:20px"><h2>Financial Workspaces</h2><div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:15px">
<a class="btn" href="aged_receivables.php">Aged Receivables</a><a class="btn" href="customer_statement.php">Customer Statement</a><a class="btn" href="../payments/index.php">Payments</a><a class="btn" href="../receipts/index.php">Receipts</a>
</div></div></div>
<?php require_once '../includes/footer.php'; ?>