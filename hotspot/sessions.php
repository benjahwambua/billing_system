<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('hotspot','view');

global $conn;
$tenantId=(int)getCurrentTenantId();
$rows=[];
$stmt=$conn->prepare("
 SELECT s.*, h.reference, h.phone_number, p.name package_name
 FROM hotspot_sessions s
 JOIN hotspot_sales h ON h.id=s.sale_id AND h.tenant_id=s.tenant_id
 JOIN hotspot_packages p ON p.id=h.package_id AND p.tenant_id=h.tenant_id
 WHERE s.tenant_id=?
 ORDER BY s.id DESC LIMIT 200
");
if($stmt){
 $stmt->bind_param('i',$tenantId);
 $stmt->execute();
 $r=$stmt->get_result();
 while($row=$r->fetch_assoc())$rows[]=$row;
 $stmt->close();
}
$pageTitle='Hotspot Sessions';
require_once '../includes/header.php';
?>
<div class="dashboard-card">
 <div style="display:flex;justify-content:space-between;align-items:center;gap:15px;flex-wrap:wrap;">
  <div><h2>Hotspot Sessions</h2><p>Monitor paid hotspot sessions and their network authorization state.</p></div>
  <a class="btn btn-primary" href="mpesa.php">New M-Pesa Sale</a>
 </div>
 <?php if(!$rows): ?>
  <p style="margin-top:20px;">No hotspot sessions have been created yet.</p>
 <?php else: ?>
 <div class="table-responsive" style="margin-top:20px;"><table>
 <thead><tr><th>Session</th><th>Package</th><th>Phone</th><th>Status</th><th>Started</th><th>Expires</th><th>MAC</th></tr></thead>
 <tbody>
 <?php foreach($rows as $row): ?>
 <tr>
  <td>#<?=e($row['id'])?></td>
  <td><?=e($row['package_name'])?></td>
  <td><?=e($row['phone_number']??'—')?></td>
  <td><?=e(ucwords(str_replace('_',' ',$row['status'])))?></td>
  <td><?=e($row['started_at']??'—')?></td>
  <td><?=e($row['expires_at']??'—')?></td>
  <td><?=e($row['mac_address']??'—')?></td>
 </tr>
 <?php endforeach; ?>
 </tbody></table></div>
 <?php endif; ?>
</div>
<?php require_once '../includes/footer.php'; ?>