<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$tenantId = isTenantUser() ? requireTenant() : null;
$pageTitle = 'Customers';

function customerColumnExists($column) {
    global $conn;
    static $columns = null;
    if ($columns === null) {
        $columns = [];
        $r = $conn->query("SHOW COLUMNS FROM customers");
        if ($r) while ($row = $r->fetch_assoc()) $columns[$row['Field']] = true;
    }
    return isset($columns[$column]);
}

$where = [];
$params = [];
$types = '';
if ($tenantId) { $where[] = 'tenant_id = ?'; $params[] = $tenantId; $types .= 'i'; }

$statusFilter = strtolower(trim($GLOBALS['status'] ?? ($_GET['status'] ?? '')));
if (in_array($statusFilter, ['active','suspended','expired','inactive'], true)) { $where[] = 'status = ?'; $params[] = $statusFilter; $types .= 's'; }

$search = trim($_GET['q'] ?? '');
if ($search !== '') {
    $parts = [];
    foreach (['customer_number','first_name','last_name','phone','email'] as $c) {
        if (customerColumnExists($c)) $parts[] = "$c LIKE ?";
    }
    if ($parts) {
        $where[] = '(' . implode(' OR ', $parts) . ')';
        foreach ($parts as $_) { $params[] = '%' . $search . '%'; $types .= 's'; }
    }
}

$sql = "SELECT * FROM customers";
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY id DESC LIMIT 100';

$stmt = $conn->prepare($sql);
if ($stmt && $params) $stmt->bind_param($types, ...$params);
$customers = [];
if ($stmt && $stmt->execute()) {
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) $customers[] = $row;
}
if ($stmt) $stmt->close();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="main-content">
    <div class="page-header">
        <div><h1>Customers</h1><p>Manage subscribers and their internet service accounts.</p></div>
        <a href="add.php" class="btn btn-primary">+ Add Customer</a>
    </div>

    <div class="card" style="margin-bottom:20px;">
        <form method="get" style="display:flex;gap:10px;padding:16px;">
            <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search customer number, name, phone or email" style="flex:1;">
            <button class="btn btn-primary" type="submit">Search</button>
            <?php if ($search !== ''): ?><a class="btn btn-secondary" href="index.php">Clear</a><?php endif; ?>
        </form>
    </div>

    <div class="card">
        <div class="card-header"><h2>Customer List</h2><p><?= count($customers) ?> customer(s) shown</p></div>
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>Customer</th><th>Contact</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
                <tbody>
                <?php if (!$customers): ?>
                    <tr><td colspan="5" class="empty-state">No customers found.</td></tr>
                <?php else: foreach ($customers as $c):
                    $name = trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''));
                    if ($name === '') $name = $c['customer_number'] ?? ('Customer #' . ($c['id'] ?? ''));
                    $status = strtolower((string)($c['status'] ?? 'active'));
                ?>
                    <tr>
                        <td><strong><?= e($name) ?></strong><small><?= e($c['customer_number'] ?? '') ?></small></td>
                        <td><?= e($c['phone'] ?? '—') ?><small><?= e($c['email'] ?? '') ?></small></td>
                        <td><span class="status-badge status-<?= e(in_array($status,['active','suspended','expired','inactive'],true) ? $status : 'default') ?>"><?= e(ucfirst($status)) ?></span></td>
                        <td><?= !empty($c['created_at']) ? e(date('d M Y', strtotime($c['created_at']))) : '—' ?></td>
                        <td><a class="btn btn-sm btn-secondary" href="view.php?id=<?= (int)$c['id'] ?>">View</a></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<style>
.main-content{padding:24px}.page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px}.page-header h1{margin:0 0 5px}.page-header p{margin:0;color:#6b7280}.card{background:#fff;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden}.card-header{padding:18px 20px;border-bottom:1px solid #e5e7eb}.card-header h2{margin:0 0 4px}.card-header p{margin:0;color:#6b7280;font-size:13px}.table-responsive{overflow-x:auto}.data-table{width:100%;border-collapse:collapse}.data-table th,.data-table td{padding:13px 16px;border-bottom:1px solid #e5e7eb;text-align:left}.data-table th{background:#f9fafb;font-size:13px}.data-table td small{display:block;color:#6b7280;margin-top:3px}.form-control{padding:10px 12px;border:1px solid #d1d5db;border-radius:6px}.btn{display:inline-block;padding:9px 14px;border-radius:6px;border:1px solid transparent;text-decoration:none;font-size:13px;font-weight:600;cursor:pointer}.btn-sm{padding:6px 10px;font-size:12px}.btn-primary{background:#111827;color:#fff}.btn-secondary{background:#f3f4f6;color:#111827}.status-badge{padding:5px 9px;border-radius:999px;font-size:12px;font-weight:600}.status-active{background:#dcfce7;color:#166534}.status-suspended,.status-inactive{background:#fee2e2;color:#991b1b}.status-expired{background:#fef3c7;color:#92400e}.status-default{background:#f3f4f6;color:#374151}.empty-state{text-align:center!important;padding:40px!important;color:#6b7280}@media(max-width:700px){.main-content{padding:15px}.page-header{flex-direction:column;align-items:flex-start;gap:12px}}
</style>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<style>
/* Flexihub sleek black/blue customer module */
.main-content{background:transparent;color:var(--fh-text,#f8fafc)}.page-header h1{color:#f8fafc;letter-spacing:-.02em}.page-header p{color:#94a3b8!important}.card{background:linear-gradient(145deg,rgba(16,24,39,.96),rgba(11,17,27,.94))!important;border-color:rgba(148,163,184,.14)!important;box-shadow:0 16px 40px rgba(0,0,0,.22)}.card-header{border-color:rgba(148,163,184,.14)!important}.card-header h2{color:#f8fafc}.card-header p{color:#94a3b8!important}.form-control{background:#0b111b!important;color:#f8fafc!important;border-color:rgba(148,163,184,.18)!important}.form-control::placeholder{color:#64748b}.form-control:focus{border-color:#3b82f6!important;box-shadow:0 0 0 3px rgba(37,99,235,.14);outline:none}.data-table th{background:#0b111b!important;color:#cbd5e1;border-color:rgba(148,163,184,.14)!important}.data-table td{color:#e2e8f0;border-color:rgba(148,163,184,.14)!important}.data-table td small{color:#94a3b8!important}.data-table tbody tr:hover{background:rgba(37,99,235,.07)}.btn-primary{background:linear-gradient(135deg,#2563eb,#3b82f6)!important;color:#fff!important}.btn-secondary{background:#172235!important;color:#cbd5e1!important;border-color:rgba(148,163,184,.14)!important}.empty-state{color:#64748b!important}
</style>