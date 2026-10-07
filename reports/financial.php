<?php
require_once '../includes/auth.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('reports','view');
$tenantId=(int)getCurrentTenantId();

$billed=$collected=$outstanding=$expenses=$overdue=0.0;
$q=$conn->prepare("SELECT COALESCE(SUM(COALESCE(total_amount,total,amount,0)),0) total FROM invoices WHERE tenant_id=? AND LOWER(COALESCE(status,''))<>'cancelled'");
if($q){$q->bind_param('i',$tenantId);$q->execute();$billed=(float)($q->get_result()->fetch_assoc()['total']??0);$q->close();}
$q=$conn->prepare("SELECT COALESCE(SUM(amount),0) total FROM payments WHERE tenant_id=?");
if($q){$q->bind_param('i',$tenantId);$q->execute();$collected=(float)($q->get_result()->fetch_assoc()['total']??0);$q->close();}
$outstanding=max(0,$billed-$collected);
$q=$conn->prepare("SELECT COALESCE(SUM(e.amount),0) total FROM expenses e WHERE e.tenant_id=?");
if($q){$q->bind_param('i',$tenantId);$q->execute();$expenses=(float)($q->get_result()->fetch_assoc()['total']??0);$q->close();}
$q=$conn->prepare("SELECT COALESCE(SUM(GREATEST(COALESCE(i.total_amount,i.total,i.amount,0)-COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.invoice_id=i.id AND p.tenant_id=i.tenant_id),0),0)),0) total
                   FROM invoices i WHERE i.tenant_id=? AND LOWER(COALESCE(i.status,'')) NOT IN ('paid','cancelled') AND i.due_date<CURDATE()");
if($q){$q->bind_param('i',$tenantId);$q->execute();$overdue=(float)($q->get_result()->fetch_assoc()['total']??0);$q->close();}

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