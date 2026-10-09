<?php
require_once '../includes/auth.php';

requireLogin();

$isTenant = isTenantUser();
$tenantId = $isTenant ? requireTenant() : null;

$pageTitle = 'Internet Plans';

function planColumns()
{
    global $conn;
    $columns = [];
    $result = $conn->query("SHOW COLUMNS FROM internet_plans");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
    }
    return $columns;
}

$columns = planColumns();

if (!$columns) {
    die('Internet plans table is not available.');
}

if ($isTenant && !in_array('tenant_id', $columns, true)) {
    http_response_code(503);
    exit('Internet plan tenant isolation is unavailable. Please contact the administrator.');
}

$search = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? '');

$where = [];
$params = [];
$types = '';

if ($isTenant && in_array('tenant_id', $columns, true)) {
    $where[] = 'tenant_id = ?';
    $params[] = $tenantId;
    $types .= 'i';
}

if ($search !== '' && in_array('name', $columns, true)) {
    $where[] = 'name LIKE ?';
    $params[] = '%' . $search . '%';
    $types .= 's';
}

if ($status !== '' && in_array('status', $columns, true)) {
    $where[] = 'status = ?';
    $params[] = $status;
    $types .= 's';
}

$sql = 'SELECT * FROM internet_plans';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY id DESC LIMIT 200';

$stmt = $conn->prepare($sql);
$plans = [];

if ($stmt) {
    if ($params) {
        $bind = [$types];
        foreach ($params as $key => $value) {
            $bind[] = &$params[$key];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind);
    }

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $plans[] = $row;
        }
    }
    $stmt->close();
}

require_once '../includes/header.php';
?>

<div class="page-toolbar" style="display:flex;justify-content:space-between;align-items:center;gap:15px;margin-bottom:20px;">
    <div>
        <p style="margin:0;">Manage the internet packages offered to your customers.</p>
    </div>
    <a href="add.php" class="btn btn-primary">+ Add Internet Plan</a>
</div>

<div class="dashboard-card" style="margin-bottom:20px;">
    <form method="get" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;">
        <?php if (in_array('name', $columns, true)): ?>
            <div>
                <label>Search</label>
                <input type="text" name="q" value="<?= e($search) ?>" placeholder="Plan name">
            </div>
        <?php endif; ?>

        <?php if (in_array('status', $columns, true)): ?>
            <div>
                <label>Status</label>
                <select name="status">
                    <option value="">All statuses</option>
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
        <?php endif; ?>

        <button type="submit" class="btn btn-secondary">Filter</button>
        <a href="index.php" class="btn btn-light">Reset</a>
    </form>
</div>

<div class="dashboard-card">
    <div style="overflow-x:auto;">
        <table class="data-table" style="width:100%;">
            <thead>
                <tr>
                    <th>Plan</th>
                    <?php if (in_array('price', $columns, true)): ?><th>Price</th><?php endif; ?>
                    <?php if (in_array('billing_cycle', $columns, true)): ?><th>Billing Cycle</th><?php endif; ?>
                    <?php if (in_array('billing_days', $columns, true)): ?><th>Days</th><?php endif; ?>
                    <?php if (in_array('download_speed', $columns, true)): ?><th>Download</th><?php endif; ?>
                    <?php if (in_array('upload_speed', $columns, true)): ?><th>Upload</th><?php endif; ?>
                    <?php if (in_array('status', $columns, true)): ?><th>Status</th><?php endif; ?>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$plans): ?>
                <tr><td colspan="8">No internet plans found.</td></tr>
            <?php else: ?>
                <?php foreach ($plans as $plan): ?>
                    <tr>
                        <td><strong><?= e($plan['name'] ?? 'Unnamed Plan') ?></strong></td>
                        <?php if (in_array('price', $columns, true)): ?><td><?= e(formatMoney($plan['price'] ?? 0)) ?></td><?php endif; ?>
                        <?php if (in_array('billing_cycle', $columns, true)): ?><td><?= e(ucfirst($plan['billing_cycle'] ?? '')) ?></td><?php endif; ?>
                        <?php if (in_array('billing_days', $columns, true)): ?><td><?= e($plan['billing_days'] ?? '') ?></td><?php endif; ?>
                        <?php if (in_array('download_speed', $columns, true)): ?><td><?= e($plan['download_speed'] ?? '') ?></td><?php endif; ?>
                        <?php if (in_array('upload_speed', $columns, true)): ?><td><?= e($plan['upload_speed'] ?? '') ?></td><?php endif; ?>
                        <?php if (in_array('status', $columns, true)): ?>
                            <td><span class="status-badge <?= strtolower($plan['status'] ?? '') === 'active' ? 'active' : 'inactive' ?>"><?= e(ucfirst($plan['status'] ?? '')) ?></span></td>
                        <?php endif; ?>
                        <td><a href="edit.php?id=<?= (int)$plan['id'] ?>" class="btn btn-sm">Edit</a></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

<style>
/* Flexihub sleek black/blue plans module */
.page-toolbar p{color:#94a3b8!important}.dashboard-card{background:linear-gradient(145deg,rgba(16,24,39,.96),rgba(11,17,27,.94))!important;border:1px solid rgba(148,163,184,.14)!important;color:#f8fafc;box-shadow:0 16px 40px rgba(0,0,0,.22)}.dashboard-card label{color:#cbd5e1}.dashboard-card input,.dashboard-card select{background:#0b111b!important;color:#f8fafc!important;border-color:rgba(148,163,184,.18)!important;border-radius:8px}.dashboard-card input:focus,.dashboard-card select:focus{border-color:#3b82f6!important;box-shadow:0 0 0 3px rgba(37,99,235,.14);outline:none}.data-table th{background:#0b111b!important;color:#cbd5e1;border-color:rgba(148,163,184,.14)!important}.data-table td{color:#e2e8f0;border-color:rgba(148,163,184,.14)!important}.data-table tbody tr:hover{background:rgba(37,99,235,.07)}.btn-primary,.btn{background:linear-gradient(135deg,#2563eb,#3b82f6)!important;color:#fff!important;border-color:transparent!important}.btn-secondary,.btn-light{background:#172235!important;color:#cbd5e1!important;border:1px solid rgba(148,163,184,.14)!important}.status-badge.active{background:rgba(34,197,94,.14)!important;color:#86efac!important}.status-badge.inactive{background:rgba(239,68,68,.14)!important;color:#fca5a5!important}
</style>