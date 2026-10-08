<?php
/**
 * Flexihub Billing System
 * Dynamic Role-Aware Sidebar
 *
 * Host users:
 *   - Manage the Flexihub SaaS platform
 *
 * Tenant users:
 *   - Manage their ISP/business operations
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userScope = $_SESSION['user_scope'] ?? 'tenant';
$isHost = ($userScope === 'host');

/**
 * Current request path.
 */
$currentPath = strtok($_SERVER['REQUEST_URI'] ?? '', '?');

/**
 * Check if a path is active.
 */
function sidebarIsActive($path)
{
    global $currentPath;

    return strpos($currentPath, $path) !== false;
}

/**
 * Return active class.
 */
function sidebarActive($path)
{
    return sidebarIsActive($path) ? 'active' : '';
}

/**
 * Return active class if any supplied paths match.
 */
function sidebarGroupActive($paths)
{
    foreach ($paths as $path) {
        if (sidebarIsActive($path)) {
            return 'open active-group';
        }
    }

    return '';
}


/**
 * Module heading icon set. Inline SVG keeps the sidebar self-contained and
 * avoids an external icon dependency while matching the Flexihub blue theme.
 */
if (!function_exists('sidebarModuleIcon')) {
    function sidebarModuleIcon($label)
    {
        $icons = [
            'OVERVIEW' => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M9 21v-6h6v6"/>',
            'PLATFORM' => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 8h10M7 12h10M7 16h6"/>',
            'OPERATIONS' => '<path d="M12 3v4M12 17v4M3 12h4M17 12h4"/><circle cx="12" cy="12" r="5"/>',
            'CONFIGURATION' => '<path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M18.4 5.6l-2.1 2.1M7.7 16.3l-2.1 2.1"/><circle cx="12" cy="12" r="4"/>',
            'CUSTOMERS' => '<path d="M16 20v-1.5a4.5 4.5 0 0 0-4.5-4.5h-3A4.5 4.5 0 0 0 4 18.5V20"/><circle cx="10" cy="7.5" r="3.5"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M17 14.5a4.5 4.5 0 0 1 4 4V20"/>',
            'INTERNET SERVICES' => '<circle cx="12" cy="12" r="8"/><path d="M4 12h16M12 4c2.2 2.2 3.2 5 3.2 8s-1 5.8-3.2 8c-2.2-2.2-3.2-5-3.2-8s1-5.8 3.2-8Z"/>',
            'PPPOE' => '<path d="M4 17V7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2Z"/><path d="M8 10h8M8 14h5"/>',
            'HOTSPOT' => '<path d="M5 9.5a10 10 0 0 1 14 0M8 13a6 6 0 0 1 8 0M11 16.5a2 2 0 0 1 2 0"/><circle cx="12" cy="18" r="1"/>',
            'NETWORK' => '<circle cx="12" cy="5" r="2.5"/><circle cx="5" cy="19" r="2.5"/><circle cx="19" cy="19" r="2.5"/><path d="M12 7.5v5M10 12.5 6.5 17M14 12.5l3.5 4.5"/>',
            'BILLING' => '<path d="M5 3h14v18l-3-2-4 2-4-2-3 2V3Z"/><path d="M8 8h8M8 12h8M8 16h4"/>',
            'REPORTING' => '<path d="M4 19V5M4 19h16"/><path d="m7 15 3-4 3 2 5-6"/>',
            'COMMUNICATION' => '<path d="M4 5h16v11H8l-4 4V5Z"/><path d="M8 9h8M8 12h5"/>',
            'INTELLIGENCE' => '<path d="M9 18h6M10 21h4"/><path d="M8 14a7 7 0 1 1 8 0c-1 1-1.5 2-1.5 3h-5c0-1-.5-2-1.5-3Z"/><path d="M12 7v3M9.5 8.5 11 10M14.5 8.5 13 10"/>',
            'STAFF & ACCESS' => '<circle cx="9" cy="8" r="3"/><path d="M3 20a6 6 0 0 1 12 0"/><path d="M16 11h5M18.5 8.5v5"/>',
            'SETTINGS' => '<path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M18.4 5.6l-2.1 2.1M7.7 16.3l-2.1 2.1"/><circle cx="12" cy="12" r="4"/>',
            'ACCOUNT' => '<circle cx="12" cy="8" r="3.5"/><path d="M5 21a7 7 0 0 1 14 0"/>',
        ];
        $key = strtoupper(trim((string)$label));
        $paths = $icons[$key] ?? '<circle cx="12" cy="12" r="8"/><path d="M12 8v8M8 12h8"/>';
        return '<span class="sidebar-heading-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'.$paths.'</svg></span>';
    }
}

/**
 * Escape sidebar text.
 */
