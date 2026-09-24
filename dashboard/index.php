<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| ACCESS
|--------------------------------------------------------------------------
*/

if (function_exists('requireLogin')) {
    requireLogin();
}

$userScope = $_SESSION['user_scope'] ?? 'tenant';
$isHost = ($userScope === 'host');

$tenantId = $_SESSION['tenant_id'] ?? null;


/*
|--------------------------------------------------------------------------
| DATABASE
|--------------------------------------------------------------------------
*/

global $conn;

if (!($conn instanceof mysqli)) {
    die('Database connection is not available.');
}


/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function dashboardBindParams($stmt, $params)
{
    if (empty($params)) {
        return true;
    }

    $types = str_repeat('i', count($params));
    $values = [];

    foreach ($params as $value) {
        $values[] = (int)$value;
    }

    $stmt->bind_param($types, ...$values);

    return true;
}


function dashboardCount($conn, $sql, $params = [])
{
    try {
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            return 0;
        }

        dashboardBindParams($stmt, $params);

        if (!$stmt->execute()) {
            $stmt->close();
            return 0;
        }

        $value = $stmt->get_result()->fetch_row()[0] ?? 0;
        $stmt->close();

        return (int)$value;
    } catch (Throwable $e) {
        return 0;
    }
}


function dashboardAmount($conn, $sql, $params = [])
{
    try {
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            return 0;
        }

        dashboardBindParams($stmt, $params);

        if (!$stmt->execute()) {
            $stmt->close();
            return 0;
        }

        $value = $stmt->get_result()->fetch_row()[0] ?? 0;
        $stmt->close();

        return (float)$value;
    } catch (Throwable $e) {
        return 0;
    }
}


function dashboardRows($conn, $sql, $params = [])
{
    try {
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            return [];
        }

        dashboardBindParams($stmt, $params);

        if (!$stmt->execute()) {
            $stmt->close();
            return [];
        }

        $result = $stmt->get_result();
        $rows = [];

        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }

        $stmt->close();

        return $rows;
    } catch (Throwable $e) {
        return [];
    }
}


function dashboardMoney($amount)
{
    return 'KES ' . number_format((float)$amount, 2);
}


function dashboardNumber($number)
{
    return number_format((int)$number);
}


/*
|--------------------------------------------------------------------------
| PAGE INFORMATION
|--------------------------------------------------------------------------
*/

$pageTitle = $isHost
    ? 'Platform Dashboard'
    : 'ISP Dashboard';

$pageSubtitle = $isHost
    ? 'Monitor Flexihub platform activity and tenant operations.'
    : 'Monitor your internet business, customers, billing and network.';


/*
|--------------------------------------------------------------------------
| HOST DASHBOARD
|--------------------------------------------------------------------------
*/

