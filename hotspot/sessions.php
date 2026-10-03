<?php
require_once '../includes/auth.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('hotspot','view');
require_once '../includes/billing_workflow.php';

global $conn;
$tenantId=(int)getCurrentTenantId();
$rows=[];
if(flexihubTableColumns('hotspot_sessions')){
    $q=$conn->prepare("SELECT hs.*,hp.name package_name,c.customer_number
                       FROM hotspot_sessions hs
                       LEFT JOIN hotspot_packages hp ON hp.id=hs.package_id AND hp.tenant_id=hs.tenant_id
                       LEFT JOIN customers c ON c.id=hs.customer_id AND c.tenant_id=hs.tenant_id
                       WHERE hs.tenant_id=? ORDER BY hs.id DESC LIMIT 200");
    if($q){$q->bind_param('i',$tenantId);$q->execute();$z=$q->get_result();while($r=$z->fetch_assoc())$rows[]=$r;$q->close();}
}else if(flexihubTableColumns('active_sessions')){
    $q=$conn->prepare("SELECT * FROM active_sessions WHERE tenant_id=? ORDER BY id DESC LIMIT 200");
    if($q){$q->bind_param('i',$tenantId);$q->execute();$z=$q->get_result();while($r=$z->fetch_assoc())$rows[]=$r;$q->close();}
}
$pageTitle='Hotspot Sessions';
require_once '../includes/header.php';
?>
<div class="dashboard-card">
    <div class="page-header"><div><h2>Hotspot Sessions</h2><p>Live and historical captive-Wi-Fi session records for this tenant.</p></div></div>
    <?php if(!$rows): ?><p>No hotspot sessions recorded yet.</p>
    <?php else: ?><div class="table-responsive"><table><thead><tr><th>Session</th><th>Customer</th><th>Package</th><th>Username</th><th>IP</th><th>Status</th><th>Started</th><th>Expires</th></tr></thead><tbody>
    <?php foreach($rows as $r): ?><tr>
        <td><?=e($r['session_identifier']??$r['id']??'')?></td>
        <td><?=e($r['customer_number']??'—')?></td>
        <td><?=e($r['package_name']??'—')?></td>
        <td><?=e($r['username']??'—')?></td>
        <td><?=e($r['ip_address']??'—')?></td>
        <td><?=e($r['status']??'')?></td>
        <td><?=e($r['started_at']??$r['created_at']??'')?></td>
        <td><?=e($r['expires_at']??'—')?></td>
    </tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</div>
<?php require_once '../includes/footer.php'; ?>