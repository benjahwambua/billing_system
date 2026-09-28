<?php
require_once '../includes/auth.php';requireActiveUser();requireTenantContext();requireModulePermission('staff','view');
$tenantId=(int)getCurrentTenantId();$total=0;$active=0;$users=0;
$q=$conn->query("SHOW TABLES LIKE 'staffs');");
if($q&&$q->num_rows){$st=$conn->prepare("SELECT COUNT(*) total,COALESCE(SUM(LOWER(status)='active'),0) active FROM staffs WHERE tenant_id=?");if($st){$st->bind_param('i',$tenantId);$st->execute();$x=$st->get_result()->fetch_assoc();$total=(int)($x['total']??0);$active=(int)($x['active']??0);$st->close();}}
$q=$conn->query("SHOW TABLES LIKE 'users'");
if($q&&$q->num_rows){$st=$conn->prepare("SELECT COUNT(*) total FROM users WHERE tenant_id=?");if($st){$st->bind_param('i',$tenantId);$st->execute();$x=$st->get_result()->fetch_assoc();$users=(int)($x['total']??0);$st->close();}}
$pageTitle='Staffs';require_once '../includes/header.php';?>
<div class="dashboard-card"><h2>Staff Management</h2><p style="color:#6b7280">Manage tenant staff and system access.</p><div class="stats-grid"><div class="stat-card"><h3><?=e($total)?></h3><p>Total Staff</p></div><div class="stat-card"><h3><?=e($active)?></h3><p>Active Staff</p></div><div class="stat-card"><h3><?=e($users)?></h3><p>System Users</p></div></div><div style="margin-top:18px;display:flex;gap:10px;flex-wrap:wrap"><?php if(userCan('staff','create')):?><a class="btn" href="add.php">Add Staff</a><?php endif;?><a class="btn" href="../users/index.php">Users & Access</a><a class="btn" href="../roles/index.php">Roles & Permissions</a></div></div><?php require_once '../includes/footer.php';?>