if ($isHost) {

    /*
    |--------------------------------------------------------------------------
    | TENANTS
    |--------------------------------------------------------------------------
    */

    $totalTenants = dashboardCount(
        $pdo,
        "SELECT COUNT(*) FROM tenants"
    );

    $activeTenants = dashboardCount(
        $pdo,
        "SELECT COUNT(*) FROM tenants WHERE status = 'active'"
    );

    $trialTenants = dashboardCount(
        $pdo,
        "SELECT COUNT(*) FROM tenants WHERE status = 'trial'"
    );

    $suspendedTenants = dashboardCount(
        $pdo,
        "SELECT COUNT(*) FROM tenants WHERE status = 'suspended'"
    );


    /*
    |--------------------------------------------------------------------------
    | PLATFORM TRANSACTIONS
    |--------------------------------------------------------------------------
    */

    $platformRevenueToday = dashboardAmount(
        $pdo,
        "
        SELECT COALESCE(SUM(amount), 0)
        FROM platform_transactions
        WHERE DATE(created_at) = CURDATE()
        "
    );

    $platformRevenueMonth = dashboardAmount(
        $pdo,
        "
        SELECT COALESCE(SUM(amount), 0)
        FROM platform_transactions
        WHERE YEAR(created_at) = YEAR(CURDATE())
        AND MONTH(created_at) = MONTH(CURDATE())
        "
    );


    /*
    |--------------------------------------------------------------------------
    | WALLETS
    |--------------------------------------------------------------------------
    */

    $walletBalance = dashboardAmount(
        $pdo,
        "
        SELECT COALESCE(SUM(balance), 0)
        FROM wallet_accounts
        "
    );


    /*
    |--------------------------------------------------------------------------
    | RECENT TENANTS
    |--------------------------------------------------------------------------
    */

    $recentTenants = dashboardRows(
        $pdo,
        "
        SELECT
            id,
            tenant_code,
            business_name,
            owner_name,
            status,
            created_at
        FROM tenants
        ORDER BY id DESC
        LIMIT 8
        "
    );


    /*
    |--------------------------------------------------------------------------
    | RECENT PLATFORM EVENTS
    |--------------------------------------------------------------------------
    */

    $recentEvents = dashboardRows(
        $pdo,
        "
        SELECT *
        FROM system_events
        ORDER BY id DESC
        LIMIT 8
        "
    );

} else {

    /*
    |--------------------------------------------------------------------------
    | TENANT DASHBOARD
    |--------------------------------------------------------------------------
    */

    /*
     * If a tenant session is not available, show zero values
     * rather than exposing platform-wide data.
     */

    if (!$tenantId) {

        $totalCustomers = 0;
        $activeCustomers = 0;
        $suspendedCustomers = 0;
        $expiredCustomers = 0;

        $revenueToday = 0;
        $revenueMonth = 0;

        $activeAccounts = 0;
        $onlineUsers = 0;
        $activeHotspotSessions = 0;
        $expiringServices = 0;
        $outstandingAmount = 0;

        $recentPayments = [];
        $routers = [];

    } else {

        /*
        |--------------------------------------------------------------------------
        | CUSTOMERS
        |--------------------------------------------------------------------------
        */

        $totalCustomers = dashboardCount(
            $pdo,
            "
            SELECT COUNT(*)
            FROM customers
            WHERE tenant_id = ?
            ",
            [$tenantId]
        );

        $activeCustomers = dashboardCount(
            $pdo,
            "
            SELECT COUNT(*)
            FROM customers
            WHERE tenant_id = ?
            AND status = 'Active'
            ",
            [$tenantId]
        );

        $suspendedCustomers = dashboardCount(
            $pdo,
            "
            SELECT COUNT(*)
            FROM customers
            WHERE tenant_id = ?
            AND status = 'Suspended'
            ",
            [$tenantId]
        );

        $expiredCustomers = dashboardCount(
            $pdo,
            "
            SELECT COUNT(*)
            FROM customers
            WHERE tenant_id = ?
            AND status = 'Expired'
            ",
            [$tenantId]
        );


        /*
        |--------------------------------------------------------------------------
        | REVENUE
        |--------------------------------------------------------------------------
        */

        $revenueToday = dashboardAmount(
            $pdo,
            "
            SELECT COALESCE(SUM(amount), 0)
            FROM payments
            WHERE tenant_id = ?
            AND DATE(payment_date) = CURDATE()
            ",
            [$tenantId]
        );

        $revenueMonth = dashboardAmount(
            $pdo,
            "
            SELECT COALESCE(SUM(amount), 0)
            FROM payments
            WHERE tenant_id = ?
            AND YEAR(payment_date) = YEAR(CURDATE())
            AND MONTH(payment_date) = MONTH(CURDATE())
            ",
            [$tenantId]
        );


        /*
        |--------------------------------------------------------------------------
        | INTERNET ACCOUNTS
        |--------------------------------------------------------------------------
        */

        $activeAccounts = dashboardCount(
            $pdo,
            "
            SELECT COUNT(*)
            FROM internet_accounts
            WHERE tenant_id = ?
            AND status = 'Active'
            ",
            [$tenantId]
        );


        /*
        |--------------------------------------------------------------------------
        | ONLINE USERS
        |--------------------------------------------------------------------------
        */

        $onlineUsers = dashboardCount(
            $pdo,
            "
            SELECT COUNT(*)
            FROM active_sessions
            WHERE tenant_id = ?
            AND status = 'online'
            ",
            [$tenantId]
        );


        /*
        |--------------------------------------------------------------------------
        | HOTSPOT
        |--------------------------------------------------------------------------
        */

        $activeHotspotSessions = dashboardCount(
            $pdo,
            "
            SELECT COUNT(*)
            FROM active_sessions
            WHERE tenant_id = ?
            AND status = 'online'
            AND session_type = 'hotspot'
            ",
            [$tenantId]
        );


        /*
        |--------------------------------------------------------------------------
        | EXPIRING SERVICES
        |--------------------------------------------------------------------------
        */

        $expiringServices = dashboardCount(
            $pdo,
            "
            SELECT COUNT(*)
            FROM internet_accounts
            WHERE tenant_id = ?
            AND expiry_date IS NOT NULL
            AND expiry_date BETWEEN CURDATE()
            AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
            AND status = 'Active'
            ",
            [$tenantId]
        );


        /*
        |--------------------------------------------------------------------------
        | OUTSTANDING
        |--------------------------------------------------------------------------
        */

        $outstandingAmount = dashboardAmount(
            $pdo,
            "
            SELECT COALESCE(SUM(balance), 0)
            FROM invoices
            WHERE tenant_id = ?
            AND balance > 0
            AND status NOT IN ('Paid', 'Cancelled')
            ",
            [$tenantId]
        );


        /*
        |--------------------------------------------------------------------------
        | RECENT PAYMENTS
        |--------------------------------------------------------------------------
        */

        $recentPayments = dashboardRows(
            $pdo,
            "
            SELECT *
            FROM payments
            WHERE tenant_id = ?
            ORDER BY id DESC
            LIMIT 8
            ",
            [$tenantId]
        );


        /*
        |--------------------------------------------------------------------------
        | ROUTERS
        |--------------------------------------------------------------------------
        */

        $routers = dashboardRows(
            $pdo,
            "
            SELECT *
            FROM mikrotik_routers
            WHERE tenant_id = ?
            ORDER BY id DESC
            LIMIT 6
            ",
            [$tenantId]
        );
    }
}