if (!function_exists('sidebar_e')) {
    function sidebar_e($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Current username.
 */
$sidebarUsername = $_SESSION['username'] ?? 'User';

/**
 * Current tenant name.
 */
$sidebarTenantName = $_SESSION['tenant_name'] ?? 'ISP Account';

/**
 * Permission-aware sidebar filtering.
 */
if (!function_exists('sidebarCanHref')) {
    function sidebarCanHref($href)
    {
        if (!function_exists('userCan')) return true;
        $path = parse_url($href, PHP_URL_PATH) ?: $href;
        $path = str_replace('\\', '/', $path);
        $parts = array_values(array_filter(explode('/', trim($path, '/'))));
        $dir = strtolower($parts[count($parts)-2] ?? '');
        $file = strtolower($parts[count($parts)-1] ?? '');

        $map = [
            'dashboard'=>'dashboard','customers'=>'customers','internet_plans'=>'internet_plans',
            'internet_accounts'=>'internet_accounts','subscriptions'=>'subscriptions',
            'pppoe'=>'pppoe','pppoe_accounts'=>'pppoe','pppoe_servers'=>'pppoe','ip_pools'=>'pppoe',
            'routers'=>'network','network'=>'network','network_sites'=>'network',
            'hotspot'=>'hotspot','billing'=>'billing','invoices'=>'invoices','payments'=>'payments',
            'receipts'=>'billing','transactions'=>'billing','expenses'=>'billing','revenue'=>'billing',
            'reports'=>'reports','communication'=>'communication','ai'=>'ai','staffs'=>'staff',
            'users'=>'staff','roles'=>'staff','sessions'=>'staff','settings'=>'settings',
            'operations'=>'operations','wallet'=>'wallet','platform'=>'platform','tenants'=>'platform',
            'platform_plans'=>'platform','platform_subscriptions'=>'platform','platform_wallets'=>'platform','platform_transactions'=>'platform',
            'platform_revenue'=>'platform','platform_users'=>'platform','support'=>'platform',
            'system_events'=>'platform','audit_logs'=>'platform','platform_settings'=>'platform'
        ];
        $module = $map[$dir] ?? null;
        if (!$module || !is_callable('userCan')) return true;

        $action = 'view';
        if (in_array($file, ['add.php','create.php','new.php'], true)) $action='create';
        elseif (in_array($file, ['edit.php','update.php'], true)) $action='edit';
        return userCan($module, $action);
    }
}

ob_start();

?>

<aside class="sidebar" id="flexihubSidebar">

    <!-- =========================================================
         BRAND
    ========================================================== -->

    <div class="sidebar-brand">

        <a href="../dashboard/index.php" class="sidebar-brand-link">

            <div class="sidebar-brand-logo">
                F
            </div>

            <div class="sidebar-brand-text">
                <strong>Flexihub</strong>
                <span>Billing System</span>
            </div>

        </a>

    </div>


    <!-- =========================================================
         ACCOUNT SCOPE
    ========================================================== -->

    <div class="sidebar-user-profile">
        <div class="sidebar-user-avatar"><?= sidebar_e(strtoupper(substr($sidebarUsername ?: 'U', 0, 1))) ?></div>
        <div class="sidebar-user-info">
            <span class="sidebar-user-name"><?= sidebar_e($sidebarUsername) ?></span>
            <span class="sidebar-user-role"><?= sidebar_e($_SESSION['user_role'] ?? ($_SESSION['role'] ?? ($isHost ? 'Platform Administrator' : 'User'))) ?></span>
        </div>
    </div>

    <div class="sidebar-account-box">
        <div class="sidebar-scope-label"><?= $isHost ? 'PLATFORM ADMINISTRATION' : 'ISP ACCOUNT' ?></div>
        <div class="sidebar-account-name"><?= sidebar_e($isHost ? 'Flexihub Platform' : $sidebarTenantName) ?></div>
    </div>

    <!-- =========================================================
         NAVIGATION
    ========================================================== -->

    <nav class="flexihub-navigation">


        <!-- =====================================================
             COMMON
        ====================================================== -->

        <div class="sidebar-menu-section">

            <div class="sidebar-menu-heading">
                <?= sidebarModuleIcon('OVERVIEW') ?>
                <span class="sidebar-heading-label"><?= sidebar_e('OVERVIEW') ?></span>
            </div>

            <a
                href="../dashboard/index.php"
                class="sidebar-menu-link <?= sidebarActive('/dashboard/') ?>"
            >
                <span class="sidebar-menu-icon">⌂</span>
                <span class="sidebar-menu-text">Dashboard</span>
            </a>

        </div>


        <?php if ($isHost): ?>

            <!-- =================================================
                 HOST / PLATFORM
            ================================================== -->

            <div class="sidebar-menu-section">

                <div class="sidebar-menu-heading">
                <?= sidebarModuleIcon('PLATFORM') ?>
                <span class="sidebar-heading-label"><?= sidebar_e('PLATFORM') ?></span>
            </div>

                <a
                    href="../tenants/index.php"
                    class="sidebar-menu-link <?= sidebarActive('/tenants/') ?>"
                >
                    <span class="sidebar-menu-icon">▣</span>
                    <span class="sidebar-menu-text">Tenants</span>
                </a>

                <a
                    href="../platform_plans/index.php"
                    class="sidebar-menu-link <?= sidebarActive('/platform_plans/') ?>"
                >
                    <span class="sidebar-menu-icon">▤</span>
                    <span class="sidebar-menu-text">Subscription Plans</span>
                </a>

                <a
                    href="../platform_subscriptions/index.php"
                    class="sidebar-menu-link <?= sidebarActive('/platform_subscriptions/') ?>"
                >
                    <span class="sidebar-menu-icon">◆</span>
                    <span class="sidebar-menu-text">Tenant Subscriptions</span>
                </a>

                <a
                    href="../platform_wallets/index.php"
                    class="sidebar-menu-link <?= sidebarActive('/platform_wallets/') ?>"
                >
                    <span class="sidebar-menu-icon">▰</span>
                    <span class="sidebar-menu-text">Tenant Wallets</span>
                </a>

                <a
                    href="../platform_transactions/index.php"
                    class="sidebar-menu-link <?= sidebarActive('/platform_transactions/') ?>"
                >
                    <span class="sidebar-menu-icon">⇄</span>
                    <span class="sidebar-menu-text">Platform Transactions</span>
                </a>

                <a
                    href="../platform_revenue/index.php"
                    class="sidebar-menu-link <?= sidebarActive('/platform_revenue/') ?>"
                >
                    <span class="sidebar-menu-icon">◈</span>
                    <span class="sidebar-menu-text">Platform Revenue</span>
                </a>

            </div>


            <!-- =================================================
                 HOST OPERATIONS
            ================================================== -->

            <div class="sidebar-menu-section">

                <div class="sidebar-menu-heading">
                <?= sidebarModuleIcon('OPERATIONS') ?>
                <span class="sidebar-heading-label"><?= sidebar_e('OPERATIONS') ?></span>
            </div>

                <a
                    href="../platform_users/index.php"
                    class="sidebar-menu-link <?= sidebarActive('/platform_users/') ?>"
                >
                    <span class="sidebar-menu-icon">♙</span>
                    <span class="sidebar-menu-text">Platform Users</span>
                </a>

                <a
                    href="../support/index.php"
                    class="sidebar-menu-link <?= sidebarActive('/support/') ?>"
                >
                    <span class="sidebar-menu-icon">?</span>
                    <span class="sidebar-menu-text">Support</span>
                </a>

                <a
                    href="../system_events/index.php"
                    class="sidebar-menu-link <?= sidebarActive('/system_events/') ?>"
                >
                    <span class="sidebar-menu-icon">!</span>
                    <span class="sidebar-menu-text">System Events</span>
                </a>

                <a
                    href="../audit_logs/index.php"
                    class="sidebar-menu-link <?= sidebarActive('/audit_logs/') ?>"
                >
                    <span class="sidebar-menu-icon">▤</span>
                    <span class="sidebar-menu-text">Audit Logs</span>
                </a>

            </div>


            <!-- =================================================
                 HOST SETTINGS
            ================================================== -->

            <div class="sidebar-menu-section">

                <div class="sidebar-menu-heading">
                <?= sidebarModuleIcon('CONFIGURATION') ?>
                <span class="sidebar-heading-label"><?= sidebar_e('CONFIGURATION') ?></span>
            </div>

                <a
                    href="../platform_settings/index.php"
                    class="sidebar-menu-link <?= sidebarActive('/platform_settings/') ?>"
                >
                    <span class="sidebar-menu-icon">⚙</span>
                    <span class="sidebar-menu-text">Platform Settings</span>
                </a>

            </div>


        <?php else: ?>


            <!-- =================================================
                 CUSTOMERS
            ================================================== -->

            <div class="sidebar-menu-section">

                <div class="sidebar-menu-heading">
                <?= sidebarModuleIcon('CUSTOMERS') ?>
                <span class="sidebar-heading-label"><?= sidebar_e('CUSTOMERS') ?></span>
            </div>

                <a
                    href="../customers/index.php"
                    class="sidebar-menu-link <?= sidebarActive('/customers/') ?>"
                >
                    <span class="sidebar-menu-icon">♙</span>
                    <span class="sidebar-menu-text">All Customers</span>
                </a>

                <a
                    href="../customers/add.php"
                    class="sidebar-menu-link <?= sidebarActive('/customers/add.php') ?>"
                >
                    <span class="sidebar-menu-icon">+</span>
                    <span class="sidebar-menu-text">Add Customer</span>
                </a>

                <a
                    href="../customers/active.php"
                    class="sidebar-menu-link <?= sidebarActive('/customers/active.php') ?>"
                >
                    <span class="sidebar-menu-icon">●</span>
                    <span class="sidebar-menu-text">Active Customers</span>
                </a>

                <a
                    href="../customers/suspended.php"
                    class="sidebar-menu-link <?= sidebarActive('/customers/suspended.php') ?>"
                >
                    <span class="sidebar-menu-icon">⏸</span>
                    <span class="sidebar-menu-text">Suspended Customers</span>
                </a>

                <a
                    href="../customers/expired.php"
                    class="sidebar-menu-link <?= sidebarActive('/customers/expired.php') ?>"
                >
                    <span class="sidebar-menu-icon">○</span>
                    <span class="sidebar-menu-text">Expired Customers</span>
                </a>

                <a
                    href="../customers/groups.php"
                    class="sidebar-menu-link <?= sidebarActive('/customers/groups.php') ?>"
                >
                    <span class="sidebar-menu-icon">♙</span>
                    <span class="sidebar-menu-text">Customer Groups</span>
                </a>

            </div>


            <!-- =================================================
                 INTERNET SERVICES
            ================================================== -->

            <div class="sidebar-menu-section">

                <div class="sidebar-menu-heading">
                <?= sidebarModuleIcon('INTERNET SERVICES') ?>
                <span class="sidebar-heading-label"><?= sidebar_e('INTERNET SERVICES') ?></span>
            </div>

                <a
                    href="../internet_plans/index.php"
                    class="sidebar-menu-link <?= sidebarActive('/internet_plans/') ?>"
                >
                    <span class="sidebar-menu-icon">▤</span>
                    <span class="sidebar-menu-text">PPPoE Plans</span>
                </a>

                <a
                    href="../internet_accounts/index.php"
                    class="sidebar-menu-link <?= sidebarActive('/internet_accounts/') ?>"
                >
                    <span class="sidebar-menu-icon">◎</span>
                    <span class="sidebar-menu-text">Customer Accounts</span>
                </a>

                <a
                    href="../subscriptions/index.php"
                    class="sidebar-menu-link <?= sidebarActive('/subscriptions/') ?>"
                >
                    <span class="sidebar-menu-icon">◆</span>
                    <span class="sidebar-menu-text">Subscriptions</span>
                </a>

                <a
                    href="../subscriptions/renewals.php"
                    class="sidebar-menu-link <?= sidebarActive('/subscriptions/renewals.php') ?>"
                >
                    <span class="sidebar-menu-icon">↻</span>
                    <span class="sidebar-menu-text">Renewals</span>
                </a>

                <a
                    href="../subscriptions/expiring.php"
                    class="sidebar-menu-link <?= sidebarActive('/subscriptions/expiring.php') ?>"
                >
                    <span class="sidebar-menu-icon">◷</span>
                    <span class="sidebar-menu-text">Expiring Services</span>
                </a>

            </div>


            <!-- =================================================
                 PPPoE
            ================================================== -->

            <div class="sidebar-menu-section">

                <div class="sidebar-menu-heading">
                <?= sidebarModuleIcon('PPPOE') ?>
                <span class="sidebar-heading-label"><?= sidebar_e('PPPOE') ?></span>
            </div>

                <a
                    href="../pppoe/accounts.php"
                    class="sidebar-menu-link <?= sidebarActive('/pppoe/accounts.php') ?>"
                >
                    <span class="sidebar-menu-icon">♙</span>
                    <span class="sidebar-menu-text">PPPoE Accounts</span>
                </a>

                <a
                    href="../pppoe/servers.php"
                    class="sidebar-menu-link <?= sidebarActive('/pppoe/servers.php') ?>"
                >
                    <span class="sidebar-menu-icon">▣</span>
                    <span class="sidebar-menu-text">PPPoE Servers</span>
                </a>

                <a
                    href="../pppoe/profiles.php"
                    class="sidebar-menu-link <?= sidebarActive('/pppoe/profiles.php') ?>"
                >
                    <span class="sidebar-menu-icon">▤</span>
                    <span class="sidebar-menu-text">Profiles</span>
                </a>

                <a
                    href="../pppoe/ip_pools.php"
                    class="sidebar-menu-link <?= sidebarActive('/pppoe/ip_pools.php') ?>"
                >
                    <span class="sidebar-menu-icon">⊙</span>
                    <span class="sidebar-menu-text">IP Pools</span>
                </a>

                <a
                    href="../pppoe/sessions.php"
                    class="sidebar-menu-link <?= sidebarActive('/pppoe/sessions.php') ?>"
                >
                    <span class="sidebar-menu-icon">↔</span>
                    <span class="sidebar-menu-text">Active Sessions</span>
                </a>

                <a
                    href="../pppoe/disconnected.php"
                    class="sidebar-menu-link <?= sidebarActive('/pppoe/disconnected.php') ?>"
                >
                    <span class="sidebar-menu-icon">↯</span>
                    <span class="sidebar-menu-text">Disconnected Sessions</span>
                </a>

            </div>


            <!-- =================================================
                 HOTSPOT
            ================================================== -->

            <div class="sidebar-menu-section">

                <div class="sidebar-menu-heading">
                <?= sidebarModuleIcon('HOTSPOT') ?>
                <span class="sidebar-heading-label"><?= sidebar_e('HOTSPOT') ?></span>
            </div>

                <a
                    href="../hotspot/index.php"
                    class="sidebar-menu-link <?= sidebarActive('/hotspot/index.php') ?>"
                >
                    <span class="sidebar-menu-icon">◉</span>
                    <span class="sidebar-menu-text">Hotspot Dashboard</span>
                </a>

                <a
                    href="../hotspot/packages.php"
                    class="sidebar-menu-link <?= sidebarActive('/hotspot/packages.php') ?>"
                >
                    <span class="sidebar-menu-icon">▤</span>
                    <span class="sidebar-menu-text">Hotspot Plans</span>
                </a>

                <a
                    href="../hotspot/portal.php"
                    class="sidebar-menu-link <?= sidebarActive('/hotspot/portal.php') ?>"
                >
                    <span class="sidebar-menu-icon">◉</span>
                    <span class="sidebar-menu-text">Captive Portal</span>
                </a>

                <a href="../hotspot/vouchers.php" class="sidebar-menu-link <?= sidebarActive('/hotspot/vouchers.php') ?>"><span class="sidebar-menu-icon">◆</span><span class="sidebar-menu-text">Vouchers</span></a>

                <a
                    href="../hotspot/access_codes.php"
                    class="sidebar-menu-link <?= sidebarActive('/hotspot/access_codes.php') ?>"
                >
                    <span class="sidebar-menu-icon">#</span>
                    <span class="sidebar-menu-text">Access Codes</span>
                </a>

                <a
                    href="../hotspot/sessions.php"
                    class="sidebar-menu-link <?= sidebarActive('/hotspot/sessions.php') ?>"
                >
                    <span class="sidebar-menu-icon">↔</span>
                    <span class="sidebar-menu-text">Sessions</span>
                </a>

                <a
                    href="../hotspot/sales.php"
                    class="sidebar-menu-link <?= sidebarActive('/hotspot/sales.php') ?>"
                >
                    <span class="sidebar-menu-icon">◆</span>
                    <span class="sidebar-menu-text">Hotspot Sales</span>
                </a>

            </div>


            <!-- =================================================
                 NETWORK / MIKROTIK
            ================================================== -->

            <div class="sidebar-menu-section">

                <div class="sidebar-menu-heading">
                <?= sidebarModuleIcon('MIKROTIK & NETWORK') ?>
                <span class="sidebar-heading-label"><?= sidebar_e('MIKROTIK & NETWORK') ?></span>
            </div>

                <a href="../network/map.php" class="sidebar-menu-link <?= sidebarActive('/network/map.php') ?>"><span class="sidebar-menu-icon">⌁</span><span class="sidebar-menu-text">Network Map</span></a>
                <a href="../network/olt_onu.php" class="sidebar-menu-link <?= sidebarActive('/network/olt_onu.php') ?>"><span class="sidebar-menu-icon">▣</span><span class="sidebar-menu-text">OLTs &amp; ONUs</span></a>
                <a
                    href="../routers/index.php"
                    class="sidebar-menu-link <?= sidebarActive('/routers/') ?>"
                >
                    <span class="sidebar-menu-icon">▣</span>
                    <span class="sidebar-menu-text">Routers</span>
                </a>

                <a
                    href="../network_sites/index.php"
                    class="sidebar-menu-link <?= sidebarActive('/network_sites/') ?>"
                >
                    <span class="sidebar-menu-icon">⌖</span>
                    <span class="sidebar-menu-text">Network Sites</span>
                </a>

                <a
                    href="../network/status.php"
                    class="sidebar-menu-link <?= sidebarActive('/network/status.php') ?>"
                >
                    <span class="sidebar-menu-icon">●</span>
                    <span class="sidebar-menu-text">Router Status</span>
                </a>

                <a
                    href="../network/interfaces.php"
                    class="sidebar-menu-link <?= sidebarActive('/network/interfaces.php') ?>"
                >
                    <span class="sidebar-menu-icon">⌁</span>
                    <span class="sidebar-menu-text">Interfaces</span>
                </a>

                <a
                    href="../network/monitoring.php"
                    class="sidebar-menu-link <?= sidebarActive('/network/monitoring.php') ?>"
                >
                    <span class="sidebar-menu-icon">◫</span>
                    <span class="sidebar-menu-text">Network Monitoring</span>
                </a>

                <a
                    href="../network/online_users.php"
                    class="sidebar-menu-link <?= sidebarActive('/network/online_users.php') ?>"
                >
                    <span class="sidebar-menu-icon">↔</span>
                    <span class="sidebar-menu-text">Online Users</span>
                </a>

            </div>


            <!-- =================================================
                 BILLING
            ================================================== -->

            <div class="sidebar-menu-section">

                <div class="sidebar-menu-heading">
                <?= sidebarModuleIcon('BILLING & FINANCE') ?>
                <span class="sidebar-heading-label"><?= sidebar_e('BILLING & FINANCE') ?></span>
            </div>

                <a
                    href="../billing/index.php"
                    class="sidebar-menu-link <?= sidebarActive('/billing/') ?>"
                >
                    <span class="sidebar-menu-icon">▤</span>
                    <span class="sidebar-menu-text">Billing</span>
                </a>

                <a
                    href="../invoices/index.php"
                    class="sidebar-menu-link <?= sidebarActive('/invoices/') ?>"
                >
                    <span class="sidebar-menu-icon">▧</span>
                    <span class="sidebar-menu-text">Invoices</span>
                </a>

                <a
                    href="../payments/index.php"
                    class="sidebar-menu-link <?= sidebarActive('/payments/') ?>"
                >
                    <span class="sidebar-menu-icon">◆</span>
                    <span class="sidebar-menu-text">Payments</span>
                </a>

                <a
                    href="../receipts/index.php"
                    class="sidebar-menu-link <?= sidebarActive('/receipts/') ?>"
                >
                    <span class="sidebar-menu-icon">▤</span>
                    <span class="sidebar-menu-text">Receipts</span>
                </a>

                <a
                    href="../transactions/index.php"
                    class="sidebar-menu-link <?= sidebarActive('/transactions/') ?>"
                >
                    <span class="sidebar-menu-icon">⇄</span>
                    <span class="sidebar-menu-text">Transactions</span>
                </a>

                <a
                    href="../billing/outstanding.php"
                    class="sidebar-menu-link <?= sidebarActive('/billing/outstanding.php') ?>"
                >
                    <span class="sidebar-menu-icon">!</span>
                    <span class="sidebar-menu-text">Outstanding</span>
                </a>

                <a href="../payments/failed_mpesa.php" class="sidebar-menu-link <?= sidebarActive('/payments/failed_mpesa.php') ?>"><span class="sidebar-menu-icon">!</span><span class="sidebar-menu-text">Failed M-Pesa</span></a>

                <a
                    href="../expenses/index.php"
                    class="sidebar-menu-link <?= sidebarActive('/expenses/') ?>"
                >
                    <span class="sidebar-menu-icon">−</span>
                    <span class="sidebar-menu-text">Expenses</span>
                </a>

                <a
                    href="../revenue/index.php"
                    class="sidebar-menu-link <?= sidebarActive('/revenue/') ?>"
                >
                    <span class="sidebar-menu-icon">◈</span>
                    <span class="sidebar-menu-text">Revenue</span>
                </a>

            </div>


            <!-- =================================================
                 REPORTS
            ================================================== -->

            <div class="sidebar-menu-section">

                <div class="sidebar-menu-heading">
                <?= sidebarModuleIcon('REPORTS & ANALYTICS') ?>
                <span class="sidebar-heading-label"><?= sidebar_e('REPORTS & ANALYTICS') ?></span>
            </div>

                <a
                    href="../reports/index.php"
                    class="sidebar-menu-link <?= sidebarActive('/reports/index.php') ?>"
                >
                    <span class="sidebar-menu-icon">▥</span>
                    <span class="sidebar-menu-text">Reports Dashboard</span>
                </a>

                <a
                    href="../reports/revenue.php"
                    class="sidebar-menu-link <?= sidebarActive('/reports/revenue.php') ?>"
                >
                    <span class="sidebar-menu-icon">◈</span>
                    <span class="sidebar-menu-text">Revenue</span>
                </a>

                <a
                    href="../reports/collections.php"
                    class="sidebar-menu-link <?= sidebarActive('/reports/collections.php') ?>"
                >
                    <span class="sidebar-menu-icon">◆</span>
                    <span class="sidebar-menu-text">Collections</span>
                </a>

                <a
                    href="../reports/customers.php"
                    class="sidebar-menu-link <?= sidebarActive('/reports/customers.php') ?>"
                >
                    <span class="sidebar-menu-icon">♙</span>
                    <span class="sidebar-menu-text">Customers</span>
                </a>

                <a
                    href="../reports/usage.php"
                    class="sidebar-menu-link <?= sidebarActive('/reports/usage.php') ?>"
                >
                    <span class="sidebar-menu-icon">◫</span>
                    <span class="sidebar-menu-text">Usage</span>
                </a>

                <a
                    href="../reports/network.php"
                    class="sidebar-menu-link <?= sidebarActive('/reports/network.php') ?>"
                >
                    <span class="sidebar-menu-icon">⌁</span>
                    <span class="sidebar-menu-text">Network</span>
                </a>

                <a
                    href="../reports/financial.php"
                    class="sidebar-menu-link <?= sidebarActive('/reports/financial.php') ?>"
                >
                    <span class="sidebar-menu-icon">▥</span>
                    <span class="sidebar-menu-text">Financial Reports</span>
                </a>

            </div>


            <!-- =================================================
                 COMMUNICATION
            ================================================== -->

            <div class="sidebar-menu-section">

                <div class="sidebar-menu-heading">
                <?= sidebarModuleIcon('COMMUNICATION') ?>
                <span class="sidebar-heading-label"><?= sidebar_e('COMMUNICATION') ?></span>
            </div>

                <a
                    href="../communication/index.php"
                    class="sidebar-menu-link <?= sidebarActive('/communication/') ?>"
                >
                    <span class="sidebar-menu-icon">✉</span>
                    <span class="sidebar-menu-text">Messages</span>
                </a>

                <a href="../communication/gateway.php" class="sidebar-menu-link <?= sidebarActive('/communication/gateway.php') ?>"><span class="sidebar-menu-icon">▣</span><span class="sidebar-menu-text">SMS Gateway</span></a>
                <a href="../communication/customer_chat.php" class="sidebar-menu-link <?= sidebarActive('/communication/customer_chat.php') ?>"><span class="sidebar-menu-icon">◉</span><span class="sidebar-menu-text">Customer Chat</span></a>

                <a
                    href="../communication/sms.php"
                    class="sidebar-menu-link <?= sidebarActive('/communication/sms.php') ?>"
                >
                    <span class="sidebar-menu-icon">▣</span>
                    <span class="sidebar-menu-text">SMS</span>
                </a>

                <a
                    href="../communication/whatsapp.php"
                    class="sidebar-menu-link <?= sidebarActive('/communication/whatsapp.php') ?>"
                >
                    <span class="sidebar-menu-icon">◉</span>
                    <span class="sidebar-menu-text">WhatsApp</span>
                </a>

                <a
                    href="../communication/email.php"
                    class="sidebar-menu-link <?= sidebarActive('/communication/email.php') ?>"
                >
                    <span class="sidebar-menu-icon">✉</span>
                    <span class="sidebar-menu-text">Email</span>
                </a>

                <a
                    href="../communication/templates.php"
                    class="sidebar-menu-link <?= sidebarActive('/communication/templates.php') ?>"
                >
                    <span class="sidebar-menu-icon">▤</span>
                    <span class="sidebar-menu-text">Message Templates</span>
                </a>

            </div>


            <!-- =================================================
                 AI
            ================================================== -->

            <div class="sidebar-menu-section">

                <div class="sidebar-menu-heading">
                <?= sidebarModuleIcon('INTELLIGENCE') ?>
                <span class="sidebar-heading-label"><?= sidebar_e('INTELLIGENCE') ?></span>
            </div>

                <a
                    href="../ai/index.php"
                    class="sidebar-menu-link <?= sidebarActive('/ai/index.php') ?>"
                >
                    <span class="sidebar-menu-icon">✦</span>
                    <span class="sidebar-menu-text">AI Assistant</span>
                </a>

                <a
                    href="../ai/conversations.php"
                    class="sidebar-menu-link <?= sidebarActive('/ai/conversations.php') ?>"
                >
                    <span class="sidebar-menu-icon">◌</span>
                    <span class="sidebar-menu-text">Conversations</span>
                </a>

                <a
                    href="../ai/actions.php"
                    class="sidebar-menu-link <?= sidebarActive('/ai/actions.php') ?>"
                >
                    <span class="sidebar-menu-icon">✓</span>
                    <span class="sidebar-menu-text">AI Actions</span>
                </a>

                <a
                    href="../ai/usage.php"
                    class="sidebar-menu-link <?= sidebarActive('/ai/usage.php') ?>"
                >
                    <span class="sidebar-menu-icon">◫</span>
                    <span class="sidebar-menu-text">AI Usage</span>
                </a>

            </div>


            <!-- =================================================
                 STAFF
            ================================================== -->

            <div class="sidebar-menu-section">

                <div class="sidebar-menu-heading">
                <?= sidebarModuleIcon('STAFF & ACCESS') ?>
                <span class="sidebar-heading-label"><?= sidebar_e('STAFF & ACCESS') ?></span>
            </div>

                <a
                    href="../staffs/index.php"
                    class="sidebar-menu-link <?= sidebarActive('/staffs/') ?>"
                >
                    <span class="sidebar-menu-icon">♙</span>
                    <span class="sidebar-menu-text">Staff</span>
                </a>

                <a
                    href="../users/index.php"
                    class="sidebar-menu-link <?= sidebarActive('/users/') ?>"
                >
                    <span class="sidebar-menu-icon">♙</span>
                    <span class="sidebar-menu-text">Users</span>
                </a>

                <a
                    href="../roles/index.php"
                    class="sidebar-menu-link <?= sidebarActive('/roles/') ?>"
                >
                    <span class="sidebar-menu-icon">◆</span>
                    <span class="sidebar-menu-text">Roles & Permissions</span>
                </a>

                <a
                    href="../sessions/index.php"
                    class="sidebar-menu-link <?= sidebarActive('/sessions/') ?>"
                >
                    <span class="sidebar-menu-icon">◉</span>
                    <span class="sidebar-menu-text">Login Sessions</span>
                </a>

            </div>


            <!-- =================================================
                 SETTINGS
            ================================================== -->

            <div class="sidebar-menu-section">

                <div class="sidebar-menu-heading">
                <?= sidebarModuleIcon('SETTINGS') ?>
                <span class="sidebar-heading-label"><?= sidebar_e('SETTINGS') ?></span>
            </div>

                <a
                    href="../settings/index.php"
                    class="sidebar-menu-link <?= sidebarActive('/settings/') ?>"
                >
                    <span class="sidebar-menu-icon">⚙</span>
                    <span class="sidebar-menu-text">Business Profile</span>
                </a>

                <a
                    href="../settings/billing.php"
                    class="sidebar-menu-link <?= sidebarActive('/settings/billing.php') ?>"
                >
                    <span class="sidebar-menu-icon">▤</span>
                    <span class="sidebar-menu-text">Billing Settings</span>
                </a>

                <a
                    href="../settings/payments.php"
                    class="sidebar-menu-link <?= sidebarActive('/settings/payments.php') ?>"
                >
                    <span class="sidebar-menu-icon">◆</span>
                    <span class="sidebar-menu-text">Payment Settings</span>
                </a>

                <a
                    href="../settings/mikrotik.php"
                    class="sidebar-menu-link <?= sidebarActive('/settings/mikrotik.php') ?>"
                >
                    <span class="sidebar-menu-icon">▣</span>
                    <span class="sidebar-menu-text">MikroTik Settings</span>
                </a>

                <a
                    href="../settings/notifications.php"
                    class="sidebar-menu-link <?= sidebarActive('/settings/notifications.php') ?>"
                >
                    <span class="sidebar-menu-icon">●</span>
                    <span class="sidebar-menu-text">Notifications</span>
                </a>

                <a
                    href="../settings/captive_portal.php"
                    class="sidebar-menu-link <?= sidebarActive('/settings/captive_portal.php') ?>"
                >
                    <span class="sidebar-menu-icon">◉</span>
                    <span class="sidebar-menu-text">Captive Portal</span>
                </a>

                <a href="../settings/themes.php" class="sidebar-menu-link <?= sidebarActive('/settings/themes.php') ?>"><span class="sidebar-menu-icon">◈</span><span class="sidebar-menu-text">Themes &amp; Branding</span></a>

                <a
                    href="../settings/api.php"
                    class="sidebar-menu-link <?= sidebarActive('/settings/api.php') ?>"
                >
                    <span class="sidebar-menu-icon">⌘</span>
                    <span class="sidebar-menu-text">API & Integrations</span>
                </a>

            </div>

            <div class="sidebar-menu-section">
                <div class="sidebar-menu-heading"><?= sidebarModuleIcon('OPERATIONS') ?><span class="sidebar-heading-label">OPERATIONS</span></div>
                <a href="../operations/agent_sales.php" class="sidebar-menu-link <?= sidebarActive('/operations/agent_sales.php') ?>"><span class="sidebar-menu-icon">♙</span><span class="sidebar-menu-text">Agent Sales</span></a>
                <a href="../operations/adverts.php" class="sidebar-menu-link <?= sidebarActive('/operations/adverts.php') ?>"><span class="sidebar-menu-icon">▤</span><span class="sidebar-menu-text">Adverts</span></a>
            </div>


        <?php endif; ?>        <?php endif; ?>


        <!-- =====================================================
             ACCOUNT
        ====================================================== -->

        <div class="sidebar-menu-section sidebar-account-section">

            <div class="sidebar-menu-heading">
                <?= sidebarModuleIcon('ACCOUNT') ?>
                <span class="sidebar-heading-label"><?= sidebar_e('ACCOUNT') ?></span>
            </div>

            <a
                href="../profile/index.php"
                class="sidebar-menu-link <?= sidebarActive('/profile/') ?>"
            >
                <span class="sidebar-menu-icon">♙</span>
                <span class="sidebar-menu-text">My Profile</span>
            </a>

            <a
                href="../auth/logout.php"
                class="sidebar-menu-link sidebar-logout"
            >
                <span class="sidebar-menu-icon">↪</span>
                <span class="sidebar-menu-text">Logout</span>
            </a>

        </div>

    </nav>

<?php
$sidebarHtml = ob_get_clean();
$sidebarHtml = preg_replace_callback("~<a\\\\b[^>]*href=([\\\"'])(.*?)\\\\1[^>]*>.*?<\\\\/a>~is", function ($m) {
    return sidebarCanHref($m[2]) ? $m[0] : '';
}, $sidebarHtml);
echo $sidebarHtml;
?>

</aside>


<style>
.sidebar-user-profile{margin:14px 12px 10px;padding:12px;border:1px solid rgba(148,163,184,.12);border-radius:12px;background:rgba(255,255,255,.035);display:flex;align-items:center;gap:10px}.sidebar-user-avatar{width:36px;height:36px;flex:0 0 36px;border-radius:10px;background:linear-gradient(135deg,#1d4ed8,#3b82f6);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:13px}.sidebar-user-info{min-width:0}.sidebar-user-name{display:block;color:#f8fafc;font-size:12px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.sidebar-user-role{display:block;color:#64748b;font-size:10px;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}

/* ============================================================
   FLEXIHUB SIDEBAR
============================================================ */

.sidebar {
    width: 260px;
    min-height: 100vh;

    position: fixed;
    top: 0;
    left: 0;

    z-index: 1000;

    background: #111827;
    color: #fff;

    overflow-y: auto;

    box-shadow: 2px 0 12px rgba(0, 0, 0, 0.08);
}


/* ============================================================
   BRAND
============================================================ */

.sidebar-brand {
    padding: 20px;
    border-bottom: 1px solid rgba(255,255,255,0.08);
}

.sidebar-brand-link {
    display: flex;
    align-items: center;
    gap: 12px;

    color: #fff;
    text-decoration: none;
}

.sidebar-brand-logo {
    width: 40px;
    height: 40px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 9px;

    background: #fff;
    color: #111827;

    font-size: 20px;
    font-weight: 800;
}

.sidebar-brand-text {
    display: flex;
    flex-direction: column;
}

.sidebar-brand-text strong {
    font-size: 18px;
    line-height: 1.2;
}

.sidebar-brand-text span {
    margin-top: 3px;

    color: #9ca3af;

    font-size: 10px;
}


/* ============================================================
   ACCOUNT / SCOPE
============================================================ */

.sidebar-account-box {
    margin: 14px 12px;

    padding: 12px;

    border-radius: 7px;

    background: rgba(255,255,255,0.05);
}

.sidebar-scope-label {
    margin-bottom: 5px;

    color: #9ca3af;

    font-size: 9px;
    font-weight: 700;

    letter-spacing: 0.8px;
}

.sidebar-account-name {
    color: #f3f4f6;

    font-size: 12px;
    font-weight: 600;
}


/* ============================================================
   NAVIGATION
============================================================ */

.flexihub-navigation {
    padding: 0 10px 30px;
}

.sidebar-menu-section {
    margin-bottom: 20px;
}

/* Hide permission-filtered sections that have no remaining links. */
.sidebar-menu-section:not(:has(.sidebar-menu-link)) {
    display: none;
}

.sidebar-menu-heading {
    padding: 0 10px 8px;

    color: #6b7280;

    font-size: 9px;
    font-weight: 700;

    letter-spacing: 1px;
}


/* ============================================================
   MENU LINK
============================================================ */

.sidebar-menu-link {
    display: flex;
    align-items: center;

    gap: 11px;

    min-height: 39px;

    margin-bottom: 2px;
    padding: 9px 11px;

    border-radius: 6px;

    color: #d1d5db;

    text-decoration: none;

    font-size: 13px;

    transition:
        background 0.15s ease,
        color 0.15s ease;
}

.sidebar-menu-link:hover {
    background: rgba(255,255,255,0.07);
    color: #fff;
}

.sidebar-menu-link.active {
    background: rgba(255,255,255,0.13);
    color: #fff;

    font-weight: 600;
}

.sidebar-menu-icon {
    width: 20px;

    flex-shrink: 0;

    text-align: center;

    color: #9ca3af;

    font-size: 14px;
}

.sidebar-menu-link:hover .sidebar-menu-icon,
.sidebar-menu-link.active .sidebar-menu-icon {
    color: #fff;
}

.sidebar-menu-text {
    white-space: nowrap;
}


/* ============================================================
   LOGOUT
============================================================ */

.sidebar-logout {
    color: #fca5a5;
}

.sidebar-logout .sidebar-menu-icon {
    color: #fca5a5;
}

.sidebar-logout:hover {
    color: #fff;
    background: rgba(239,68,68,0.12);
}


/* ============================================================
   SCROLLBAR
============================================================ */

.sidebar::-webkit-scrollbar {
    width: 5px;
}

.sidebar::-webkit-scrollbar-track {
    background: transparent;
}

.sidebar::-webkit-scrollbar-thumb {
    background: rgba(255,255,255,0.15);
    border-radius: 10px;
}


/* ============================================================
   RESPONSIVE
============================================================ */

@media (max-width: 900px) {

    .sidebar {
        width: 230px;
    }

}

@media (max-width: 700px) {

    .sidebar {
        position: relative;

        width: 100%;

        min-height: auto;
    }

    .navigation {
        max-height: none;
    }

}


/* ============================================================
   FLEXIHUB COLLAPSIBLE MODULE NAVIGATION
   Inspired by the structured module navigation used in the
   Hospital System while retaining Flexihub's dark/blue theme.
============================================================ */
.sidebar-menu-section.has-dropdown .sidebar-menu-heading{
    position:relative;
    display:flex;
    align-items:center;
    justify-content:space-between;
    cursor:pointer;
    user-select:none;
    padding:9px 10px;
    margin-bottom:3px;
    border-radius:8px;
    transition:background .18s ease,color .18s ease;
}
.sidebar-menu-section.has-dropdown .sidebar-menu-heading:hover{
    background:rgba(37,99,235,.08);
    color:#cbd5e1;
}
.sidebar-menu-heading .sidebar-chevron{
    width:20px;
    height:20px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    color:#64748b;
    font-size:10px;
    transition:transform .2s ease,color .2s ease;
}
.sidebar-menu-section.has-dropdown.open .sidebar-chevron{
    transform:rotate(180deg);
    color:#60a5fa;
}
.sidebar-submenu{
    display:grid;
    grid-template-rows:0fr;
    transition:grid-template-rows .22s ease;
}
.sidebar-submenu-inner{
    min-height:0;
    overflow:hidden;
}
.sidebar-menu-section.has-dropdown.open .sidebar-submenu{
    grid-template-rows:1fr;
}
.sidebar-submenu .sidebar-menu-link{
    margin-left:5px;
    min-height:37px;
    padding:8px 11px;
}
.sidebar-menu-section.has-dropdown.active-group > .sidebar-menu-heading{
    color:#93c5fd;
}
.sidebar-menu-section.has-dropdown.active-group > .sidebar-menu-heading .sidebar-chevron{
    color:#60a5fa;
}
@media(max-width:700px){
    .sidebar-menu-section.has-dropdown .sidebar-menu-heading{padding:10px}
    .sidebar-submenu .sidebar-menu-link{margin-left:0}
}




/* Module heading icons */
.sidebar-menu-heading{gap:8px!important;}
.sidebar-heading-icon{width:19px;height:19px;display:inline-flex;align-items:center;justify-content:center;flex:0 0 19px;color:#64748b;}
.sidebar-heading-icon svg{width:16px;height:16px;display:block;}
.sidebar-menu-section.has-dropdown .sidebar-menu-heading:hover .sidebar-heading-icon,
.sidebar-menu-section.active-group .sidebar-heading-icon{color:#60a5fa;}
.sidebar-heading-label{flex:1;min-width:0;}
</style>