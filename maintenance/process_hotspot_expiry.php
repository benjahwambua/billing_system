<?php
require_once '../includes/auth.php';
requireActiveUser();
requireTenantContext();
require_once '../includes/hotspot_workflow.php';
requireModulePermission('hotspot','view');

$tenantId=(int)getCurrentTenantId();
$result=flexihubProcessHotspotExpiry($tenantId,100);
$pageTitle='Hotspot Expiry Processor';
require_once '../includes/header.php';
?>
<div class="dashboard-card">
 <div class="page-header"><div><h2>Hotspot Expiry Processor</h2><p>Disconnects expired HotSpot users and marks their sessions expired.</p></div></div>
 <div class="form-grid" style="margin-top:20px">
  <div class="card"><strong>Processed</strong><div style="font-size:28px"><?=e($result['processed'])?></div></div>
  <div class="card"><strong>Expired</strong><div style="font-size:28px"><?=e($result['expired'])?></div></div>
  <div class="card"><strong>Failed</strong><div style="font-size:28px"><?=e($result['failed'])?></div></div>
 </div>
 <p style="margin-top:20px">Run this endpoint from a scheduled job/cron in production. The process is tenant-scoped and safe to run repeatedly.</p>
</div>
<?php require_once '../includes/footer.php'; ?>