<?php
require_once '../includes/auth.php';
requireActiveUser();
require_once '../includes/functions.php';
require_once '../includes/ip_pool_validation.php';
requireTenantContext();
requireModulePermission('pppoe', 'create');
global $conn;

$serverCols = [];
$q = $conn->query("SHOW COLUMNS FROM pppoe_servers");
if (!$q) die('Unable to read PPPoE server schema.');
while ($x = $q->fetch_assoc()) $serverCols[] = $x['Field'];
$serverHas = fn($c) => in_array($c, $serverCols, true);

$poolCols = [];
$poolQuery = $conn->query("SHOW COLUMNS FROM ip_pools");
if (!$poolQuery) die('Unable to read IP pool schema.');
while ($x = $poolQuery->fetch_assoc()) $poolCols[] = $x['Field'];
$poolHas = fn($c) => in_array($c, $poolCols, true);

$tid = (int)getCurrentTenantId();
if ($tid <= 0) { http_response_code(403); exit('A valid tenant context is required.'); }
if (!$serverHas('tenant_id') || !$poolHas('tenant_id')) {
    http_response_code(503);
    exit('Tenant-scoped PPPoE servers and IP pools are required. Please contact the administrator.');
}
if (!$serverHas('ip_pool_id')) {
    http_response_code(503);
    exit('The PPPoE server IP pool association is unavailable. Apply database migration 034 before creating a server.');
}

$routerCols = [];
$routerSchema = $conn->query("SHOW COLUMNS FROM mikrotik_routers");
if (!$routerSchema) { http_response_code(503); exit('Unable to read router schema.'); }
while ($x = $routerSchema->fetch_assoc()) $routerCols[] = $x['Field'];
if (!in_array('tenant_id', $routerCols, true) || !in_array('status', $routerCols, true)) {
    http_response_code(503); exit('Tenant-scoped active router selection is unavailable.');
}
$routers = [];
$routerQuery = $conn->prepare("SELECT id, host FROM mikrotik_routers WHERE tenant_id=? AND status='active' ORDER BY id DESC LIMIT 500");
if (!$routerQuery) { http_response_code(503); exit('Unable to load tenant routers.'); }
$routerQuery->bind_param('i', $tid);
$routerQuery->execute();
$routerResult = $routerQuery->get_result();
while ($row = $routerResult->fetch_assoc()) $routers[] = $row;
$routerQuery->close();

