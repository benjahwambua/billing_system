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

    <div class="sidebar-account-box">

        <?php if ($isHost): ?>

            <div class="sidebar-scope-label">
                PLATFORM ADMINISTRATION
            </div>

            <div class="sidebar-account-name">
                Flexihub Platform
            </div>

        <?php else: ?>

            <div class="sidebar-scope-label">
                ISP ACCOUNT
            </div>

            <div class="sidebar-account-name">
                <?= sidebar_e($sidebarTenantName) ?>
            </div>

        <?php endif; ?>

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
                OVERVIEW
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
                    PLATFORM
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
                    href="../subscriptions/index.php"
                    class="sidebar-menu-link <?= sidebarActive('/subscriptions/') ?>"
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
                    OPERATIONS
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
                    CONFIGURATION
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
                    CUSTOMERS
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
                    INTERNET SERVICES
                </div>

                <a
                    href="../internet_plans/index.php"
                    class="sidebar-menu-link <?= sidebarActive('/internet_plans/') ?>"
                >
                    <span class="sidebar-menu-icon">▤</span>
                    <span class="sidebar-menu-text">Internet Plans</span>
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
                    PPPOE
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
                    HOTSPOT
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
                    <span class="sidebar-menu-text">Packages</span>
                </a>

                <a
                    href="../hotspot/portal.php"
                    class="sidebar-menu-link <?= sidebarActive('/hotspot/portal.php') ?>"
                >
                    <span class="sidebar-menu-icon">◉</span>
                    <span class="sidebar-menu-text">Captive Portal</span>
                </a>

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
                    MIKROTIK & NETWORK
                </div>

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
                    BILLING & FINANCE
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
                    REPORTS & ANALYTICS
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
                    COMMUNICATION
                </div>

                <a
                    href="../communication/index.php"
                    class="sidebar-menu-link <?= sidebarActive('/communication/') ?>"
                >
                    <span class="sidebar-menu-icon">✉</span>
                    <span class="sidebar-menu-text">Messages</span>
                </a>

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
                    INTELLIGENCE
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
                    STAFF & ACCESS
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
                    SETTINGS
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

                <a
                    href="../settings/api.php"
                    class="sidebar-menu-link <?= sidebarActive('/settings/api.php') ?>"
                >
                    <span class="sidebar-menu-icon">⌘</span>
                    <span class="sidebar-menu-text">API & Integrations</span>
                </a>

            </div>

        <?php endif; ?>


        <!-- =====================================================
             ACCOUNT
        ====================================================== -->

        <div class="sidebar-menu-section sidebar-account-section">

            <div class="sidebar-menu-heading">
                ACCOUNT
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

</aside>


<style>

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

</style>