/*
|--------------------------------------------------------------------------
| HEADER
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../includes/header.php';

?>

<div class="dashboard-page">

    <!-- =========================================================
         PAGE HEADER
    ========================================================== -->

    <div class="dashboard-page-header">

        <div>

            <h1>
                <?= htmlspecialchars($pageTitle) ?>
            </h1>

            <p>
                <?= htmlspecialchars($pageSubtitle) ?>
            </p>

        </div>

        <div class="dashboard-date">

            <?= date('l, d F Y') ?>

        </div>

    </div>


    <?php if ($isHost): ?>

        <!-- =====================================================
             HOST DASHBOARD
        ====================================================== -->

        <div class="dashboard-grid">

            <div class="dashboard-stat-card">

                <div class="dashboard-stat-icon">
                    ◈
                </div>

                <div>
                    <div class="dashboard-stat-label">
                        Total Tenants
                    </div>

                    <div class="dashboard-stat-value">
                        <?= dashboardNumber($totalTenants) ?>
                    </div>
                </div>

            </div>


            <div class="dashboard-stat-card">

                <div class="dashboard-stat-icon">
                    ●
                </div>

                <div>
                    <div class="dashboard-stat-label">
                        Active Tenants
                    </div>

                    <div class="dashboard-stat-value">
                        <?= dashboardNumber($activeTenants) ?>
                    </div>
                </div>

            </div>


            <div class="dashboard-stat-card">

                <div class="dashboard-stat-icon">
                    ◷
                </div>

                <div>
                    <div class="dashboard-stat-label">
                        Trial Tenants
                    </div>

                    <div class="dashboard-stat-value">
                        <?= dashboardNumber($trialTenants) ?>
                    </div>
                </div>

            </div>


            <div class="dashboard-stat-card">

                <div class="dashboard-stat-icon">
                    !
                </div>

                <div>
                    <div class="dashboard-stat-label">
                        Suspended Tenants
                    </div>

                    <div class="dashboard-stat-value">
                        <?= dashboardNumber($suspendedTenants) ?>
                    </div>
                </div>

            </div>

        </div>


        <!-- PLATFORM FINANCE -->

        <div class="dashboard-grid dashboard-grid-three">

            <div class="dashboard-finance-card">

                <span>
                    Platform Revenue Today
                </span>

                <strong>
                    <?= dashboardMoney($platformRevenueToday) ?>
                </strong>

            </div>


            <div class="dashboard-finance-card">

                <span>
                    Platform Revenue This Month
                </span>

                <strong>
                    <?= dashboardMoney($platformRevenueMonth) ?>
                </strong>

            </div>


            <div class="dashboard-finance-card">

                <span>
                    Tenant Wallet Balance
                </span>

                <strong>
                    <?= dashboardMoney($walletBalance) ?>
                </strong>

            </div>

        </div>


        <!-- PLATFORM TABLES -->

        <div class="dashboard-columns">

            <div class="dashboard-panel">

                <div class="dashboard-panel-header">

                    <div>
                        <h2>Recent Tenants</h2>
                        <span>Latest ISP accounts on Flexihub</span>
                    </div>

                    <a href="../tenants/index.php">
                        View All
                    </a>

                </div>


                <div class="table-responsive">

                    <table class="dashboard-table">

                        <thead>

                            <tr>
                                <th>Tenant</th>
                                <th>Owner</th>
                                <th>Status</th>
                                <th>Created</th>
                            </tr>

                        </thead>

                        <tbody>

                        <?php if (!$recentTenants): ?>

                            <tr>
                                <td colspan="4" class="empty-state">
                                    No tenants found.
                                </td>
                            </tr>

                        <?php else: ?>

                            <?php foreach ($recentTenants as $tenant): ?>

                                <tr>

                                    <td>
                                        <strong>
                                            <?= htmlspecialchars($tenant['business_name'] ?? '') ?>
                                        </strong>

                                        <small>
                                            <?= htmlspecialchars($tenant['tenant_code'] ?? '') ?>
                                        </small>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($tenant['owner_name'] ?? '—') ?>
                                    </td>

                                    <td>
                                        <span class="status-badge status-<?= strtolower($tenant['status'] ?? '') ?>">
                                            <?= htmlspecialchars(ucfirst($tenant['status'] ?? 'Unknown')) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?= !empty($tenant['created_at'])
                                            ? date('d M Y', strtotime($tenant['created_at']))
                                            : '—'
                                        ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </div>


            <div class="dashboard-panel">

                <div class="dashboard-panel-header">

                    <div>
                        <h2>System Activity</h2>
                        <span>Latest platform events</span>
                    </div>

                    <a href="../system_events/index.php">
                        View All
                    </a>

                </div>


                <div class="dashboard-activity-list">

                    <?php if (!$recentEvents): ?>

                        <div class="empty-state">
                            No recent system events.
                        </div>

                    <?php else: ?>

                        <?php foreach ($recentEvents as $event): ?>

                            <div class="dashboard-activity-item">

                                <div class="activity-icon">
                                    !
                                </div>

                                <div>

                                    <strong>
                                        <?= htmlspecialchars(
                                            $event['event_name']
                                            ?? $event['event_type']
                                            ?? 'System Event'
                                        ) ?>
                                    </strong>

                                    <small>
                                        <?= !empty($event['created_at'])
                                            ? date('d M Y H:i', strtotime($event['created_at']))
                                            : ''
                                        ?>
                                    </small>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </div>

            </div>

        </div>


    <?php else: ?>


        <!-- =====================================================
             TENANT / ISP DASHBOARD
        ====================================================== -->

        <div class="dashboard-grid">

            <!-- REVENUE -->

            <div class="dashboard-stat-card">

                <div class="dashboard-stat-icon">
                    KES
                </div>

                <div>

                    <div class="dashboard-stat-label">
                        Revenue Today
                    </div>

                    <div class="dashboard-stat-value">
                        <?= dashboardMoney($revenueToday) ?>
                    </div>

                </div>

            </div>


            <!-- CUSTOMERS -->

            <div class="dashboard-stat-card">

                <div class="dashboard-stat-icon">
                    ♙
                </div>

                <div>

                    <div class="dashboard-stat-label">
                        Active Customers
                    </div>

                    <div class="dashboard-stat-value">
                        <?= dashboardNumber($activeCustomers) ?>
                    </div>

                    <small>
                        <?= dashboardNumber($totalCustomers) ?> total
                    </small>

                </div>

            </div>


            <!-- ONLINE -->

            <div class="dashboard-stat-card">

                <div class="dashboard-stat-icon">
                    ●
                </div>

                <div>

                    <div class="dashboard-stat-label">
                        Online Users
                    </div>

                    <div class="dashboard-stat-value">
                        <?= dashboardNumber($onlineUsers) ?>
                    </div>

                </div>

            </div>


            <!-- ACCOUNTS -->

            <div class="dashboard-stat-card">

                <div class="dashboard-stat-icon">
                    ◎
                </div>

                <div>

                    <div class="dashboard-stat-label">
                        Active Services
                    </div>

                    <div class="dashboard-stat-value">
                        <?= dashboardNumber($activeAccounts) ?>
                    </div>

                </div>

            </div>

        </div>


        <!-- SECONDARY STATS -->

        <div class="dashboard-grid dashboard-grid-four">

            <div class="dashboard-mini-card">

                <span>
                    Monthly Revenue
                </span>

                <strong>
                    <?= dashboardMoney($revenueMonth) ?>
                </strong>

            </div>


            <div class="dashboard-mini-card">

                <span>
                    Hotspot Sessions
                </span>

                <strong>
                    <?= dashboardNumber($activeHotspotSessions) ?>
                </strong>

            </div>


            <div class="dashboard-mini-card warning-card">

                <span>
                    Expiring in 7 Days
                </span>

                <strong>
                    <?= dashboardNumber($expiringServices) ?>
                </strong>

            </div>


            <div class="dashboard-mini-card danger-card">

                <span>
                    Outstanding
                </span>

                <strong>
                    <?= dashboardMoney($outstandingAmount) ?>
                </strong>

            </div>

        </div>


        <!-- QUICK ACTIONS -->

        <div class="dashboard-panel">

            <div class="dashboard-panel-header">

                <div>
                    <h2>Quick Actions</h2>
                    <span>Common ISP operations</span>
                </div>

            </div>


            <div class="quick-actions">

                <a href="../customers/add.php" class="quick-action">
                    <span>+</span>
                    <strong>Add Customer</strong>
                    <small>Register a new customer</small>
                </a>

                <a href="../payments/index.php" class="quick-action">
                    <span>◆</span>
                    <strong>Record Payment</strong>
                    <small>Capture a customer payment</small>
                </a>

                <a href="../internet_accounts/index.php" class="quick-action">
                    <span>◎</span>
                    <strong>Manage Services</strong>
                    <small>View internet accounts</small>
                </a>

                <a href="../pppoe/accounts.php" class="quick-action">
                    <span>♙</span>
                    <strong>PPPoE Accounts</strong>
                    <small>Manage PPPoE subscribers</small>
                </a>

                <a href="../hotspot/packages.php" class="quick-action">
                    <span>◉</span>
                    <strong>Hotspot Packages</strong>
                    <small>Manage hotspot packages</small>
                </a>

                <a href="../routers/index.php" class="quick-action">
                    <span>▣</span>
                    <strong>MikroTik Routers</strong>
                    <small>Manage network devices</small>
                </a>

            </div>

        </div>


        <!-- LOWER DASHBOARD -->

        <div class="dashboard-columns">

            <!-- RECENT PAYMENTS -->

            <div class="dashboard-panel">

                <div class="dashboard-panel-header">

                    <div>
                        <h2>Recent Payments</h2>
                        <span>Latest customer collections</span>
                    </div>

                    <a href="../payments/index.php">
                        View All
                    </a>

                </div>


                <div class="table-responsive">

                    <table class="dashboard-table">

                        <thead>

                            <tr>
                                <th>Reference</th>
                                <th>Amount</th>
                                <th>Date</th>
                            </tr>

                        </thead>

                        <tbody>

                        <?php if (!$recentPayments): ?>

                            <tr>
                                <td colspan="3" class="empty-state">
                                    No payments recorded yet.
                                </td>
                            </tr>

                        <?php else: ?>

                            <?php foreach ($recentPayments as $payment): ?>

                                <tr>

                                    <td>
                                        <?= htmlspecialchars(
                                            $payment['payment_reference']
                                            ?? $payment['reference']
                                            ?? $payment['transaction_code']
                                            ?? '—'
                                        ) ?>
                                    </td>

                                    <td>
                                        <strong>
                                            <?= dashboardMoney($payment['amount'] ?? 0) ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <?= !empty($payment['payment_date'])
                                            ? date('d M Y H:i', strtotime($payment['payment_date']))
                                            : (
                                                !empty($payment['created_at'])
                                                ? date('d M Y H:i', strtotime($payment['created_at']))
                                                : '—'
                                            )
                                        ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </div>


            <!-- NETWORK -->

            <div class="dashboard-panel">

                <div class="dashboard-panel-header">

                    <div>
                        <h2>Network Status</h2>
                        <span>MikroTik router overview</span>
                    </div>

                    <a href="../routers/index.php">
                        Manage
                    </a>

                </div>


                <div class="router-status-list">

                    <?php if (!$routers): ?>

                        <div class="empty-state">
                            No MikroTik routers configured.
                        </div>

                    <?php else: ?>

                        <?php foreach ($routers as $router): ?>

                            <?php

                            $routerStatus = strtolower(
                                $router['status'] ?? 'unknown'
                            );

                            $isOnline = in_array(
                                $routerStatus,
                                ['online', 'connected', 'active'],
                                true
                            );

                            ?>

                            <div class="router-status-item">

                                <div class="router-status-left">

                                    <span class="router-dot <?= $isOnline ? 'online' : 'offline' ?>"></span>

                                    <div>

                                        <strong>
                                            <?= htmlspecialchars(
                                                $router['name']
                                                ?? $router['router_name']
                                                ?? 'Router'
                                            ) ?>
                                        </strong>

                                        <small>
                                            <?= htmlspecialchars(
                                                $router['location']
                                                ?? 'Network device'
                                            ) ?>
                                        </small>

                                    </div>

                                </div>

                                <span class="router-status-text">
                                    <?= $isOnline ? 'Online' : 'Offline' ?>
                                </span>

                            </div>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </div>

            </div>

        </div>


    <?php endif; ?>

</div>


<style>

/* =========================================================
   DASHBOARD
========================================================= */

