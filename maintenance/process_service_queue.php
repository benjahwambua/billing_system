<?php
require_once '../includes/auth.php';
requireActiveUser();
requireTenantContext();
require_once '../includes/billing_workflow.php';
require_once '../includes/hotspot_workflow.php';

$tenantId=(int)getCurrentTenantId();
$accountExpiry=flexihubProcessAccountExpiry($tenantId,100);
$subscriptionExpiry=flexihubProcessSubscriptionExpiry($tenantId,100);
$result=flexihubProcessServiceActivationQueue($tenantId,50);
$hotspotFinalization=flexihubProcessHotspotFinalizationQueue($tenantId,25);$hotspotExpiry=flexihubProcessHotspotExpiry($tenantId,100);

$pageTitle='Service Activation Queue';
require_once '../includes/header.php';
?>
<div class="dashboard-card">
    <h2>Service Activation Queue</h2>
    <p>Processes pending customer service activations for the current tenant.</p>
    <div class="form-grid" style="margin-top:20px">
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
    <p style="margin-top:20px">For production, run this endpoint from a scheduled job/cron after the billing and M-Pesa callback workflows are enabled.</p>
</div>
<?php require_once '../includes/footer.php'; ?>