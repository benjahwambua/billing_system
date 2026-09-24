<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireHost();

$pageTitle = 'Tenants';

$tenants = [];

$sql = "
    SELECT
        id,
        tenant_code,
        business_name,
        trading_name,
        owner_name,
        email,
        phone,
        county,
        town,
        status,
        onboarding_status,
        onboarding_step,
        trial_started_at,
        trial_ends_at,
        activated_at,
        created_at
    FROM tenants
    ORDER BY id DESC
";

$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $tenants[] = $row;
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content">

    <div class="page-header">
        <div>
            <h1>Tenants</h1>
            <p>Manage ISPs and businesses using the Flexihub platform.</p>
        </div>

        <div>
            <a href="add.php" class="btn btn-primary">
                + Add Tenant
            </a>
        </div>
    </div>

    <!-- Summary Cards -->
    <?php
    $totalTenants = count($tenants);
    $activeTenants = 0;
    $trialTenants = 0;
    $pendingTenants = 0;
    $suspendedTenants = 0;

    foreach ($tenants as $tenant) {
        switch ($tenant['status']) {
            case 'active':
                $activeTenants++;
                break;

            case 'trial':
                $trialTenants++;
                break;

            case 'pending':
                $pendingTenants++;
                break;

            case 'suspended':
            case 'past_due':
                $suspendedTenants++;
                break;
        }
    }
    ?>

    <div class="stats-grid">

        <div class="stat-card">
            <div class="stat-card-label">Total Tenants</div>
            <div class="stat-card-value">
                <?= e($totalTenants) ?>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-card-label">Active</div>
            <div class="stat-card-value">
                <?= e($activeTenants) ?>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-card-label">On Trial</div>
            <div class="stat-card-value">
                <?= e($trialTenants) ?>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-card-label">Pending</div>
            <div class="stat-card-value">
                <?= e($pendingTenants) ?>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-card-label">Suspended / Past Due</div>
            <div class="stat-card-value">
                <?= e($suspendedTenants) ?>
            </div>
        </div>

    </div>

    <!-- Tenant Table -->
    <div class="card">

        <div class="card-header">
            <div>
                <h2>All Tenants</h2>
                <p>Businesses currently registered on Flexihub.</p>
            </div>
        </div>

        <div class="table-responsive">

            <table class="data-table">

                <thead>
                    <tr>
                        <th>Tenant</th>
                        <th>Owner / Contact</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Onboarding</th>
                        <th>Trial / Activation</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (empty($tenants)): ?>

                    <tr>
                        <td colspan="8" class="empty-state">
                            No tenants have been registered yet.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($tenants as $tenant): ?>

                        <?php
                        $status = strtolower($tenant['status']);

                        $statusClass = 'status-default';

                        if ($status === 'active') {
                            $statusClass = 'status-success';
                        } elseif ($status === 'trial') {
                            $statusClass = 'status-warning';
                        } elseif ($status === 'pending') {
                            $statusClass = 'status-info';
                        } elseif (
                            $status === 'suspended' ||
                            $status === 'past_due'
                        ) {
                            $statusClass = 'status-danger';
                        } elseif (
                            $status === 'cancelled' ||
                            $status === 'closed'
                        ) {
                            $statusClass = 'status-muted';
                        }

                        $onboarding = ucwords(
                            str_replace('_', ' ', $tenant['onboarding_status'])
                        );

                        $locationParts = [];

                        if (!empty($tenant['town'])) {
                            $locationParts[] = $tenant['town'];
                        }

                        if (!empty($tenant['county'])) {
                            $locationParts[] = $tenant['county'];
                        }

                        $location = !empty($locationParts)
                            ? implode(', ', $locationParts)
                            : '—';

                        $trialText = '—';

                        if (!empty($tenant['trial_ends_at'])) {
                            $trialText = 'Trial ends: ' .
                                date('d M Y', strtotime($tenant['trial_ends_at']));
                        } elseif (!empty($tenant['activated_at'])) {
                            $trialText = 'Activated: ' .
                                date('d M Y', strtotime($tenant['activated_at']));
                        }
                        ?>

                        <tr>

                            <td>
                                <div class="tenant-name">
                                    <strong>
                                        <?= e($tenant['business_name']) ?>
                                    </strong>

                                    <?php if (!empty($tenant['trading_name'])): ?>
                                        <small>
                                            <?= e($tenant['trading_name']) ?>
                                        </small>
                                    <?php endif; ?>

                                    <span class="tenant-code">
                                        <?= e($tenant['tenant_code']) ?>
                                    </span>
                                </div>
                            </td>

                            <td>
                                <div>
                                    <?= e($tenant['owner_name'] ?: '—') ?>
                                </div>

                                <?php if (!empty($tenant['phone'])): ?>
                                    <small>
                                        <?= e($tenant['phone']) ?>
                                    </small>
                                <?php endif; ?>

                                <?php if (!empty($tenant['email'])): ?>
                                    <small>
                                        <?= e($tenant['email']) ?>
                                    </small>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?= e($location) ?>
                            </td>

                            <td>
                                <span class="status-badge <?= e($statusClass) ?>">
                                    <?= e(ucwords(str_replace('_', ' ', $status))) ?>
                                </span>
                            </td>

                            <td>

                                <div>
                                    <?= e($onboarding) ?>
                                </div>

                                <?php if (
                                    $tenant['onboarding_status'] !== 'completed'
                                ): ?>

                                    <small>
                                        Step <?= e($tenant['onboarding_step']) ?>
                                    </small>

                                <?php endif; ?>

                            </td>

                            <td>
                                <?= e($trialText) ?>
                            </td>

                            <td>
                                <?= !empty($tenant['created_at'])
                                    ? e(date('d M Y', strtotime($tenant['created_at'])))
                                    : '—'
                                ?>
                            </td>

                            <td>

                                <div class="action-buttons">

                                    <a
                                        href="view.php?id=<?= (int) $tenant['id'] ?>"
                                        class="btn btn-sm btn-secondary"
                                    >
                                        View
                                    </a>

                                    <a
                                        href="edit.php?id=<?= (int) $tenant['id'] ?>"
                                        class="btn btn-sm btn-primary"
                                    >
                                        Edit
                                    </a>

                                    <a
                                        href="users.php?id=<?= (int) $tenant['id'] ?>"
                                        class="btn btn-sm btn-outline"
                                    >
                                        Users
                                    </a>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