.dashboard-page {
    width: 100%;
}


/* PAGE HEADER */

.dashboard-page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;

    margin-bottom: 25px;
}

.dashboard-page-header h1 {
    font-size: 25px;
    color: #111827;
}

.dashboard-page-header p {
    margin-top: 6px;

    color: #6b7280;

    font-size: 13px;
}

.dashboard-date {
    color: #6b7280;

    font-size: 13px;
}


/* =========================================================
   STAT CARDS
========================================================= */

.dashboard-stat-card {
    background: #ffffff;

    border: 1px solid #e5e7eb;

    border-radius: 12px;

    padding: 20px;

    display: flex;
    align-items: center;

    gap: 15px;

    min-width: 0;
}

.dashboard-stat-icon {
    width: 46px;
    height: 46px;

    flex-shrink: 0;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 10px;

    background: #eff6ff;

    color: #2563eb;

    font-size: 13px;
    font-weight: 700;
}

.dashboard-stat-label {
    color: #6b7280;

    font-size: 12px;

    margin-bottom: 7px;
}

.dashboard-stat-value {
    color: #111827;

    font-size: 22px;

    font-weight: 700;
}

.dashboard-stat-card small {
    color: #9ca3af;

    font-size: 11px;
}


/* =========================================================
   FINANCE CARDS
========================================================= */

