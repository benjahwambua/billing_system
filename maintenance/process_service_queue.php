<?php
require_once '../includes/auth.php';
requireActiveUser();
requireTenantContext();
require_once '../includes/billing_workflow.php';
require_once '../includes/hotspot_workflow.php';

$tenantId=(int)getCurrentTenantId();
$overdue=flexihubProcessOverdueSuspensions($tenantId,100);
$billing=flexihubGenerateRecurringInvoices($tenantId,100);
$accountExpiry=flexihubProcessAccountExpiry($tenantId,100);
$subscriptionExpiry=flexihubProcessSubscriptionExpiry($tenantId,100);
$result=flexihubProcessServiceActivationQueue($tenantId,50);
$hotspotFinalization=flexihubProcessHotspotFinalizationQueue($tenantId,25);$hotspotExpiry=flexihubProcessHotspotExpiry($tenantId,100);

$failureSummary=['activation'=>0,'hotspot_finalization'=>0,'router_sync'=>0,'mpesa'=>0];
$recentFailures=[];
$hasTable=function($table)use($conn){$table=$conn->real_escape_string($table);$q=$conn->query("SHOW TABLES LIKE '{$table}'");return $q&&$q->num_rows>0;};
if($hasTable('service_activation_queue')){
    $q=$conn->prepare("SELECT COUNT(*) total FROM service_activation_queue WHERE tenant_id=? AND status='failed'");
    if($q){$q->bind_param('i',$tenantId);$q->execute();$failureSummary['activation']=(int)($q->get_result()->fetch_assoc()['total']??0);$q->close();}
}
if($hasTable('hotspot_finalization_queue')){
    $q=$conn->prepare("SELECT COUNT(*) total FROM hotspot_finalization_queue WHERE tenant_id=? AND status='failed'");
    if($q){$q->bind_param('i',$tenantId);$q->execute();$failureSummary['hotspot_finalization']=(int)($q->get_result()->fetch_assoc()['total']??0);$q->close();}
}
if($hasTable('router_sync_logs')){
    $q=$conn->prepare("SELECT COUNT(*) total FROM router_sync_logs WHERE tenant_id=? AND status='failed'");
    if($q){$q->bind_param('i',$tenantId);$q->execute();$failureSummary['router_sync']=(int)($q->get_result()->fetch_assoc()['total']??0);$q->close();}
    $q=$conn->prepare("SELECT router_id,service_type,external_username,action,message,created_at FROM router_sync_logs WHERE tenant_id=? AND status='failed' ORDER BY id DESC LIMIT 10");
    if($q){$q->bind_param('i',$tenantId);$q->execute();$rs=$q->get_result();while($row=$rs->fetch_assoc())$recentFailures[]=['source'=>'Router sync','label'=>trim(($row['service_type']??'').' / '.($row['action']??'')),'message'=>$row['message']??'','created_at'=>$row['created_at']??''];$q->close();}
}
if($hasTable('payment_gateway_transactions')){
    $q=$conn->prepare("SELECT COUNT(*) total FROM payment_gateway_transactions WHERE tenant_id=? AND status='failed'");
    if($q){$q->bind_param('i',$tenantId);$q->execute();$failureSummary['mpesa']=(int)($q->get_result()->fetch_assoc()['total']??0);$q->close();}
}

$pageTitle='Service Activation Queue';
require_once '../includes/header.php';
?>
<div class="dashboard-card">
    <h2>Service Activation Queue</h2>
    <p>Processes pending customer service activations for the current tenant.</p>
    <div class="form-grid" style="margin-top:20px">
        <div class="card"><strong>Failed Activation Jobs</strong><div style="font-size:28px"><?=e($failureSummary['activation'])?></div></div>
        <div class="card"><strong>Failed Hotspot Jobs</strong><div style="font-size:28px"><?=e($failureSummary['hotspot_finalization'])?></div></div>
        <div class="card"><strong>Router Sync Failures</strong><div style="font-size:28px"><?=e($failureSummary['router_sync'])?></div></div>
        <div class="card"><strong>Failed M-Pesa Transactions</strong><div style="font-size:28px"><?=e($failureSummary['mpesa'])?></div></div>
        <div class="card"><strong>Accounts Suspended</strong><div style="font-size:28px"><?=e($overdue['suspended'])?></div></div>
        <div class="card"><strong>Overdue Invoices</strong><div style="font-size:28px"><?=e($overdue['marked_overdue'])?></div></div>
        <div class="card"><strong>Invoices Generated</strong><div style="font-size:28px"><?=e($billing['generated'])?></div></div>
        <div class="card"><strong>Invoice Generation Failed</strong><div style="font-size:28px"><?=e($billing['failed'])?></div></div>
        <div class="card"><strong>Accounts Expired</strong><div style="font-size:28px"><?=e($accountExpiry['expired'])?></div></div>
        <div class="card"><strong>Subscriptions Expired</strong><div style="font-size:28px"><?=e($subscriptionExpiry['expired'])?></div></div>
        <div class="card"><strong>Expiry Failures</strong><div style="font-size:28px"><?=e($subscriptionExpiry['failed'])?></div></div>
        <div class="card"><strong>Processed</strong><div style="font-size:28px"><?=e($result['processed'])?></div></div>
        <div class="card"><strong>Completed</strong><div style="font-size:28px"><?=e($result['completed'])?></div></div>
        <div class="card"><strong>Failed</strong><div style="font-size:28px"><?=e($result['failed'])?></div></div>
        <div class="card"><strong>Hotspot Finalized</strong><div style="font-size:28px"><?=e($hotspotFinalization['completed'])?></div></div>
        <div class="card"><strong>Hotspot Finalization Failed</strong><div style="font-size:28px"><?=e($hotspotFinalization['failed'])?></div></div>
        <div class="card"><strong>Hotspot Expired</strong><div style="font-size:28px"><?=e($hotspotExpiry['expired'])?></div></div>
        <div class="card"><strong>Hotspot Expiry Failed</strong><div style="font-size:28px"><?=e($hotspotExpiry['failed'])?></div></div>
    </div>
    <?php if($recentFailures): ?>
    <div class="dashboard-card" style="margin-top:20px">
        <h2>Recent Router Synchronization Failures</h2>
        <div style="overflow:auto;margin-top:12px"><table class="table"><thead><tr><th>Service / Action</th><th>Message</th><th>Time</th></tr></thead><tbody>
        <?php foreach($recentFailures as $failure): ?><tr><td><?=e($failure['label'])?></td><td><?=e($failure['message'])?></td><td><?=e($failure['created_at'])?></td></tr><?php endforeach; ?>
        </tbody></table></div>
    </div>
    <?php endif; ?>
    <p style="margin-top:20px">For production, run this endpoint from a scheduled job/cron after the billing and M-Pesa callback workflows are enabled.</p>
</div>
<?php require_once '../includes/footer.php'; ?>