<?php
require_once '../includes/auth.php';
requireActiveUser();
require_once '../includes/functions.php';
require_once '../includes/ip_pool_validation.php';
if (isTenantUser()) requireTenant();
requireModulePermission('pppoe', 'edit');
global $conn;

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(400); exit('Invalid PPPoE server ID.'); }

$cols = [];
$q = $conn->query("SHOW COLUMNS FROM pppoe_servers");
if (!$q) { http_response_code(503); exit('Unable to read PPPoE server schema.'); }
while ($x = $q->fetch_assoc()) $cols[] = $x['Field'];
$has = static fn($c) => in_array($c, $cols, true);
$tid = (int)getCurrentTenantId();
if ($has('tenant_id') && $tid <= 0) { http_response_code(403); exit('A valid tenant context is required.'); }

$sql = "SELECT * FROM pppoe_servers WHERE id=?";
$params = [$id]; $types = 'i';
if ($has('tenant_id')) { $sql .= ' AND tenant_id=?'; $params[] = $tid; $types .= 'i'; }
$st = $conn->prepare($sql);
if (!$st) { http_response_code(503); exit('Unable to load PPPoE server.'); }
$st->bind_param($types, ...$params);
$st->execute(); $row = $st->get_result()->fetch_assoc(); $st->close();
if (!$row) { http_response_code(404); exit('PPPoE server not found.'); }

$routerCols = [];
$routerSchema = $conn->query("SHOW COLUMNS FROM mikrotik_routers");
if (!$routerSchema) { http_response_code(503); exit('Unable to read router schema.'); }
while ($x = $routerSchema->fetch_assoc()) $routerCols[] = $x['Field'];
$routerHasTenant = in_array('tenant_id', $routerCols, true);
$routerHasStatus = in_array('status', $routerCols, true);
if (!$routerHasTenant || !$routerHasStatus || $tid <= 0) {
    http_response_code(503); exit('Tenant-scoped active router selection is unavailable.');
}
$routers = [];
$routerStmt = $conn->prepare("SELECT id, host, status FROM mikrotik_routers WHERE tenant_id=? AND (status='active' OR id=?) ORDER BY id DESC LIMIT 500");
if (!$routerStmt) { http_response_code(503); exit('Unable to load tenant routers.'); }
$routerStmt->bind_param('ii', $tid, $row['router_id']);
$routerStmt->execute(); $routerResult = $routerStmt->get_result();
while ($r = $routerResult->fetch_assoc()) $routers[] = $r;
$routerStmt->close();

$poolCols = [];
$pc = $conn->query("SHOW COLUMNS FROM ip_pools");
if (!$pc) { http_response_code(503); exit('Unable to read IP pool schema.'); }
while ($x = $pc->fetch_assoc()) $poolCols[] = $x['Field'];
$poolHas = static fn($c) => in_array($c, $poolCols, true);
if (!$poolHas('tenant_id') || $tid <= 0) {
    http_response_code(503); exit('Tenant-scoped IP pools are required.');
}
if (!$has('ip_pool_id')) {
    http_response_code(503); exit('The PPPoE server IP pool association is unavailable. Apply database migration 034 before editing the IP pool.');
}

$currentPoolId = (int)($row['ip_pool_id'] ?? 0);
$currentPool = [];
if ($currentPoolId > 0) {
    $poolStmt = $conn->prepare("SELECT * FROM ip_pools WHERE id=? AND tenant_id=? LIMIT 1");
    if (!$poolStmt) { http_response_code(503); exit('Unable to load the assigned IP pool.'); }
    $poolStmt->bind_param('ii', $currentPoolId, $tid);
    $poolStmt->execute(); $currentPool = $poolStmt->get_result()->fetch_assoc() ?: [];
    $poolStmt->close();
    if (!$currentPool) { http_response_code(409); exit('The assigned IP pool is missing or does not belong to this tenant.'); }
}

