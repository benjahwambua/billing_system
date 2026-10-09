<?php
require_once __DIR__ . '/../includes/auth.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('customers', 'view');
$tenantId = (int)getCurrentTenantId();
if ($tenantId <= 0) { http_response_code(403); exit('A valid tenant context is required.'); }
$pageTitle = 'Customers';
if (!customerColumnExists('tenant_id')) { http_response_code(503); exit('Customer tenant isolation is unavailable. Please contact the administrator.'); }

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

function customerTableExists($table) {
    global $conn;
    static $cache = [];
    if (!isset($cache[$table])) {
        $safe = $conn->real_escape_string($table);
        $q = $conn->query("SHOW TABLES LIKE '{$safe}'");
        $cache[$table] = $q && $q->num_rows > 0;
    }
    return $cache[$table];
}

function customerCount($table, $conditions = [], $params = [], $types = '') {
    global $conn;
    if (!customerTableExists($table)) return 0;
    $sql = "SELECT COUNT(*) AS total FROM {$table}";
    if ($conditions) $sql .= ' WHERE ' . implode(' AND ', $conditions);
    $stmt = $conn->prepare($sql);
    if (!$stmt) return 0;
    if ($params) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['total'] ?? 0);
}

$tenantClause = ['tenant_id = ?', [$tenantId], 'i'];

$totalCustomers = customerCount('customers', $tenantClause[0], $tenantClause[1], $tenantClause[2]);
$activeCustomers = customerCount('customers', array_merge($tenantClause[0], ['status = ?']), array_merge($tenantClause[1], ['active']), $tenantClause[2] . 's');
$suspendedCustomers = customerCount('customers', array_merge($tenantClause[0], ['status = ?']), array_merge($tenantClause[1], ['suspended']), $tenantClause[2] . 's');
$expiredCustomers = customerCount('customers', array_merge($tenantClause[0], ['status = ?']), array_merge($tenantClause[1], ['expired']), $tenantClause[2] . 's');

$accountTotal = 0;
$accountActive = 0;
$accountExpired = 0;
$accountSuspended = 0;
if (customerTableExists('internet_accounts')) {
    $accountTenant = $tenantId ? ['tenant_id = ?', [$tenantId], 'i'] : [[], [], ''];
    $accountTotal = customerCount('internet_accounts', $accountTenant[0], $accountTenant[1], $accountTenant[2]);
    $accountActive = customerCount('internet_accounts', array_merge($accountTenant[0], ['status = ?']), array_merge($accountTenant[1], ['active']), $accountTenant[2] . 's');
    $accountExpired = customerCount('internet_accounts', array_merge($accountTenant[0], ['status = ?']), array_merge($accountTenant[1], ['expired']), $accountTenant[2] . 's');
    $accountSuspended = customerCount('internet_accounts', array_merge($accountTenant[0], ['status = ?']), array_merge($accountTenant[1], ['suspended']), $accountTenant[2] . 's');
}

$where = [];
$params = [];
$types = '';
$where[] = 'tenant_id = ?';
$params[] = $tenantId;
$types .= 'i';

$statusFilter = strtolower(trim($_GET['status'] ?? ''));
if (in_array($statusFilter, ['active','suspended','expired','inactive'], true)) {
    $where[] = 'status = ?';
    $params[] = $statusFilter;
    $types .= 's';
}

$search = trim($_GET['q'] ?? '');
if ($search !== '') {
    $parts = [];
    foreach (['customer_number','first_name','last_name','phone','email'] as $c) {
        if (customerColumnExists($c)) $parts[] = "$c LIKE ?";
    }
    if ($parts) {
        $where[] = '(' . implode(' OR ', $parts) . ')';
        foreach ($parts as $_) {
            $params[] = '%' . $search . '%';
            $types .= 's';
        }
    }
}

$sql = "SELECT * FROM customers";
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY id DESC LIMIT 100';