.dashboard-finance-card,
.dashboard-mini-card {
    background: #ffffff;

    border: 1px solid #e5e7eb;

    border-radius: 12px;

    padding: 20px;
}

.dashboard-finance-card span,
.dashboard-mini-card span {
    display: block;

    color: #6b7280;

    font-size: 12px;

    margin-bottom: 8px;
}

.dashboard-finance-card strong,
.dashboard-mini-card strong {
    display: block;

    color: #111827;

    font-size: 20px;
}

.warning-card strong {
    color: #b45309;
}

.danger-card strong {
    color: #dc2626;
}


/* =========================================================
   GRID VARIATIONS
========================================================= */

.dashboard-grid-three {
    grid-template-columns: repeat(3, 1fr);
}

.dashboard-grid-four {
    grid-template-columns: repeat(4, 1fr);
}


/* =========================================================
   COLUMNS
========================================================= */

.dashboard-columns {
    display: grid;

    grid-template-columns: repeat(2, minmax(0, 1fr));

    gap: 20px;

    margin-top: 20px;
}


/* =========================================================
   PANELS
========================================================= */

.dashboard-panel {
    background: #ffffff;

    border: 1px solid #e5e7eb;

    border-radius: 12px;

    overflow: hidden;
}

.dashboard-panel-header {
    min-height: 70px;

    padding: 16px 20px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    border-bottom: 1px solid #e5e7eb;
}

