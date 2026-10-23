<?php
require_once __DIR__ . '/../includes/auth.php';
requireActiveUser();
requireTenantContext();

$tenantId = (int)getCurrentTenantId();
$pageTitle = 'Service Activation Queue';
$metrics = [
    'activation_pending' => 0,
    'activation_failed' => 0,
    'hotspot_pending' => 0,
    'hotspot_failed' => 0,
    'router_sync_failed' => 0,
    'mpesa_failed' => 0,
    'overdue_invoices' => 0,
];
$errors = [];
$recentFailures = [];

$hasTable = static function ($table) use ($conn) {
    $allowed = ['service_activation_queue', 'hotspot_finalization_queue', 'router_sync_logs', 'payment_gateway_transactions', 'invoices'];
    if (!in_array($table, $allowed, true)) {
        return false;
    }
    $escaped = $conn->real_escape_string($table);
    $result = $conn->query("SHOW TABLES LIKE '{$escaped}'");
    return $result && $result->num_rows > 0;
};

$countStatus = static function ($table, $status) use ($conn, $tenantId, $hasTable, &$errors) {
    $allowedStatuses = ['pending', 'failed', 'overdue'];
    if (!in_array($status, $allowedStatuses, true) || !$hasTable($table)) {
        return 0;
    }
    $sql = "SELECT COUNT(*) AS total FROM {$table} WHERE tenant_id=? AND status=?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $errors[] = "Unable to prepare the {$table} status summary.";
        return 0;
    }
    $stmt->bind_param('is', $tenantId, $status);
    if (!$stmt->execute()) {
        $stmt->close();
        $errors[] = "Unable to load the {$table} status summary.";
        return 0;
    }
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    if (!$row) {
        $errors[] = "Unable to read the {$table} status summary.";
        return 0;
    }
    return (int)$row['total'];
};

$metrics['activation_pending'] = $countStatus('service_activation_queue', 'pending');
$metrics['activation_failed'] = $countStatus('service_activation_queue', 'failed');
$metrics['hotspot_pending'] = $countStatus('hotspot_finalization_queue', 'pending');
$metrics['hotspot_failed'] = $countStatus('hotspot_finalization_queue', 'failed');
$metrics['router_sync_failed'] = $countStatus('router_sync_logs', 'failed');
$metrics['mpesa_failed'] = $countStatus('payment_gateway_transactions', 'failed');
$metrics['overdue_invoices'] = $countStatus('invoices', 'overdue');

if ($hasTable('router_sync_logs')) {
    $stmt = $conn->prepare("SELECT router_id,service_type,external_username,action,message,created_at FROM router_sync_logs WHERE tenant_id=? AND status='failed' ORDER BY id DESC LIMIT 10");
    if (!$stmt) {
        $errors[] = 'Unable to prepare the recent router failure list.';
    } else {
        $stmt->bind_param('i', $tenantId);
        if (!$stmt->execute()) {
            $errors[] = 'Unable to load recent router failures.';
        } else {
            $result = $stmt->get_result();
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $recentFailures[] = [
                        'label' => trim(($row['service_type'] ?? '') . ' / ' . ($row['action'] ?? '')),
                        'message' => $row['message'] ?? '',
                        'created_at' => $row['created_at'] ?? '',
                    ];
                }
            }
        }
        $stmt->close();
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="main-content">
    <div class="page-header">
        <div>
            <h1>Service Operations Monitor</h1>
            <p>Read-only view of service activation, hotspot finalization, router synchronization and billing exceptions.</p>
        </div>
    </div>

    <?php if ($errors): ?>
        <div class="alert alert-warning" role="alert">
            <strong>Some monitoring data could not be loaded.</strong>
            <ul><?php foreach (array_unique($errors) as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <div class="form-grid" style="margin-top:20px">
        <div class="card"><strong>Activations Pending</strong><div style="font-size:28px"><?= e($metrics['activation_pending']) ?></div></div>
        <div class="card"><strong>Failed Activations</strong><div style="font-size:28px"><?= e($metrics['activation_failed']) ?></div></div>
        <div class="card"><strong>Hotspot Jobs Pending</strong><div style="font-size:28px"><?= e($metrics['hotspot_pending']) ?></div></div>
        <div class="card"><strong>Failed Hotspot Jobs</strong><div style="font-size:28px"><?= e($metrics['hotspot_failed']) ?></div></div>
        <div class="card"><strong>Router Sync Failures</strong><div style="font-size:28px"><?= e($metrics['router_sync_failed']) ?></div></div>
        <div class="card"><strong>Failed M-Pesa Transactions</strong><div style="font-size:28px"><?= e($metrics['mpesa_failed']) ?></div></div>
        <div class="card"><strong>Overdue Invoices</strong><div style="font-size:28px"><?= e($metrics['overdue_invoices']) ?></div></div>
    </div>

    <div class="dashboard-card" style="margin-top:20px">
        <h2>Recent Router Synchronization Failures</h2>
        <?php if (!$recentFailures): ?>
            <p>No router failures were returned by the current query.</p>
        <?php else: ?>
            <div style="overflow:auto;margin-top:12px">
                <table class="table">
                    <thead><tr><th>Service / Action</th><th>Message</th><th>Time</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentFailures as $failure): ?>
                        <tr><td><?= e($failure['label']) ?></td><td><?= e($failure['message']) ?></td><td><?= e($failure['created_at']) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <p style="margin-top:20px">This page only reads queue and exception summaries. Actual lifecycle processing runs through the CLI worker <code>maintenance/cron_service_worker.php</code>; schedule it on the server and monitor its exit status and logs.</p>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