$stmt = $conn->prepare($sql);
$customers = [];
if ($stmt) {
    if ($params) $stmt->bind_param($types, ...$params);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) $customers[] = $row;
    }
    $stmt->close();
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="main-content customer-command-center">
    <section class="customer-hero">
        <div>
            <span class="command-kicker">ISP CUSTOMER OPERATIONS</span>
            <h1>Customers</h1>
            <p>Manage subscribers, service status and customer accounts from one workspace.</p>
        </div>
        <div class="hero-actions">
            <a href="add.php" class="btn btn-primary">+ Add Customer</a>
            <a href="../reports/customers.php" class="btn btn-secondary">Customer Report</a>
        </div>
    </section>

    <section class="customer-metrics">
        <a class="metric-card" href="index.php"><span>Total Customers</span><strong><?= $totalCustomers ?></strong><small>All registered subscribers</small></a>
        <a class="metric-card metric-success" href="index.php?status=active"><span>Active</span><strong><?= $activeCustomers ?></strong><small>Customers in good standing</small></a>
        <a class="metric-card metric-warning" href="index.php?status=expired"><span>Expired</span><strong><?= $expiredCustomers ?></strong><small>Require renewal attention</small></a>
        <a class="metric-card metric-danger" href="index.php?status=suspended"><span>Suspended</span><strong><?= $suspendedCustomers ?></strong><small>Require intervention</small></a>
    </section>

    <section class="customer-workspace">
        <div class="customer-main-panel">
            <div class="panel-heading">
                <div><span class="panel-kicker">SUBSCRIBER DIRECTORY</span><h2>Customer List</h2><p><?= count($customers) ?> customer(s) shown</p></div>
            </div>
            <form method="get" class="customer-search">
                <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search customer number, name, phone or email">
                <select class="form-control" name="status">
                    <option value="">All statuses</option>
                    <?php foreach (['active','suspended','expired','inactive'] as $option): ?>
                        <option value="<?= $option ?>" <?= $statusFilter === $option ? 'selected' : '' ?>><?= ucfirst($option) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-primary" type="submit">Search</button>
                <?php if ($search !== '' || $statusFilter !== ''): ?><a class="btn btn-secondary" href="index.php">Clear</a><?php endif; ?>
            </form>
            <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>Customer</th><th>Contact</th><th>Status</th><th>Created</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php if (!$customers): ?>
                        <tr><td colspan="5" class="empty-state">No customers found for the selected filters.</td></tr>
                    <?php else: foreach ($customers as $c):
                        $name = trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''));
                        if ($name === '') $name = $c['customer_number'] ?? ('Customer #' . ($c['id'] ?? ''));
                        $status = strtolower((string)($c['status'] ?? 'active'));
                        $safeStatus = in_array($status, ['active','suspended','expired','inactive'], true) ? $status : 'default';
                    ?>
                        <tr>
                            <td><strong class="customer-name"><?= e($name) ?></strong><small><?= e($c['customer_number'] ?? '') ?></small></td>
                            <td><?= e($c['phone'] ?? '—') ?><small><?= e($c['email'] ?? '') ?></small></td>
                            <td><span class="status-badge status-<?= e($safeStatus) ?>"><?= e(ucfirst($status)) ?></span></td>
                            <td><?= !empty($c['created_at']) ? e(date('d M Y', strtotime($c['created_at']))) : '—' ?></td>
                            <td><a class="btn btn-sm btn-secondary" href="view.php?id=<?= (int)$c['id'] ?>">View Customer</a></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <aside class="customer-side-panel">
            <div class="side-card">
                <span class="panel-kicker">SERVICE PORTFOLIO</span>
                <h3>Internet Accounts</h3>
                <div class="side-stat"><span>Total accounts</span><strong><?= $accountTotal ?></strong></div>
                <div class="side-stat"><span>Active</span><strong><?= $accountActive ?></strong></div>
                <div class="side-stat"><span>Expired</span><strong><?= $accountExpired ?></strong></div>
                <div class="side-stat"><span>Suspended</span><strong><?= $accountSuspended ?></strong></div>
            </div>
            <div class="side-card quick-actions">
                <span class="panel-kicker">QUICK ACTIONS</span>
                <h3>Customer workflow</h3>
                <a href="add.php">Create customer <span>→</span></a>
                <a href="../internet_accounts/index.php">Manage service accounts <span>→</span></a>
                <a href="../pppoe_accounts/index.php">PPPoE accounts <span>→</span></a>
                <a href="../hotspot/index.php">Hotspot services <span>→</span></a>
                <a href="../payments/index.php">View payments <span>→</span></a>
            </div>
        </aside>
    </section>
</div>