.dashboard-panel-header h2 {
    font-size: 15px;

    color: #111827;
}

.dashboard-panel-header span {
    display: block;

    margin-top: 4px;

    color: #9ca3af;

    font-size: 11px;
}

.dashboard-panel-header a {
    color: #2563eb;

    font-size: 12px;

    font-weight: 600;
}


/* =========================================================
   TABLE
========================================================= */

.dashboard-table {
    width: 100%;

    border-collapse: collapse;
}

.dashboard-table th {
    padding: 11px 20px;

    background: #f9fafb;

    color: #6b7280;

    font-size: 10px;

    text-transform: uppercase;

    letter-spacing: 0.5px;

    text-align: left;
}

.dashboard-table td {
    padding: 13px 20px;

    border-bottom: 1px solid #f1f5f9;

    font-size: 12px;

    color: #374151;
}

.dashboard-table tr:last-child td {
    border-bottom: none;
}

.dashboard-table td strong {
    display: block;

    color: #111827;
}

.dashboard-table td small {
    display: block;

    margin-top: 3px;

    color: #9ca3af;

    font-size: 10px;
}


/* =========================================================
   STATUS BADGES
========================================================= */

.status-badge {
    display: inline-block;

    padding: 4px 8px;

    border-radius: 20px;

    font-size: 10px;

    font-weight: 600;
}

