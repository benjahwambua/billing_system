<?php
require_once __DIR__ . '/../includes/auth.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('customers', 'view');

$tenantId = (int)getCurrentTenantId();
if ($tenantId <= 0) { http_response_code(403); exit('A valid tenant context is required.'); }

$groupsTable = $conn->query("SHOW TABLES LIKE 'customer_groups'");
$hasGroupsTable = $groupsTable && $groupsTable->num_rows > 0;
$hasCustomerGroup = flexihubTableHasColumn('customers', 'customer_group_id');
$errors = [];
$success = '';

if ($hasGroupsTable && $hasCustomerGroup && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = trim((string)($_POST['action'] ?? ''));
    $name = trim((string)($_POST['name'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $status = strtolower(trim((string)($_POST['status'] ?? 'active')));

    if (!in_array($action, ['create', 'update'], true)) $errors[] = 'Invalid customer group action.';
    if ($name === '' || mb_strlen($name) > 120) $errors[] = 'Enter a group name of up to 120 characters.';
    if (mb_strlen($description) > 500) $errors[] = 'Group description must not exceed 500 characters.';
    if (!in_array($status, ['active', 'archived'], true)) $errors[] = 'Select a valid group status.';

    if (!$errors && $action === 'create') {
        requireModulePermission('customers', 'create');
        $stmt = $conn->prepare("INSERT INTO customer_groups (tenant_id, name, description, status) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param('isss', $tenantId, $name, $description, $status);
            if ($stmt->execute()) $success = 'Customer group created.';
            elseif ($stmt->errno === 1062) $errors[] = 'A group with this name already exists.';
            else $errors[] = 'Unable to create customer group.';
            $stmt->close();
        } else $errors[] = 'Unable to prepare customer group creation.';
    }

    if (!$errors && $action === 'update') {
        requireModulePermission('customers', 'edit');
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $conn->prepare("UPDATE customer_groups SET name=?, description=?, status=? WHERE id=? AND tenant_id=?");
        if ($stmt) {
            $stmt->bind_param('sssii', $name, $description, $status, $id, $tenantId);
            if ($stmt->execute()) $success = $stmt->affected_rows ? 'Customer group updated.' : 'No group details changed.';
            elseif ($stmt->errno === 1062) $errors[] = 'A group with this name already exists.';
            else $errors[] = 'Unable to update customer group.';
            $stmt->close();
        } else $errors[] = 'Unable to prepare customer group update.';
    }
}

$rows = [];
if ($hasGroupsTable && $hasCustomerGroup) {
    $stmt = $conn->prepare("SELECT g.*, COUNT(c.id) AS customer_count
        FROM customer_groups g
        LEFT JOIN customers c ON c.customer_group_id=g.id AND c.tenant_id=g.tenant_id
        WHERE g.tenant_id=?
        GROUP BY g.id
        ORDER BY g.status ASC, g.name ASC");
    if ($stmt) {
        $stmt->bind_param('i', $tenantId);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) $rows[] = $row;
        }
        $stmt->close();
    }
}

$pageTitle = 'Customer Groups';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="main-content customer-groups-page">
    <div class="groups-hero">
        <div><span class="groups-kicker">CUSTOMER SEGMENTATION</span><h1>Customer Groups</h1><p>Organise subscribers for targeted service management and communication.</p></div>
        <a class="btn btn-secondary" href="index.php">Back to Customers</a>
    </div>
    <?php if ($errors): ?><div class="groups-alert error"><?php foreach ($errors as $error): ?><div><?=e($error)?></div><?php endforeach; ?></div><?php endif; ?>
    <?php if ($success): ?><div class="groups-alert success"><?=e($success)?></div><?php endif; ?>
    <?php if (!$hasGroupsTable || !$hasCustomerGroup): ?>
        <section class="groups-panel"><h2>Customer groups require a database update</h2><p>The tenant-scoped customer group migration is included as <code>database/037_customer_groups.sql</code>. It has not been run automatically. Apply it during the planned database verification phase before enabling group assignment.</p></section>
    <?php else: ?>
        <?php if (userCan('customers', 'create')): ?>
        <section class="groups-panel"><h2>Create Group</h2><form method="post" class="group-form"><?=csrfField()?><input type="hidden" name="action" value="create"><label>Group Name<input name="name" maxlength="120" required></label><label>Description<input name="description" maxlength="500"></label><button class="btn btn-primary">Create Group</button></form></section>
        <?php endif; ?>
        <section class="groups-panel"><div class="groups-heading"><div><h2>Groups</h2><p><?=count($rows)?> group(s) in this tenant</p></div></div>
            <div class="groups-list">
            <?php if (!$rows): ?><div class="groups-empty">No customer groups have been created yet.</div><?php else: foreach ($rows as $group): ?>
                <form method="post" class="group-row"><?=csrfField()?><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?=(int)$group['id']?>"><div class="group-fields"><label>Group Name<input name="name" maxlength="120" value="<?=e($group['name'])?>" required></label><label>Description<input name="description" maxlength="500" value="<?=e($group['description'] ?? '')?>"></label><label>Status<select name="status"><option value="active" <?=$group['status']==='active'?'selected':''?>>Active</option><option value="archived" <?=$group['status']==='archived'?'selected':''?>>Archived</option></select></label></div><div class="group-meta"><span><?=number_format((int)$group['customer_count'])?> customers</span><?php if (userCan('customers', 'edit')): ?><button class="btn btn-secondary">Save</button><?php endif; ?></div></form>
            <?php endforeach; endif; ?>
            </div>
        </section>
    <?php endif; ?>
</div>
<style>
.customer-groups-page{padding:26px;color:#e2e8f0}.groups-hero{display:flex;justify-content:space-between;align-items:center;gap:18px;padding:25px 28px;margin-bottom:18px;border:1px solid rgba(59,130,246,.18);border-radius:16px;background:radial-gradient(circle at 90% 10%,rgba(37,99,235,.2),transparent 36%),linear-gradient(135deg,#101827,#0a101a)}.groups-kicker{color:#60a5fa;font-size:11px;font-weight:800;letter-spacing:.14em}.groups-hero h1{margin:7px 0 4px;color:#f8fafc}.groups-hero p,.groups-heading p{margin:0;color:#94a3b8}.groups-panel{margin-bottom:18px;padding:21px;border:1px solid rgba(148,163,184,.14);border-radius:15px;background:linear-gradient(145deg,#101827,#0b111b)}.groups-panel h2{margin:0 0 14px;color:#f8fafc;font-size:17px}.group-form,.group-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.group-form label,.group-fields label{display:grid;gap:6px;color:#cbd5e1;font-size:12px;font-weight:700}.group-form input,.group-fields input,.group-fields select{width:100%;box-sizing:border-box;padding:10px 11px;border:1px solid rgba(148,163,184,.18);border-radius:8px;background:#0b111b;color:#f8fafc}.group-form button{align-self:end;justify-self:start}.group-row{padding:15px 0;border-bottom:1px solid rgba(148,163,184,.12)}.group-row:last-child{border-bottom:0}.group-meta{display:flex;justify-content:space-between;align-items:center;margin-top:10px;color:#94a3b8;font-size:12px}.groups-alert{padding:12px 14px;border-radius:9px;margin-bottom:16px}.groups-alert.error{background:rgba(239,68,68,.13);color:#fca5a5}.groups-alert.success{background:rgba(34,197,94,.13);color:#86efac}.groups-empty{padding:25px;text-align:center;color:#94a3b8}.customer-groups-page .btn{padding:9px 14px;border-radius:8px;border:1px solid rgba(148,163,184,.16);font-weight:700}.customer-groups-page .btn-primary{background:#2563eb;color:white}.customer-groups-page .btn-secondary{background:#172235;color:#cbd5e1}@media(max-width:760px){.customer-groups-page{padding:15px}.groups-hero{align-items:flex-start;flex-direction:column}.group-form,.group-fields{grid-template-columns:1fr}}
</style>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
