<?php
require_once '../includes/auth.php';requireLogin();requireHostContext();
$rows=[];$available=false;$total=0;$completed=0;$pending=0;$failed=0;
$check=$conn->query("SHOW TABLES LIKE 'platform_payments'");
if($check&&$check->num_rows){
    $available=true;
    $q=$conn->query("SELECT pp.id,pp.amount,pp.payment_date,pp.payment_method,pp.reference,pp.provider,pp.external_transaction_id,pp.status,pp.failure_reason,pp.confirmed_at,pp.expires_at,pp.tenant_id,pi.invoice_number,pi.status AS invoice_status,pi.total_amount AS invoice_total,pi.paid_amount AS invoice_paid,t.tenant_code,t.name AS tenant_name FROM platform_payments pp LEFT JOIN platform_invoices pi ON pi.id=pp.invoice_id LEFT JOIN tenants t ON t.id=pp.tenant_id ORDER BY pp.id DESC LIMIT 500");
    if($q)while($r=$q->fetch_assoc()){
        $rows[]=$r;
        if((string)$r['status']==='completed'){$completed++;$total+=(float)($r['amount']??0);}
        elseif((string)$r['status']==='pending')$pending++;
        elseif((string)$r['status']==='failed')$failed++;
    }
}
$pageTitle='Platform Transactions';require_once '../includes/header.php';require_once '../includes/sidebar.php';?>
<div class="main-content"><div class="page-header"><div><h1>Platform Transactions</h1><p>Payments made by tenants for Flexihub hosting subscriptions. Failed and pending transactions are retained for reconciliation.</p></div></div>
<div class="stats-grid"><div class="stat-card"><h3><?=e(formatMoney($total))?></h3><p>Completed SaaS Collections</p></div><div class="stat-card"><h3><?=e($completed)?></h3><p>Completed Payments</p></div><div class="stat-card"><h3><?=e($pending)?></h3><p>Pending</p></div><div class="stat-card alert-card"><h3><?=e($failed)?></h3><p>Failed / Validation Exceptions</p></div></div>
<div class="dashboard-card" style="margin-top:20px"><?php if(!$available):?><div class="notice">The SaaS payment schema has not been installed yet.</div><?php else:?><div class="table-responsive"><table><thead><tr><th>Date</th><th>Tenant</th><th>Invoice</th><th>Reference</th><th>Method / Provider</th><th>Status</th><th>Amount</th><th>Reconciliation</th></tr></thead><tbody>
<?php foreach($rows as $r):?>
<tr>
<td><?=e($r['payment_date']??'—')?><small><?=e($r['confirmed_at']??'')?></small></td>
<td><strong><?=e($r['tenant_name']??('Tenant #'.$r['tenant_id']))?></strong><small><?=e($r['tenant_code']??'Tenant #'.$r['tenant_id'])?></small></td>
<td><?=e($r['invoice_number']??'—')?></td>
<td><?=e($r['reference']?:($r['external_transaction_id']?:('#'.$r['id'])))?><small><?=e($r['external_transaction_id']??'')?></small></td>
<td><?=e($r['payment_method']??'—')?><small><?=e($r['provider']??'')?></small></td>
<td><span class="status status-<?=e(preg_replace('/[^a-z0-9_-]/i','',(string)$r['status']))?>"><?=e(ucfirst((string)$r['status']))?></span></td>
<td><?=e(formatMoney((float)$r['amount']))?></td>
<td><?php if((string)$r['status']==='failed'):?><strong>Action needed</strong><small><?=e($r['failure_reason']??'Payment failed; review provider result and invoice state.')?></small><?php elseif((string)$r['status']==='pending'):?><strong>Awaiting callback</strong><small><?=e($r['expires_at']?'Expires '.$r['expires_at']:'No expiry recorded')?></small><?php elseif(empty($r['invoice_number'])):?><strong>Exception</strong><small>Payment has no linked SaaS invoice.</small><?php elseif((string)$r['status']==='completed' && (string)$r['invoice_status']!=='paid' && (float)$r['invoice_paid']+0.0001 < (float)$r['invoice_total']):?><strong>Review allocation</strong><small>Payment is completed but the linked invoice is not fully paid.</small><?php else:?>Allocated to SaaS invoice<?php endif;?></td>
</tr>
<?php endforeach;?>
<?php if(!$rows):?><tr><td colspan="8" class="empty">No SaaS payments found.</td></tr><?php endif;?></tbody></table></div><?php endif;?></div></div>
<style>
.main-content{padding:24px}.page-header{margin-bottom:24px}.page-header h1{margin:0 0 6px}.page-header p{margin:0;color:#6b7280}.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px}.stat-card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px}.stat-card h3{margin:0 0 7px;font-size:24px}.stat-card p{margin:0;color:#6b7280;font-size:13px}.alert-card{border-color:#fecaca}.dashboard-card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden}.table-responsive{overflow:auto}table{width:100%;border-collapse:collapse;min-width:1200px}th,td{padding:13px 15px;border-bottom:1px solid #e5e7eb;text-align:left;vertical-align:top}th{background:#f9fafb;font-size:12px;color:#4b5563}small{display:block;color:#6b7280;margin-top:4px}.empty{text-align:center;padding:40px;color:#6b7280}.notice{padding:20px}.status{display:inline-block;padding:4px 8px;border-radius:999px;font-size:12px;font-weight:600}.status-completed{background:#dcfce7;color:#166534}.status-pending{background:#fef3c7;color:#92400e}.status-failed{background:#fee2e2;color:#991b1b}.status-reversed{background:#e5e7eb;color:#374151}
</style>
<?php require_once '../includes/footer.php';?>