.status-active {
    background: #dcfce7;
    color: #166534;
}

.status-trial {
    background: #dbeafe;
    color: #1d4ed8;
}

.status-suspended {
    background: #fee2e2;
    color: #991b1b;
}

.status-pending {
    background: #fef3c7;
    color: #92400e;
}

.status-cancelled,
.status-closed {
    background: #f3f4f6;
    color: #4b5563;
}


/* =========================================================
   QUICK ACTIONS
========================================================= */

.quick-actions {
    display: grid;

    grid-template-columns: repeat(3, 1fr);

    gap: 12px;

    padding: 18px;
}

.quick-action {
    display: block;

    padding: 15px;

    border: 1px solid #e5e7eb;

    border-radius: 9px;

    background: #ffffff;

    transition: 0.2s;
}

.quick-action:hover {
    border-color: #2563eb;

    background: #f8fbff;
}

.quick-action > span {
    display: inline-flex;

    width: 32px;
    height: 32px;

    align-items: center;
    justify-content: center;

    margin-bottom: 10px;

    border-radius: 7px;

    background: #eff6ff;

    color: #2563eb;

    font-weight: bold;
}

.quick-action strong {
    display: block;

    color: #111827;

    font-size: 12px;
}

.quick-action small {
    display: block;

    margin-top: 5px;

    color: #9ca3af;

    font-size: 10px;
}


