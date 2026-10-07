<?php
require_once '../includes/auth.php'; requireLogin(); requireHostContext();
$pageTitle='Platform Users';
$users=[];
$sql="SELECT u.id,u.username,u.role,u.status,u.user_scope,u.tenant_id,u.last_login,u.last_activity_at,t.name AS tenant_name
      FROM users u LEFT JOIN tenants t ON t.id=u.tenant_id
      ORDER BY u.id DESC LIMIT 500";
$r=$conn->query($sql); if($r) while($row=$r->fetch_assoc()) $users[]=$row;
require_once '../includes/header.php'; require_once '../includes/sidebar.php';
?>
<div class="main-content"><div class="page-header"><div><h1>Platform Users</h1><p>Host-level visibility of users across the Flexihub installation.</p></div></div>
<div class="card"><div class="table-responsive"><table class="data-table"><thead><tr><th>User</th><th>Role</th><th>Scope</th><th>Tenant</th><th>Status</th><th>Last Login</th><th>Activity</th></tr></thead><tbody>
<?php if(!$users): ?><tr><td colspan="7" class="empty">No users found.</td></tr><?php else: foreach($users as $u): ?>
<tr><td><strong><?=e($u['username'])?></strong><small>#<?=e($u['id'])?></small></td><td><?=e($u['role'])?></td><td><?=e(ucfirst($u['user_scope']??''))?></td><td><?=e($u['tenant_name']??($u['user_scope']==='host'?'Platform':'—'))?></td><td><span class="status"><?=e(ucfirst($u['status']))?></span></td><td><?=e($u['last_login']?:'—')?></td><td><?=e($u['last_activity_at']?:'—')?></td></tr>
<?php endforeach; endif; ?></tbody></table></div></div></div>
<style>.main-content{padding:24px}.page-header{margin-bottom:24px}.page-header h1{margin:0 0 6px}.page-header p{margin:0;color:#6b7280}.card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden}.table-responsive{overflow:auto}.data-table{width:100%;border-collapse:collapse;min-width:900px}.data-table th,.data-table td{padding:13px 15px;border-bottom:1px solid #e5e7eb;text-align:left}.data-table th{background:#f9fafb;font-size:12px;color:#4b5563}.data-table small{display:block;color:#6b7280;margin-top:3px}.status{display:inline-block;padding:4px 8px;border-radius:999px;background:#f3f4f6;font-size:12px}.empty{text-align:center;padding:40px;color:#6b7280}</style>
<?php require_once '../includes/footer.php'; ?>