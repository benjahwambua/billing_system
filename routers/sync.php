<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/mikrotik_api.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('network', 'view');

global $conn;
$tenantId = (int)getCurrentTenantId();
$routerId = (int)($_GET['id'] ?? 0);
if ($tenantId <= 0 || $routerId <= 0) {
    http_response_code(400);
    exit('A valid tenant and router are required.');
}

$routerStmt = $conn->prepare('SELECT * FROM mikrotik_routers WHERE id=? AND tenant_id=? LIMIT 1');
if (!$routerStmt) {
    http_response_code(503);
    exit('Unable to prepare tenant router lookup.');
}
$routerStmt->bind_param('ii', $routerId, $tenantId);
$routerStmt->execute();
$router = $routerStmt->get_result()->fetch_assoc();
$routerStmt->close();
if (!$router) {
    http_response_code(404);
    exit('Router not found for this tenant.');
}

$readColumns = static function ($table) use ($conn) {
    if (!in_array($table, ['mikrotik_routers', 'pppoe_servers', 'ip_pools'], true)) {
        return [];
    }
    $columns = [];
    $query = $conn->query('SHOW COLUMNS FROM ' . $table);
    if ($query) {
        while ($column = $query->fetch_assoc()) {
            $columns[] = $column['Field'];
        }
        $query->free();
    }
    return $columns;
};
$routerColumns = $readColumns('mikrotik_routers');
$pick = static function (array $row, array $columns, array $candidates, $default = '') {
    foreach ($candidates as $column) {
        if (in_array($column, $columns, true) && isset($row[$column]) && $row[$column] !== '') {
            return $row[$column];
        }
    }
    return $default;
};
$routerName = (string)$pick($router, $routerColumns, ['name', 'router_name'], 'Router #' . $routerId);
$host = (string)$pick($router, $routerColumns, ['host', 'ip_address', 'ip']);
$username = (string)$pick($router, $routerColumns, ['username', 'user']);
$password = (string)$pick($router, $routerColumns, ['password', 'api_password']);
$port = (int)$pick($router, $routerColumns, ['api_port', 'port'], 8728);

$serverColumns = $readColumns('pppoe_servers');
$poolColumns = $readColumns('ip_pools');
$requiredServerColumns = ['id', 'tenant_id', 'router_id', 'ip_pool_id'];
$requiredPoolColumns = ['id', 'tenant_id', 'start_ip', 'end_ip', 'gateway'];
foreach ($requiredServerColumns as $column) {
    if (!in_array($column, $serverColumns, true)) {
        http_response_code(503);
        exit('The PPPoE server schema is missing a required association field. No router changes were made.');
    }
}
foreach ($requiredPoolColumns as $column) {
    if (!in_array($column, $poolColumns, true)) {
        http_response_code(503);
        exit('The IP pool schema is missing a required allocation field. No router changes were made.');
    }
}
$poolNameColumn = in_array('name', $poolColumns, true) ? 'name' : (in_array('pool_name', $poolColumns, true) ? 'pool_name' : null);
if (!$poolNameColumn) {
    http_response_code(503);
    exit('The IP pool schema has no supported name field. No router changes were made.');
}

$serverSql = 'SELECT id, router_id, ip_pool_id' .
    (in_array('name', $serverColumns, true) ? ', name' : (in_array('server_name', $serverColumns, true) ? ', server_name AS name' : ", CONCAT('PPPoE Server #', id) AS name")) .
    ' FROM pppoe_servers WHERE tenant_id=? AND router_id=? ORDER BY id';
$serverStmt = $conn->prepare($serverSql);
if (!$serverStmt) {
    http_response_code(503);
    exit('Unable to load tenant PPPoE servers.');
}
$serverStmt->bind_param('ii', $tenantId, $routerId);
$serverStmt->execute();
$serverResult = $serverStmt->get_result();
$servers = [];
while ($server = $serverResult->fetch_assoc()) {
    $poolId = (int)($server['ip_pool_id'] ?? 0);
    if ($poolId <= 0) {
        $server['pool'] = null;
    } else {
        $poolStmt = $conn->prepare('SELECT id, ' . $poolNameColumn . ' AS pool_name, start_ip, end_ip, gateway FROM ip_pools WHERE id=? AND tenant_id=? LIMIT 1');
        if (!$poolStmt) {
            $server['pool'] = null;
            $server['pool_error'] = 'Unable to prepare tenant pool lookup.';
        } else {
            $poolStmt->bind_param('ii', $poolId, $tenantId);
            $poolStmt->execute();
            $server['pool'] = $poolStmt->get_result()->fetch_assoc() ?: null;
            $poolStmt->close();
        }
    }
    $servers[] = $server;
}
$serverStmt->close();