$data = [
    'name' => '', 'router_id' => '', 'interface' => '', 'service_name' => 'pppoe',
    'pool_name' => '', 'network' => '', 'cidr' => '', 'gateway' => '',
    'start_ip' => '', 'end_ip' => '', 'status' => 'inactive', 'description' => ''
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    foreach ($data as $key => $default) $data[$key] = trim((string)($_POST[$key] ?? $default));
    $data['status'] = strtolower($data['status']);

    if ($data['name'] === '') $errors[] = 'Server name is required.';
    if ($data['pool_name'] === '') $data['pool_name'] = $data['name'] . ' Pool';
    if (!in_array($data['status'], ['active', 'inactive'], true)) $errors[] = 'Invalid server status.';

    $routerId = (int)$data['router_id'];
    if ($routerId <= 0) {
        $errors[] = 'Select an active MikroTik router.';
    } else {
        $checkRouter = $conn->prepare("SELECT id FROM mikrotik_routers WHERE id=? AND tenant_id=? AND status='active' LIMIT 1");
        if (!$checkRouter) $errors[] = 'Unable to validate selected MikroTik router.';
        else {
            $checkRouter->bind_param('ii', $routerId, $tid);
            if (!$checkRouter->execute()) $errors[] = 'Unable to validate selected MikroTik router.';
            elseif (!$checkRouter->get_result()->fetch_assoc()) $errors[] = 'Choose an active MikroTik router belonging to this tenant.';
            $checkRouter->close();
        }
    }

    $poolData = [
        'name' => $data['pool_name'],
        'network' => $data['network'],
        'cidr' => $data['cidr'] !== '' ? $data['cidr'] : $data['network'],
        'gateway' => $data['gateway'],
        'start_ip' => $data['start_ip'],
        'end_ip' => $data['end_ip']
    ];
    $errors = array_merge($errors, flexihubValidateIpPool($conn, $tid, $poolData, $poolCols));

    foreach (['name','router_id','network','gateway','start_ip','end_ip'] as $required) {
        if (!array_key_exists($required, $data) || $data[$required] === '') $errors[] = ucfirst(str_replace('_', ' ', $required)) . ' is required.';
    }

    if (!$errors) {
        $conn->begin_transaction();
        try {
            $poolMap = [
                'name' => $data['pool_name'],
                'pool_name' => $data['pool_name'],
                'router_id' => $routerId,
                'network' => $data['network'],
                'cidr' => $data['cidr'] !== '' ? $data['cidr'] : $data['network'],
                'gateway' => $data['gateway'],
                'start_ip' => $data['start_ip'],
                'end_ip' => $data['end_ip'],
                'status' => $data['status'],
                'description' => $data['description'],
                'tenant_id' => $tid
            ];
            $poolFields = []; $poolValues = []; $poolTypes = '';
            foreach ($poolMap as $column => $value) {
                if ($poolHas($column) && !in_array($column, $poolFields, true)) {
                    $poolFields[] = $column; $poolValues[] = $value;
                    $poolTypes .= is_int($value) ? 'i' : 's';
                }
            }
            $poolStmt = $conn->prepare('INSERT INTO ip_pools (' . implode(',', $poolFields) . ') VALUES (' . implode(',', array_fill(0, count($poolFields), '?')) . ')');
            if (!$poolStmt) throw new RuntimeException('Unable to prepare IP pool save.');
            $poolStmt->bind_param($poolTypes, ...$poolValues);
            if (!$poolStmt->execute()) throw new RuntimeException('Unable to save IP pool: ' . $poolStmt->error);
            $poolId = (int)$conn->insert_id;
            $poolStmt->close();

            $serverMap = [
                'name' => $data['name'],
                'server_name' => $data['name'],
                'router_id' => $routerId,
                'interface' => $data['interface'],
                'service_name' => $data['service_name'],
                'ip_pool_id' => $poolId,
                'status' => $data['status'],
                'description' => $data['description'],
                'tenant_id' => $tid
            ];
            $serverFields = []; $serverValues = []; $serverTypes = '';
            foreach ($serverMap as $column => $value) {
                if ($serverHas($column) && !in_array($column, $serverFields, true)) {
                    $serverFields[] = $column; $serverValues[] = $value;
                    $serverTypes .= is_int($value) ? 'i' : 's';
                }
            }
            if (!in_array('ip_pool_id', $serverFields, true)) throw new RuntimeException('This database cannot link the new IP pool to the PPPoE server.');
            $serverStmt = $conn->prepare('INSERT INTO pppoe_servers (' . implode(',', $serverFields) . ') VALUES (' . implode(',', array_fill(0, count($serverFields), '?')) . ')');
            if (!$serverStmt) throw new RuntimeException('Unable to prepare PPPoE server save.');
            $serverStmt->bind_param($serverTypes, ...$serverValues);
            if (!$serverStmt->execute()) throw new RuntimeException('Unable to save PPPoE server: ' . $serverStmt->error);
            $serverStmt->close();
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
        <div><h1>Add PPPoE Server</h1><p>Configure the server and its subscriber IP allocation range.</p></div>
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
                <label>IP Pool Name<input name="pool_name" value="<?= e($data['pool_name']) ?>" placeholder="Subscriber Pool"></label>
                <label>Network / CIDR<input name="network" required value="<?= e($data['network']) ?>" placeholder="10.20.0.0/24"></label>
                <label>Gateway / Local IP<input name="gateway" required value="<?= e($data['gateway']) ?>" placeholder="10.20.0.1"></label>
                <label>Start IP<input name="start_ip" required value="<?= e($data['start_ip']) ?>" placeholder="10.20.0.10"></label>
                <label>End IP<input name="end_ip" required value="<?= e($data['end_ip']) ?>" placeholder="10.20.0.240"></label>
                <label>Status<select name="status"><option value="inactive" <?= $data['status'] === 'inactive' ? 'selected' : '' ?>>Inactive (safe test setup)</option><option value="active" <?= $data['status'] === 'active' ? 'selected' : '' ?>>Active</option></select></label>
                <label style="grid-column:1/-1">Description<input name="description" value="<?= e($data['description']) ?>"></label>
            </div>
            <p><small>This saves the server and its IP pool together in Flexihub. It does not apply changes to RouterOS. Use an unused, non-overlapping IP range.</small></p>
            <button class="btn btn-primary" style="margin-top:18px">Save Server and IP Pool</button>
        </form>
    </div>
</div>
<?php require '../includes/footer.php'; ?>