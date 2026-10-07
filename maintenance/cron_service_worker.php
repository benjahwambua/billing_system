<?php
/**
 * Flexihub Billing System - CLI service lifecycle worker.
 *
 * Run from the server scheduler, not from a browser:
 *   php maintenance/cron_service_worker.php
 *
 * This worker is tenant-aware and processes lifecycle jobs for every tenant.
 * Network/RouterOS work remains inside the existing queues/workflows.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/billing_workflow.php';
require_once __DIR__ . '/../includes/hotspot_workflow.php';

$lockPath = __DIR__ . '/service_worker.lock';
$lock = fopen($lockPath, 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit("Another Flexihub service worker is already running.\n");
}

$started = microtime(true);
$totals = [
    'tenants' => 0,
    'account_expired' => 0,
    'subscription_expired' => 0,
    'activation_processed' => 0,
    'activation_completed' => 0,
    'activation_failed' => 0,
    'hotspot_finalized' => 0,
    'hotspot_finalization_failed' => 0,
    'hotspot_expired' => 0,
    'hotspot_expiry_failed' => 0,
];

try {
    $tenants = [];
    $q = $conn->query("SELECT id FROM tenants ORDER BY id ASC");
    if (!$q) {
        throw new Exception('Unable to load tenants.');
    }

    while ($row = $q->fetch_assoc()) {
        $tenants[] = (int)$row['id'];
    }
    $q->free();

    foreach ($tenants as $tenantId) {
        $totals['tenants']++;

        $accountExpiry = flexihubProcessAccountExpiry($tenantId, 200);
        $subscriptionExpiry = flexihubProcessSubscriptionExpiry($tenantId, 200);

        // Expiry queues are created first, then processed in the same run so
        // expired customers are disconnected without waiting for another cycle.
        $activation = flexihubProcessServiceActivationQueue($tenantId, 100);
        $hotspotFinalization = flexihubProcessHotspotFinalizationQueue($tenantId, 50);
        $hotspotExpiry = flexihubProcessHotspotExpiry($tenantId, 200);

        $totals['account_expired'] += (int)$accountExpiry['expired'];
        $totals['subscription_expired'] += (int)$subscriptionExpiry['expired'];
        $totals['activation_processed'] += (int)$activation['processed'];
        $totals['activation_completed'] += (int)$activation['completed'];
        $totals['activation_failed'] += (int)$activation['failed'];
        $totals['hotspot_finalized'] += (int)$hotspotFinalization['completed'];
        $totals['hotspot_finalization_failed'] += (int)$hotspotFinalization['failed'];
        $totals['hotspot_expired'] += (int)$hotspotExpiry['expired'];
        $totals['hotspot_expiry_failed'] += (int)$hotspotExpiry['failed'];
    }

    $duration = round(microtime(true) - $started, 3);
    echo "Flexihub service worker completed in {$duration}s\n";
    echo json_encode($totals, JSON_UNESCAPED_SLASHES) . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Flexihub service worker failed: " . $e->getMessage() . "\n");
    exit(1);
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}