$poolNameColumn = $poolHas('name') ? 'name' : ($poolHas('pool_name') ? 'pool_name' : null);
$data = [
    'name' => (string)($row['name'] ?? $row['server_name'] ?? ''),
    'router_id' => (string)($row['router_id'] ?? ''),
    'interface' => (string)($row['interface'] ?? ''),
    'service_name' => (string)($row['service_name'] ?? 'pppoe'),
    'pool_name' => (string)($poolNameColumn ? ($currentPool[$poolNameColumn] ?? '') : ''),
    'network' => (string)($currentPool['network'] ?? ''),
    'cidr' => (string)($currentPool['cidr'] ?? ''),
    'gateway' => (string)($currentPool['gateway'] ?? ''),
    'start_ip' => (string)($currentPool['start_ip'] ?? ''),
    'end_ip' => (string)($currentPool['end_ip'] ?? ''),
    'status' => (string)($row['status'] ?? 'inactive'),
    'description' => (string)($row['description'] ?? ($currentPool['description'] ?? ''))
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    foreach ($data as $k => $v) $data[$k] = trim((string)($_POST[$k] ?? $v));
    $data['status'] = strtolower($data['status']);

    if ($data['name'] === '') $errors[] = 'Server name is required.';
    if ($data['pool_name'] === '') $data['pool_name'] = $data['name'] . ' Pool';
    if (!in_array($data['status'], ['active', 'inactive'], true)) $errors[] = 'Invalid server status.';
    if ($data['network'] === '' && $data['cidr'] !== '') $data['network'] = $data['cidr'];
    if ($data['cidr'] === '' && $data['network'] !== '') $data['cidr'] = $data['network'];

    $routerId = (int)$data['router_id'];
    if ($routerId <= 0) $errors[] = 'Select an active MikroTik router.';
    else {
        $routerCheck = $conn->prepare("SELECT id FROM mikrotik_routers WHERE id=? AND tenant_id=? AND status='active' LIMIT 1");
        if (!$routerCheck) $errors[] = 'Unable to validate selected MikroTik router.';
        else {
            $routerCheck->bind_param('ii', $routerId, $tid);
            if (!$routerCheck->execute()) $errors[] = 'Unable to validate selected MikroTik router.';
            elseif (!$routerCheck->get_result()->fetch_assoc()) $errors[] = 'Choose an active MikroTik router belonging to this tenant.';
            $routerCheck->close();
        }
    }

    if ($poolHas('router_id') && $currentPoolId > 0 && (int)($currentPool['router_id'] ?? 0) !== $routerId) {
        // The pool is updated to follow the server's selected router below.
    }

    $poolData = [
        'name' => $data['pool_name'], 'network' => $data['network'],
        'cidr' => $data['cidr'], 'gateway' => $data['gateway'],
        'start_ip' => $data['start_ip'], 'end_ip' => $data['end_ip']
    ];
    $errors = array_merge($errors, flexihubValidateIpPool($conn, $tid, $poolData, $poolCols, $currentPoolId));

    foreach (['name','router_id','network','gateway','start_ip','end_ip'] as $required) {
        if ($data[$required] === '') $errors[] = ucfirst(str_replace('_', ' ', $required)) . ' is required.';
    }

    if ($currentPoolId > 0 && $poolHas('status') && strtolower((string)($currentPool['status'] ?? '')) !== 'active' && $data['status'] === 'active') {
        $errors[] = 'An inactive IP pool cannot be assigned to an active PPPoE server. Activate the pool separately after verifying its configuration.';
    }

    if (!$errors) {
        $conn->begin_transaction();
        try {
            $poolMap = [
                'name' => $data['pool_name'], 'pool_name' => $data['pool_name'],
                'router_id' => $routerId, 'network' => $data['network'],
                'cidr' => $data['cidr'], 'gateway' => $data['gateway'],
                'start_ip' => $data['start_ip'], 'end_ip' => $data['end_ip'],
                'status' => $data['status'], 'description' => $data['description'],
                'tenant_id' => $tid
            ];
            if ($currentPoolId > 0) {
                $sets = []; $values = []; $bindTypes = '';
                foreach ($poolMap as $column => $value) {
                    if ($poolHas($column)) {
                        $sets[] = $column . '=?'; $values[] = $value;
                        $bindTypes .= is_int($value) ? 'i' : 's';
                    }
                }
                if (!$sets) throw new RuntimeException('No compatible IP pool fields were found.');
                $poolSql = 'UPDATE ip_pools SET ' . implode(',', $sets) . ' WHERE id=? AND tenant_id=?';
                $values[] = $currentPoolId; $values[] = $tid; $bindTypes .= 'ii';
                $poolUpdate = $conn->prepare($poolSql);
                if (!$poolUpdate) throw new RuntimeException('Unable to prepare IP pool update.');
                $poolUpdate->bind_param($bindTypes, ...$values);
                if (!$poolUpdate->execute()) throw new RuntimeException('Unable to update IP pool: ' . $poolUpdate->error);
                $poolUpdate->close();
                $poolId = $currentPoolId;
            } else {
                $fields = []; $values = []; $bindTypes = '';
                foreach ($poolMap as $column => $value) {
                    if ($poolHas($column)) {
                        $fields[] = $column; $values[] = $value;
                        $bindTypes .= is_int($value) ? 'i' : 's';
                    }
                }
                $poolInsert = $conn->prepare('INSERT INTO ip_pools (' . implode(',', $fields) . ') VALUES (' . implode(',', array_fill(0, count($fields), '?')) . ')');
                if (!$poolInsert) throw new RuntimeException('Unable to prepare IP pool save.');
                $poolInsert->bind_param($bindTypes, ...$values);
                if (!$poolInsert->execute()) throw new RuntimeException('Unable to save IP pool: ' . $poolInsert->error);
                $poolId = (int)$conn->insert_id;
                $poolInsert->close();
            }

            $serverMap = [
                'name' => $data['name'], 'server_name' => $data['name'],
                'router_id' => $routerId, 'interface' => $data['interface'],
                'service_name' => $data['service_name'], 'ip_pool_id' => $poolId,
                'status' => $data['status'], 'description' => $data['description']
            ];
            $sets = []; $values = []; $bindTypes = '';
            foreach ($serverMap as $column => $value) {
                if ($has($column)) {
                    $sets[] = $column . '=?'; $values[] = $value;
                    $bindTypes .= is_int($value) ? 'i' : 's';
                }
            }
            if (!$sets) throw new RuntimeException('No compatible PPPoE server fields were found.');
            $serverSql = 'UPDATE pppoe_servers SET ' . implode(',', $sets) . ' WHERE id=?';
            $values[] = $id; $bindTypes .= 'i';
            if ($has('tenant_id')) { $serverSql .= ' AND tenant_id=?'; $values[] = $tid; $bindTypes .= 'i'; }
            $serverUpdate = $conn->prepare($serverSql);
            if (!$serverUpdate) throw new RuntimeException('Unable to prepare PPPoE server update.');
            $serverUpdate->bind_param($bindTypes, ...$values);
            if (!$serverUpdate->execute()) throw new RuntimeException('Unable to update PPPoE server: ' . $serverUpdate->error);
            $serverUpdate->close();
            $conn->commit();
            redirect('index.php');
        } catch (Throwable $e) {
            $conn->rollback();
            $errors[] = $e->getMessage();
        }
    }
}
?>
<?php require '../includes/header.php'; ?>
<div class="page-content">
    <div class="page-header">
        <div><h1>Edit PPPoE Server</h1><p>Update server details and its subscriber IP allocation range.</p></div>
        <a class="btn btn-secondary" href="index.php">Back</a>
    </div>
    <?php if ($errors): ?>
        <div class="alert alert-danger"><ul><?php foreach (array_unique($errors) as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <div class="card" style="max-width:850px;padding:22px">
        <form method="post">
            <?= csrfField() ?>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                <label>Server Name<input name="name" required value="<?= e($data['name']) ?>"></label>
                <label>Router<select name="router_id" required><option value="">Select active router</option><?php foreach ($routers as $router): ?><option value="<?= (int)$router['id'] ?>" <?= ((string)$router['id'] === $data['router_id'] ? 'selected' : '') ?>><?= e(($router['host'] ?? 'Router') . ' (ID ' . (int)$router['id'] . ')') ?></option><?php endforeach; ?></select></label>
                <label>Interface<input name="interface" placeholder="ether1" value="<?= e($data['interface']) ?>"></label>
                <label>Service Name<input name="service_name" placeholder="pppoe" value="<?= e($data['service_name']) ?>"></label>
                <label>IP Pool Name<input name="pool_name" required value="<?= e($data['pool_name']) ?>" placeholder="Subscriber Pool"></label>
                <label>Network / CIDR<input name="network" required value="<?= e($data['network']) ?>" placeholder="10.20.0.0/24"></label>
                <label>CIDR (optional)<input name="cidr" value="<?= e($data['cidr']) ?>" placeholder="10.20.0.0/24"></label>
                <label>Gateway / Local IP<input name="gateway" required value="<?= e($data['gateway']) ?>" placeholder="10.20.0.1"></label>
                <label>Start IP<input name="start_ip" required value="<?= e($data['start_ip']) ?>" placeholder="10.20.0.10"></label>
                <label>End IP<input name="end_ip" required value="<?= e($data['end_ip']) ?>" placeholder="10.20.0.240"></label>
                <label>Status<select name="status"><option value="inactive" <?= $data['status'] === 'inactive' ? 'selected' : '' ?>>Inactive (safe test setup)</option><option value="active" <?= $data['status'] === 'active' ? 'selected' : '' ?>>Active</option></select></label>
                <label style="grid-column:1/-1">Description<input name="description" value="<?= e($data['description']) ?>"></label>
            </div>
            <p><small>This updates the associated IP pool and PPPoE server together in Flexihub. It does not apply changes to RouterOS. Use an unused, non-overlapping IP range.</small></p>
            <button class="btn btn-primary" style="margin-top:18px">Update Server and IP Pool</button>
        </form>
    </div>
</div>
<?php require '../includes/footer.php'; ?>