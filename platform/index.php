<?php
require_once '../includes/auth.php';
requireLogin();
requireHostContext();

$pageTitle = 'Platform';
$stats = ['total'=>0,'active'=>0,'trial'=>0,'pending'=>0,'suspended'=>0];

$check = $conn->query("SHOW TABLES LIKE 'tenants'");
if ($check && $check->num_rows > 0) {
    $result = $conn->query("SELECT status, COUNT(*) AS total FROM tenants GROUP BY status");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $status = strtolower(trim((string)$row['status']));
            $count = (int)$row['total'];
            $stats['total'] += $count;
            if ($status === 'active') $stats['active'] += $count;
            elseif ($status === 'trial') $stats['trial'] += $count;
            elseif ($status === 'pending') $stats['pending'] += $count;
            elseif (in_array($status, ['suspended','past_due'], true)) $stats['suspended'] += $count;
        }
    }
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<div class="main-content">
    <div class="page-header">
        <div><h1>Flexihub Platform</h1><p>Host control centre for the multi-tenant ISP SaaS platform.</p></div>
    </div>
    <div class="stats-grid">
        <div class="stat-card"><span>Total Tenants</span><strong><?= e($stats['total']) ?></strong></div>
        <div class="stat-card"><span>Active</span><strong><?= e($stats['active']) ?></strong></div>
        <div class="stat-card"><span>On Trial</span><strong><?= e($stats['trial']) ?></strong></div>
        <div class="stat-card"><span>Pending</span><strong><?= e($stats['pending']) ?></strong></div>
        <div class="stat-card"><span>Suspended / Past Due</span><strong><?= e($stats['suspended']) ?></strong></div>
    </div>
    <div class="workspace-grid">
        <a class="workspace-card" href="../tenants/index.php"><b>Tenants</b><span>Register, review and manage ISP tenants.</span></a>
        <a class="workspace-card" href="../platform_plans/index.php"><b>Subscription Plans</b><span>Manage SaaS plan definitions and limits.</span></a>
        <a class="workspace-card" href="../platform_transactions/index.php"><b>Platform Transactions</b><span>Review platform-level financial activity.</span></a>
        <a class="workspace-card" href="../platform_wallets/index.php"><b>Tenant Wallets</b><span>Monitor tenant funding and balances.</span></a>
        <a class="workspace-card" href="../platform_users/index.php"><b>Platform Users</b><span>Manage host-side operating users.</span></a>
        <a class="workspace-card" href="../platform_settings/index.php"><b>Platform Settings</b><span>Configure global platform behaviour.</span></a>
    </div>
</div>
<style>
.main-content{padding:24px}.page-header{margin-bottom:24px}.page-header h1{margin:0 0 6px}.page-header p{margin:0;color:#6b7280}.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:16px;margin-bottom:24px}.stat-card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px}.stat-card span{display:block;color:#6b7280;font-size:13px;margin-bottom:9px}.stat-card strong{font-size:28px}.workspace-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px}.workspace-card{display:flex;flex-direction:column;gap:8px;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px;text-decoration:none;color:#111827;transition:.15s}.workspace-card:hover{transform:translateY(-2px);border-color:#9ca3af}.workspace-card span{color:#6b7280;font-size:13px;line-height:1.5}
</style>
<?php require_once '../includes/footer.php'; ?>