<style>
.customer-command-center{padding:26px;min-height:calc(100vh - 70px);color:#f8fafc}
.customer-hero{display:flex;justify-content:space-between;align-items:center;gap:24px;padding:28px 30px;margin-bottom:20px;border:1px solid rgba(59,130,246,.18);border-radius:18px;background:radial-gradient(circle at 88% 10%,rgba(37,99,235,.22),transparent 34%),linear-gradient(135deg,#101827,#0a101a);box-shadow:0 18px 48px rgba(0,0,0,.25)}
.command-kicker,.panel-kicker{font-size:11px;font-weight:800;letter-spacing:.14em;color:#60a5fa}
.customer-hero h1{margin:7px 0 5px;font-size:32px;letter-spacing:-.035em}
.customer-hero p,.panel-heading p{margin:0;color:#94a3b8}
.hero-actions{display:flex;gap:10px;flex-wrap:wrap}
.customer-metrics{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px}
.metric-card{display:block;padding:18px 19px;border:1px solid rgba(148,163,184,.13);border-radius:14px;background:linear-gradient(145deg,rgba(16,24,39,.96),rgba(11,17,27,.94));box-shadow:0 12px 30px rgba(0,0,0,.18);text-decoration:none;color:#f8fafc;transition:.18s ease}
.metric-card:hover{transform:translateY(-2px);border-color:rgba(59,130,246,.4)}
.metric-card span,.metric-card small{display:block;color:#94a3b8}.metric-card strong{display:block;font-size:27px;margin:6px 0 2px}.metric-success strong{color:#4ade80}.metric-warning strong{color:#fbbf24}.metric-danger strong{color:#fb7185}
.customer-workspace{display:grid;grid-template-columns:minmax(0,1fr) 290px;gap:20px}
.customer-main-panel,.side-card{background:linear-gradient(145deg,rgba(16,24,39,.96),rgba(11,17,27,.94));border:1px solid rgba(148,163,184,.13);border-radius:16px;box-shadow:0 14px 36px rgba(0,0,0,.2)}
.panel-heading{padding:20px 21px;border-bottom:1px solid rgba(148,163,184,.12)}.panel-heading h2{margin:4px 0;color:#f8fafc}
.customer-search{display:grid;grid-template-columns:minmax(0,1fr) 150px auto auto;gap:9px;padding:15px;border-bottom:1px solid rgba(148,163,184,.12)}
.form-control{width:100%;box-sizing:border-box;padding:10px 12px;border-radius:9px;border:1px solid rgba(148,163,184,.18);background:#0b111b;color:#f8fafc}.form-control:focus{outline:none;border-color:#3b82f6;box-shadow:0 0 0 3px rgba(37,99,235,.14)}
.table-responsive{overflow:auto}.data-table{width:100%;border-collapse:collapse}.data-table th,.data-table td{padding:13px 15px;text-align:left;border-bottom:1px solid rgba(148,163,184,.12)}.data-table th{background:#0b111b;color:#cbd5e1;font-size:12px}.data-table td{color:#e2e8f0}.data-table tbody tr:hover{background:rgba(37,99,235,.06)}.data-table td small{display:block;color:#94a3b8;margin-top:3px}.customer-name{font-size:14px}
.status-badge{display:inline-block;padding:5px 9px;border-radius:999px;font-size:11px;font-weight:700}.status-active{background:rgba(34,197,94,.13);color:#4ade80}.status-suspended,.status-inactive{background:rgba(244,63,94,.13);color:#fb7185}.status-expired{background:rgba(245,158,11,.13);color:#fbbf24}.status-default{background:#172235;color:#cbd5e1}
.btn{display:inline-flex;align-items:center;justify-content:center;min-height:38px;box-sizing:border-box;padding:9px 14px;border-radius:9px;border:1px solid transparent;text-decoration:none;font-size:13px;font-weight:700;cursor:pointer}.btn-sm{min-height:32px;padding:7px 10px;font-size:12px}.btn-primary{background:linear-gradient(135deg,#2563eb,#3b82f6);color:#fff;box-shadow:0 8px 22px rgba(37,99,235,.18)}.btn-secondary{background:#172235;color:#cbd5e1;border-color:rgba(148,163,184,.14)}.btn-secondary:hover{background:#1d2b42;color:#fff}
.customer-side-panel{display:flex;flex-direction:column;gap:20px}.side-card{padding:20px}.side-card h3{margin:6px 0 16px}.side-stat{display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid rgba(148,163,184,.1);color:#94a3b8}.side-stat:last-child{border-bottom:0}.side-stat strong{color:#f8fafc}.quick-actions a{display:flex;justify-content:space-between;gap:10px;padding:11px 0;color:#cbd5e1;text-decoration:none;border-bottom:1px solid rgba(148,163,184,.1)}.quick-actions a:hover{color:#60a5fa}.quick-actions a:last-child{border-bottom:0}.empty-state{text-align:center!important;padding:42px!important;color:#64748b!important}
@media(max-width:1050px){.customer-workspace{grid-template-columns:1fr}.customer-side-panel{display:grid;grid-template-columns:1fr 1fr}.customer-metrics{grid-template-columns:repeat(2,1fr)}}
@media(max-width:700px){.customer-command-center{padding:15px}.customer-hero{align-items:flex-start;flex-direction:column;padding:22px}.customer-metrics,.customer-side-panel{grid-template-columns:1fr}.customer-search{grid-template-columns:1fr}.customer-hero h1{font-size:27px}}
</style>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>