<style>
.main-content {
    padding: 24px;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    margin-bottom: 24px;
}

.page-header h1 {
    margin: 0 0 5px;
}

.page-header p {
    margin: 0;
    color: #6b7280;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.stat-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 20px;
}

.stat-card-label {
    color: #6b7280;
    font-size: 13px;
    margin-bottom: 8px;
}

.stat-card-value {
    font-size: 28px;
    font-weight: 700;
}

.card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    overflow: hidden;
}

.card-header {
    padding: 20px;
    border-bottom: 1px solid #e5e7eb;
}

.card-header h2 {
    margin: 0 0 5px;
}

.card-header p {
    margin: 0;
    color: #6b7280;
}

.table-responsive {
    width: 100%;
    overflow-x: auto;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1100px;
}

.data-table th,
.data-table td {
    padding: 14px 16px;
    border-bottom: 1px solid #e5e7eb;
    text-align: left;
    vertical-align: middle;
}

.data-table th {
    background: #f9fafb;
    font-size: 13px;
    color: #4b5563;
    font-weight: 600;
}

.data-table tbody tr:hover {
    background: #fafafa;
}

.tenant-name {
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.tenant-name small {
    color: #6b7280;
}

.tenant-code {
    display: inline-block;
    font-size: 11px;
    color: #6b7280;
    font-family: monospace;
}

.data-table td small {
    display: block;
    color: #6b7280;
    margin-top: 3px;
}

.status-badge {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
}

.status-success {
    background: #dcfce7;
    color: #166534;
}

.status-warning {
    background: #fef3c7;
    color: #92400e;
}

.status-info {
    background: #dbeafe;
    color: #1e40af;
}

.status-danger {
    background: #fee2e2;
    color: #991b1b;
}

.status-muted {
    background: #f3f4f6;
    color: #4b5563;
}

.status-default {
    background: #f3f4f6;
    color: #374151;
}

.action-buttons {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}

.btn {
    display: inline-block;
    padding: 9px 14px;
    border-radius: 6px;
    text-decoration: none;
    border: 1px solid transparent;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
}

.btn-sm {
    padding: 6px 10px;
    font-size: 12px;
}

.btn-primary {
    background: #111827;
    color: #fff;
}

.btn-secondary {
    background: #f3f4f6;
    color: #111827;
}

.btn-outline {
    background: #fff;
    border-color: #d1d5db;
    color: #374151;
}

.btn:hover {
    opacity: 0.9;
}

.empty-state {
    text-align: center !important;
    padding: 50px !important;
    color: #6b7280;
}

@media (max-width: 768px) {
    .main-content {
        padding: 15px;
    }

    .page-header {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>