/* =========================================================
   ACTIVITY
========================================================= */

.dashboard-activity-list {
    padding: 5px 20px;
}

.dashboard-activity-item {
    display: flex;

    align-items: center;

    gap: 12px;

    padding: 14px 0;

    border-bottom: 1px solid #f1f5f9;
}

.dashboard-activity-item:last-child {
    border-bottom: none;
}

.activity-icon {
    width: 32px;
    height: 32px;

    display: flex;
    align-items: center;
    justify-content: center;

    flex-shrink: 0;

    border-radius: 50%;

    background: #eff6ff;

    color: #2563eb;

    font-size: 12px;
}

.dashboard-activity-item strong {
    display: block;

    color: #374151;

    font-size: 12px;
}

.dashboard-activity-item small {
    display: block;

    margin-top: 3px;

    color: #9ca3af;

    font-size: 10px;
}


/* =========================================================
   ROUTERS
========================================================= */

.router-status-list {
    padding: 5px 20px;
}

.router-status-item {
    display: flex;

    align-items: center;
    justify-content: space-between;

    padding: 14px 0;

    border-bottom: 1px solid #f1f5f9;
}

.router-status-item:last-child {
    border-bottom: none;
}

.router-status-left {
    display: flex;

    align-items: center;

    gap: 10px;
}

.router-status-left strong {
    display: block;

    color: #374151;

    font-size: 12px;
}

.router-status-left small {
    display: block;

    margin-top: 3px;

    color: #9ca3af;

    font-size: 10px;
}

.router-dot {
    width: 9px;
    height: 9px;

    border-radius: 50%;

    flex-shrink: 0;
}

.router-dot.online {
    background: #22c55e;
}

.router-dot.offline {
    background: #ef4444;
}

.router-status-text {
    font-size: 10px;

    color: #6b7280;
}


/* =========================================================
   EMPTY STATE
========================================================= */

.empty-state {
    padding: 30px 20px !important;

    text-align: center;

    color: #9ca3af !important;

    font-size: 12px !important;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1100px) {

    .dashboard-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .dashboard-grid-three,
    .dashboard-grid-four {
        grid-template-columns: repeat(2, 1fr);
    }

    .quick-actions {
        grid-template-columns: repeat(2, 1fr);
    }
}


@media (max-width: 800px) {

    .dashboard-columns {
        grid-template-columns: 1fr;
    }

    .dashboard-page-header {
        align-items: flex-start;

        flex-direction: column;

        gap: 10px;
    }
}


@media (max-width: 600px) {

    .dashboard-grid,
    .dashboard-grid-three,
    .dashboard-grid-four {
        grid-template-columns: 1fr;
    }

    .quick-actions {
        grid-template-columns: 1fr;
    }

    .dashboard-page-header h1 {
        font-size: 21px;
    }

    .dashboard-stat-value {
        font-size: 19px;
    }

}

</style>
