<?php
require_once '../includes/auth.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('billing','view');
$tenantId=(int)getCurrentTenantId();
if($tenantId<=0){http_response_code(403);die('A valid tenant context is required to view billing data.');} $stats=['invoices'=>0,'payments'=>0,'outstanding'=>0];
foreach([['invoices','invoices'],['payments','payments']] as $s){$q=$conn->query("SHOW TABLES LIKE '{$s[1]}'");if($q&&$q->num_rows){$sql="SELECT COUNT(*) total FROM {$s[1]}";if($tenantId)$sql.=" WHERE tenant_id=".(int)$tenantId;$r=$conn->query($sql);$stats[$s[0]]=$r?(int)$r->fetch_assoc()['total']:0;}}
$q=$conn->query("SHOW TABLES LIKE 'invoices'");
if($q&&$q->num_rows){$sql="SELECT COALESCE(SUM(GREATEST(total_amount-COALESCE(paid_amount,0),0)),0) total FROM invoices WHERE status NOT IN ('paid','cancelled')";if($tenantId)$sql.=" AND tenant_id=".(int)$tenantId;$r=$conn->query($sql);$stats['outstanding']=$r?(float)$r->fetch_assoc()['total']:0;}
$pageTitle='Billing'; require_once '../includes/header.php';
?>
<div class="dashboard-card"><h2>Billing</h2><div class="stats-grid"><div class="stat-card"><h3><?=e($stats['invoices'])?></h3><p>Invoices</p></div><div class="stat-card"><h3><?=e($stats['payments'])?></h3><p>Payments</p></div><div class="stat-card"><h3><?=e(formatMoney($stats['outstanding']))?></h3><p>Outstanding</p></div></div>
<div style="margin-top:20px;display:flex;gap:10px;flex-wrap:wrap"><a class="btn" href="../invoices/index.php">Invoices</a><a class="btn" href="../payments/index.php">Payments</a><a class="btn" href="../receipts/index.php">Receipts</a><a class="btn" href="outstanding.php">Debtors</a></div></div>
<?php require_once '../includes/footer.php'; ?>