$results = [];
$routerError = '';
$identity = '';
if ($host === '' || $username === '') {
    $routerError = 'Router connection details are incomplete.';
} else {
    try {
        $api = new FlexihubRouterOS($host, $username, $password, $port, 8);
        try {
            $identityRows = $api->command(['/system/identity/print', '=.proplist=name']);
            foreach ($identityRows as $row) {
                if (($row['!type'] ?? '') === '!re' && !empty($row['name'])) {
                    $identity = (string)$row['name'];
                    break;
                }
            }
            $poolRows = $api->command(['/ip/pool/print', '=.proplist=.id,name,ranges']);
            $remotePools = [];
            foreach ($poolRows as $row) {
                if (($row['!type'] ?? '') === '!re' && isset($row['name'])) {
                    $remotePools[(string)$row['name']] = (string)($row['ranges'] ?? '');
                }
            }
            $profileRows = $api->command(['/ppp/profile/print', '=.proplist=.id,name,local-address,remote-address']);
            $remoteProfiles = [];
            foreach ($profileRows as $row) {
                if (($row['!type'] ?? '') === '!re' && isset($row['name'])) {
                    $remoteProfiles[(string)$row['name']] = [
                        'local_address' => (string)($row['local-address'] ?? ''),
                        'remote_address' => (string)($row['remote-address'] ?? '')
                    ];
                }
            }

            foreach ($servers as $server) {
                $serverId = (int)$server['id'];
                $pool = $server['pool'] ?? null;
                $entry = [
                    'server' => (string)($server['name'] ?? ('PPPoE Server #' . $serverId)),
                    'pool_name' => '',
                    'expected_range' => '',
                    'expected_gateway' => '',
                    'pool_status' => 'Not configured',
                    'profile_status' => 'Not checked'
                ];
                if (!$pool) {
                    $entry['pool_status'] = 'Missing tenant IP pool';
                    $entry['profile_status'] = 'Not checked';
                    $results[] = $entry;
                    continue;
                }
                $poolId = (int)$pool['id'];
                $routerPoolName = 'fh_t' . $tenantId . '_pool' . $poolId;
                $routerProfileName = 'fh_t' . $tenantId . '_pppoe' . $serverId;
                $expectedRange = trim((string)$pool['start_ip']) . '-' . trim((string)$pool['end_ip']);
                $expectedGateway = trim((string)($pool['gateway'] ?? ''));
                $entry['pool_name'] = (string)$pool['pool_name'];
                $entry['expected_range'] = $expectedRange;
                $entry['expected_gateway'] = $expectedGateway;
                $entry['router_pool_name'] = $routerPoolName;
                $entry['router_profile_name'] = $routerProfileName;

                if (!isset($remotePools[$routerPoolName])) {
                    $entry['pool_status'] = 'Missing on router';
                } elseif (trim($remotePools[$routerPoolName]) !== $expectedRange) {
                    $entry['pool_status'] = 'Range differs';
                    $entry['router_range'] = $remotePools[$routerPoolName];
                } else {
                    $entry['pool_status'] = 'In sync';
                }

                if (!isset($remoteProfiles[$routerProfileName])) {
                    $entry['profile_status'] = 'Missing on router';
                } else {
                    $remoteProfile = $remoteProfiles[$routerProfileName];
                    $profileMatches = $remoteProfile['remote_address'] === $routerPoolName
                        && $remoteProfile['local_address'] === $expectedGateway;
                    $entry['profile_status'] = $profileMatches ? 'In sync' : 'Configuration differs';
                    if (!$profileMatches) {
                        $entry['router_remote_address'] = $remoteProfile['remote_address'];
                        $entry['router_local_address'] = $remoteProfile['local_address'];
                    }
                }
                $results[] = $entry;
            }
        } finally {
            $api->close();
        }
    } catch (Throwable $e) {
        $routerError = $e->getMessage();
    }
}

$pageTitle = 'Router Provisioning Sync Check';
require_once '../includes/header.php';
?>
<div class="page-content">
    <div class="page-header">
        <div>
            <h1>Router Provisioning Sync Check</h1>
            <p>Read-only comparison of Flexihub PPPoE server pools and profiles against this tenant's MikroTik router.</p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
            <a class="btn btn-secondary" href="index.php">Back to Routers</a>
            <a class="btn btn-secondary" href="sync.php?id=<?= (int)$routerId ?>">Refresh Check</a>
        </div>
    </div>
    <div class="card" style="padding:18px;margin-bottom:16px">
        <strong><?= e($routerName) ?></strong>
        <div style="color:#94a3b8;margin-top:5px"><?= e($host) ?> · API port <?= (int)$port ?><?= $identity !== '' ? ' · Router identity: ' . e($identity) : '' ?></div>
    </div>
    <?php if ($routerError !== ''): ?>
        <div class="alert alert-danger"><?= e($routerError) ?></div>
    <?php elseif (!$results): ?>
        <div class="alert alert-info">No PPPoE servers are assigned to this router for the current tenant.</div>
    <?php else: ?>
        <div class="card" style="padding:18px;overflow:auto">
            <table class="table">
                <thead><tr><th>PPPoE Server</th><th>Flexihub IP Pool</th><th>RouterOS Pool</th><th>Pool Check</th><th>PPP Profile Check</th></tr></thead>
                <tbody>
                <?php foreach ($results as $item): ?>
                    <tr>
                        <td><?= e($item['server']) ?></td>
                        <td><?= e($item['pool_name'] ?: '—') ?><br><small><?= e($item['expected_range']) ?></small></td>
                        <td><?= e($item['router_pool_name'] ?? '—') ?><br><small><?= e($item['router_profile_name'] ?? '') ?></small></td>
                        <td><?= e($item['pool_status']) ?><?php if (!empty($item['router_range'])): ?><br><small>Router range: <?= e($item['router_range']) ?></small><?php endif; ?></td>
                        <td><?= e($item['profile_status']) ?><?php if (isset($item['router_remote_address'])): ?><br><small>Remote: <?= e($item['router_remote_address']) ?> · Local: <?= e($item['router_local_address']) ?></small><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <p style="color:#94a3b8;font-size:12px;margin-top:14px">Safety: this page only reads RouterOS identity, IP pools and PPP profiles. It does not add, edit, remove, enable, disable or disconnect anything. RouterOS write protection remains disabled.</p>
</div>
<?php require_once '../includes/footer.php'; ?>
