<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();
requireTenant();
requirePermission('plans.view');

$pageTitle = 'Internet Plans';
$tenantId = getCurrentTenantId();
$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');

$where = ['tenant_id = ?'];
$params = [$tenantId];
$types = 'i';

if ($search !== '') {
    $where[] = '(plan_code LIKE ? OR name LIKE ? OR mikrotik_profile LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
    $types .= 'sss';
}
if (in_array(strtolower($status), ['active','inactive'], true)) {
    $where[] = 'LOWER(status) = ?';
    $params[] = strtolower($status);
    $types .= 's';
}

$sql = "SELECT id, plan_code, name, download_speed, upload_speed, unit, price, billing_cycle, billing_days, mikrotik_profile, data_limit, status, created_at
        FROM internet_plans WHERE " . implode(' AND ', $where) . " ORDER BY id DESC LIMIT 250";
$stmt = $conn->prepare($sql);
$plans = [];
$error = null;
if ($stmt) {
    $stmt->bind_param($types, ...$params);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $plans = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    } else $error = $stmt->error;
    $stmt->close();
} else $error = $conn->error;

$stats = ['total'=>0,'active'=>0,'inactive'=>0];
$stmt = $conn->prepare("SELECT LOWER(status) status, COUNT(*) total FROM internet_plans WHERE tenant_id=? GROUP BY LOWER(status)");
if ($stmt) {
    $stmt->bind_param('i',$tenantId); $stmt->execute(); $result=$stmt->get_result();
    while($row=$result->fetch_assoc()){ if(isset($stats[$row['status']])) $stats[$row['status']]=(int)$row['total']; }
    $stmt->close();
}
$stats['total']=$stats['active']+$stats['inactive'];

require_once __DIR__ . '/../includes/header.php';
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
  <div><h2 style="margin:0;">Internet Plans</h2><p style="margin:5px 0;color:#6b7280;">Manage packages offered to ISP customers and network services.</p></div>
  <?php if(userHasPermission('plans.create')): ?><a href="add.php" class="btn btn-primary">+ Add Plan</a><?php endif; ?>
</div>
<?php if($error): ?><div class="alert alert-danger"><?=e($error)?></div><?php endif; ?>
<?php if(!empty($_SESSION['flash_success'])): ?><div class="alert alert-success"><?=e($_SESSION['flash_success']);unset($_SESSION['flash_success']);?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:15px;margin-bottom:20px;">
 <div class="card"><small>Total Plans</small><h2><?=number_format($stats['total'])?></h2></div>
 <div class="card"><small>Active</small><h2><?=number_format($stats['active'])?></h2></div>
 <div class="card"><small>Inactive</small><h2><?=number_format($stats['inactive'])?></h2></div>
</div>

<div class="card" style="margin-bottom:20px;">
<form method="get" style="display:grid;grid-template-columns:2fr 1fr auto;gap:10px;align-items:end;">
 <div><label>Search</label><input name="search" value="<?=e($search)?>" placeholder="Plan code, name or MikroTik profile"></div>
 <div><label>Status</label><select name="status"><option value="">All</option><option value="active" <?=strtolower($status)==='active'?'selected':''?>>Active</option><option value="inactive" <?=strtolower($status)==='inactive'?'selected':''?>>Inactive</option></select></div>
 <button class="btn btn-secondary" type="submit">Filter</button>
</form>
</div>

<div class="card"><div style="overflow-x:auto;"><table class="table">
<thead><tr><th>Code</th><th>Plan</th><th>Speed</th><th>Price</th><th>Cycle</th><th>Data</th><th>Status</th><th>Actions</th></tr></thead>
<tbody>
<?php if(!$plans): ?><tr><td colspan="8" style="text-align:center;padding:30px;">No internet plans found.</td></tr>
<?php else: foreach($plans as $plan): ?>
<tr>
<td><?=e($plan['plan_code'])?></td><td><strong><?=e($plan['name'])?></strong></td>
<td><?=e($plan['download_speed'])?> / <?=e($plan['upload_speed'])?> <?=e($plan['unit'])?></td>
<td>KES <?=number_format((float)$plan['price'],2)?></td>
<td><?=e($plan['billing_cycle'])?><?=!empty($plan['billing_days'])?' ('.(int)$plan['billing_days'].' days)':''?></td>
<td><?=e($plan['data_limit'] ?: 'Unlimited')?></td>
<td><?=e(ucfirst($plan['status']))?></td>
<td><a href="view.php?id=<?=(int)$plan['id']?>">View</a><?php if(userHasPermission('plans.edit')): ?> | <a href="edit.php?id=<?=(int)$plan['id']?>">Edit</a><?php endif; ?></td>
</tr>
<?php endforeach; endif; ?>
</tbody></table></div></div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>