<?php
require_once '../includes/auth.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('reports','view');
require_once '../includes/billing_workflow.php';

$tenantId=(int)getCurrentTenantId();
$rows=[]; $summary=['current'=>0,'1_30'=>0,'31_60'=>0,'61_90'=>0,'90_plus'=>0,'total'=>0];

$sql="SELECT i.id,i.invoice_number,i.customer_id,i.invoice_date,i.due_date,i.status,
             c.customer_number,c.first_name,c.last_name,
             COALESCE(i.total_amount,i.total,i.amount,0) invoice_total,
             COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.invoice_id=i.id AND p.tenant_id=i.tenant_id),0) actual_paid
      FROM invoices i
      LEFT JOIN customers c ON c.id=i.customer_id AND c.tenant_id=i.tenant_id
      WHERE i.tenant_id=? AND LOWER(COALESCE(i.status,'')) NOT IN ('paid','cancelled')
      ORDER BY i.due_date ASC,i.id ASC";
$stmt=$conn->prepare($sql);
if($stmt){
    $stmt->bind_param('i',$tenantId);$stmt->execute();$res=$stmt->get_result();
    while($r=$res->fetch_assoc()){
        $total=(float)$r['invoice_total']; $paid=(float)$r['actual_paid']; $balance=max(0,$total-$paid);
        if($balance<=0) continue;
        $due=$r['due_date']??$r['invoice_date']??date('Y-m-d');
        $days=max(0,(int)floor((strtotime(date('Y-m-d'))-strtotime($due))/86400));
        if($days<=0){$bucket='current';}
        elseif($days<=30){$bucket='1_30';}
        elseif($days<=60){$bucket='31_60';}
        elseif($days<=90){$bucket='61_90';}
        else{$bucket='90_plus';}
        $summary[$bucket]+=$balance;$summary['total']+=$balance;
        $r['_total']=$total;$r['_paid']=$paid;$r['_balance']=$balance;$r['_days']=$days;$r['_bucket']=$bucket;
        $rows[]=$r;
    }
    $stmt->close();
}
$pageTitle='Aged Receivables';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<div class="main-content">
<div class="page-header"><div><h1>Aged Receivables</h1><p>Outstanding customer balances grouped by days past due.</p></div></div>
<div class="stats-grid">
<div class="stat-card"><h3><?=e(formatMoney($summary['current']))?></h3><p>Current</p></div>
<div class="stat-card"><h3><?=e(formatMoney($summary['1_30']))?></h3><p>1–30 Days</p></div>
<div class="stat-card"><h3><?=e(formatMoney($summary['31_60']))?></h3><p>31–60 Days</p></div>
<div class="stat-card"><h3><?=e(formatMoney($summary['61_90']))?></h3><p>61–90 Days</p></div>
<div class="stat-card"><h3><?=e(formatMoney($summary['90_plus']))?></h3><p>90+ Days</p></div>
<div class="stat-card"><h3><?=e(formatMoney($summary['total']))?></h3><p>Total Outstanding</p></div>
</div>
<div class="dashboard-card" style="margin-top:20px"><div class="table-responsive"><table><thead><tr><th>Customer</th><th>Invoice</th><th>Due</th><th>Days</th><th>Total</th><th>Paid</th><th>Balance</th><th>Age</th></tr></thead><tbody>
<?php foreach($rows as $r): ?><tr>
<td><?=e(($r['customer_number']??'').' — '.trim(($r['first_name']??'').' '.($r['last_name']??'')))?></td>
<td><?=e($r['invoice_number']??$r['id'])?></td><td><?=e($r['due_date']??'—')?></td><td><?=e($r['_days'])?></td>
<td><?=e(formatMoney($r['_total']))?></td><td><?=e(formatMoney($r['_paid']))?></td><td><b><?=e(formatMoney($r['_balance']))?></b></td>
<td><?=e(str_replace('_','–',$r['_bucket']))?></td></tr><?php endforeach;?>
<?php if(!$rows):?><tr><td colspan="8" class="empty">No outstanding receivables.</td></tr><?php endif;?>
</tbody></table></div></div>
</div>
<?php require_once '../includes/footer.php'; ?>