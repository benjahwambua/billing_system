<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();
requireTenant();
requirePermission('customers.view');

$pageTitle = 'Customers';

$tenantId = getCurrentTenantId();
$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$type = trim($_GET['customer_type'] ?? '');

$where = ['tenant_id = ?'];
$params = [$tenantId];
$types = 'i';

if ($search !== '') {
    $where[] = '(customer_number LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR phone LIKE ? OR email LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like, $like);
    $types .= 'sssss';
}

$allowedStatuses = ['active','suspended','inactive'];
if (in_array(strtolower($status), $allowedStatuses, true)) {
    $where[] = 'LOWER(status) = ?';
    $params[] = strtolower($status);
    $types .= 's';
}

if ($type !== '') {
    $where[] = 'customer_type = ?';
    $params[] = $type;
    $types .= 's';
}

$whereSql = implode(' AND ', $where);

function customerQuery($conn, $sql, $types, $params) {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [[], 'Database query failed.'];
    }
    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        return [[], $error];
    }
    $result = $stmt->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return [$rows, null];
}

[$customers, $queryError] = customerQuery(
    $conn,
    "SELECT id, customer_number, first_name, last_name, phone, email, customer_type, status, created_at
     FROM customers
     WHERE {$whereSql}
     ORDER BY id DESC
     LIMIT 250",
    $types,
    $params
);

$stats = ['total'=>0,'active'=>0,'suspended'=>0,'inactive'=>0];
$stmt = $conn->prepare("SELECT LOWER(status) status, COUNT(*) total FROM customers WHERE tenant_id = ? GROUP BY LOWER(status)");
if ($stmt) {
    $stmt->bind_param('i', $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $key = $row['status'];
        if (isset($stats[$key])) $stats[$key] = (int)$row['total'];
    }
    $stmt->close();
}
$stats['total'] = array_sum([$stats['active'],$stats['suspended'],$stats['inactive']]);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="content-header" style="display:flex;justify-content:space-between;align-items:center;gap:15px;margin-bottom:20px;">
    <div>
        <h2 style="margin:0;">Customer Management</h2>
        <p style="margin:5px 0 0;color:#6b7280;">Manage customers for your ISP.</p>
    </div>
    <a href="add.php" class="btn btn-primary">+ Add Customer</a>
</div>

<?php if ($queryError): ?>
    <div class="alert alert-danger"><?= e($queryError) ?></div>
<?php endif; ?>

<?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="alert alert-success"><?= e($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:15px;margin-bottom:20px;">
    <div class="card"><small>Total Customers</small><h2><?= number_format($stats['total']) ?></h2></div>
    <div class="card"><small>Active</small><h2><?= number_format($stats['active']) ?></h2></div>
    <div class="card"><small>Suspended</small><h2><?= number_format($stats['suspended']) ?></h2></div>
    <div class="card"><small>Inactive</small><h2><?= number_format($stats['inactive']) ?></h2></div>
</div>

<div class="card" style="margin-bottom:20px;">
    <form method="get" style="display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:10px;align-items:end;">
        <div>
            <label>Search</label>
            <input type="text" name="search" value="<?= e($search) ?>" placeholder="Number, name, phone or email">
        </div>
        <div>
            <label>Status</label>
            <select name="status">
                <option value="">All statuses</option>
                <?php foreach ($allowedStatuses as $item): ?>
                    <option value="<?= e($item) ?>" <?= strtolower($status) === $item ? 'selected' : '' ?>><?= ucfirst($item) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>Customer type</label>
            <input type="text" name="customer_type" value="<?= e($type) ?>" placeholder="e.g. Individual">
        </div>
        <button class="btn btn-secondary" type="submit">Filter</button>
    </form>
</div>

<div class="card">
    <div style="overflow-x:auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>Customer No.</th>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$customers): ?>
                <tr><td colspan="8" style="text-align:center;padding:30px;">No customers found.</td></tr>
            <?php else: ?>
                <?php foreach ($customers as $customer): ?>
                    <?php $name = trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '')); ?>
                    <tr>
                        <td><?= e($customer['customer_number']) ?></td>
                        <td><strong><?= e($name ?: 'Unnamed') ?></strong></td>
                        <td><?= e($customer['phone']) ?></td>
                        <td><?= e($customer['email']) ?></td>
                        <td><?= e($customer['customer_type']) ?></td>
                        <td><?= e(ucfirst($customer['status'])) ?></td>
                        <td><?= e(date('d M Y', strtotime($customer['created_at']))) ?></td>
                        <td style="white-space:nowrap;">
                            <a href="view.php?id=<?= (int)$customer['id'] ?>">View</a>
                            <?php if (userHasPermission('customers.edit')): ?>
                                | <a href="edit.php?id=<?= (int)$customer['id'] ?>">Edit</a>
                            <?php endif; ?>
                            <?php if (userHasPermission('customers.suspend')): ?>
                                | <a href="toggle_status.php?id=<?= (int)$customer['id'] ?>">Toggle</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
