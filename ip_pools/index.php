<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireActiveUser();
requireTenantContext();
requireModulePermission('pppoe', 'view');

global $conn;

$tenantId = (int) getCurrentTenantId();
if ($tenantId <= 0) {
    http_response_code(403);
    exit('A valid tenant context is required to view IP pools.');
}

$columns = [];
$columnResult = $conn->query("SHOW COLUMNS FROM ip_pools");
if (!$columnResult) {
    http_response_code(503);
    exit('Unable to read IP pool schema.');
}
while ($column = $columnResult->fetch_assoc()) {
    $columns[] = $column['Field'];
}
$hasColumn = static fn($name) => in_array($name, $columns, true);

if (!$hasColumn('tenant_id')) {
    http_response_code(503);
    exit('IP pool tenant isolation is unavailable. Please contact the administrator.');
}

/*
 * The server assignment counters are optional until the corresponding
 * migration is present. They are always tenant-scoped when available.
 */
$serverColumns = [];
$serverColumnResult = $conn->query("SHOW COLUMNS FROM pppoe_servers");
if ($serverColumnResult) {
    while ($column = $serverColumnResult->fetch_assoc()) {
        $serverColumns[] = $column['Field'];
    }
}
$hasServerColumn = static fn($name) => in_array($name, $serverColumns, true);
$hasServerAssignments = $hasServerColumn('tenant_id')
    && $hasServerColumn('ip_pool_id')
    && $hasColumn('id');

$select = 'p.*';
if ($hasServerAssignments) {
    $select .= ",
        (SELECT COUNT(*) FROM pppoe_servers ps
         WHERE ps.ip_pool_id = p.id AND ps.tenant_id = p.tenant_id) AS assigned_server_count,
        (SELECT COUNT(*) FROM pppoe_servers ps
         WHERE ps.ip_pool_id = p.id AND ps.tenant_id = p.tenant_id
           AND LOWER(ps.status) = 'active') AS active_server_count";
}

$sql = "SELECT {$select}
        FROM ip_pools p
        WHERE p.tenant_id = ?
        ORDER BY p.id DESC
        LIMIT 200";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(503);
    exit('Unable to prepare IP pool listing.');
}
$stmt->bind_param('i', $tenantId);
if (!$stmt->execute()) {
    $stmt->close();
    http_response_code(503);
    exit('Unable to load IP pools.');
}
$rows = $stmt->get_result();

$canCreate = userCan('pppoe', 'create');
$canEdit = userCan('pppoe', 'edit');

function flexihubIpPoolValue(array $row, array $names, string $default = ''): string
{
    foreach ($names as $name) {
        if (isset($row[$name]) && $row[$name] !== '') {
            return (string) $row[$name];
        }
    }
    return $default;
}

$pageTitle = 'IP Pools';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-content">
    <div class="page-header">
        <div>
            <h1>IP Pools</h1>
            <p>Manage tenant address ranges and their PPPoE server assignments.</p>
        </div>
        <?php if ($canCreate): ?>
            <a class="btn btn-primary" href="add.php">+ Add IP Pool</a>
        <?php endif; ?>
    </div>

    <section class="dashboard-card">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Network / CIDR</th>
                        <th>Gateway</th>
                        <th>Allocation range</th>
                        <th>PPPoE servers</th>
                        <th>Status</th>
                        <?php if ($canEdit): ?><th>Action</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows->num_rows): ?>
                        <tr>
                            <td colspan="<?= $canEdit ? 7 : 6 ?>">
                                <div class="empty-state">
                                    <strong>No IP pools found</strong>
                                    <p>Create an address pool before assigning it to a PPPoE server.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php while ($row = $rows->fetch_assoc()): ?>
                            <?php
                            $name = flexihubIpPoolValue($row, ['name', 'pool_name'], 'Pool #' . $row['id']);
                            $status = strtolower(flexihubIpPoolValue($row, ['status'], 'unknown'));
                            $statusClass = $status === 'active' ? 'badge-success' : 'badge-muted';
                            $assignedCount = $hasServerAssignments ? (int) ($row['assigned_server_count'] ?? 0) : null;
                            $activeCount = $hasServerAssignments ? (int) ($row['active_server_count'] ?? 0) : null;
                            ?>
                            <tr>
                                <td><strong><?= e($name) ?></strong></td>
                                <td><?= e(flexihubIpPoolValue($row, ['network', 'cidr'], '—')) ?></td>
                                <td><?= e(flexihubIpPoolValue($row, ['gateway'], '—')) ?></td>
                                <td>
                                    <?php
                                    $start = flexihubIpPoolValue($row, ['start_ip']);
                                    $end = flexihubIpPoolValue($row, ['end_ip']);
                                    echo e($start !== '' && $end !== '' ? $start . ' – ' . $end : 'Subnet range');
                                    ?>
                                </td>
                                <td>
                                    <?php if ($assignedCount === null): ?>
                                        <span class="text-muted">Not available</span>
                                    <?php else: ?>
                                        <strong><?= e($assignedCount) ?></strong>
                                        <span class="help-text"><?= e($activeCount) ?> active</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge <?= e($statusClass) ?>"><?= e(ucfirst($status)) ?></span></td>
                                <?php if ($canEdit): ?>
                                    <td><a class="btn btn-secondary btn-sm" href="edit.php?id=<?= e((int) $row['id']) ?>">Edit</a></td>
                                <?php endif; ?>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<?php
$stmt->close();
require_once __DIR__ . '/../includes/footer.php';
?>
