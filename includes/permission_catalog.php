<?php
/**
 * Flexihub permission catalogue.
 *
 * Keep tenant module keys and route aliases in one place so the role editor
 * and navigation cannot drift apart. This catalogue describes permission
 * names only; it does not grant access or replace server-side authorization.
 */

if (!function_exists('flexihubTenantPermissionModules')) {
    function flexihubTenantPermissionModules()
    {
        return [
            'dashboard' => 'Dashboard',
            'customers' => 'Customers',
            'internet_plans' => 'Internet Plans',
            'internet_accounts' => 'Internet Accounts',
            'subscriptions' => 'Subscriptions',
            'routers' => 'Routers',
            'pppoe' => 'PPPoE',
            'pppoe_accounts' => 'PPPoE Accounts',
            'pppoe_servers' => 'PPPoE Servers',
            'hotspot' => 'Hotspot',
            'network' => 'Network Monitoring',
            'network_sites' => 'Network Sites',
            'ip_pools' => 'IP Pools',
            'billing' => 'Billing',
            'invoices' => 'Invoices',
            'payments' => 'Payments',
            'receipts' => 'Receipts',
            'transactions' => 'Transactions',
            'wallet' => 'Wallet',
            'revenue' => 'Revenue',
            'expenses' => 'Expenses',
            'reports' => 'Reports',
            'communication' => 'Communication',
            'support' => 'Support',
            'operations' => 'Operations',
            'staff' => 'Staff Management',
            'users' => 'User Accounts',
            'roles' => 'Roles & Permissions',
            'sessions' => 'User Sessions',
            'ai' => 'AI Assistant',
            'settings' => 'Settings',
            'audit_logs' => 'Audit Logs',
            'system_events' => 'System Events',
        ];
    }
}

if (!function_exists('flexihubPermissionActions')) {
    function flexihubPermissionActions()
    {
        return ['view', 'create', 'edit', 'delete', 'approve'];
    }
}

if (!function_exists('flexihubPermissionRouteMap')) {
    function flexihubPermissionRouteMap()
    {
        return [
            'dashboard' => 'dashboard',
            'customers' => 'customers',
            'internet_plans' => 'internet_plans',
            'internet_accounts' => 'internet_accounts',
            'subscriptions' => 'subscriptions',
            'pppoe' => 'pppoe',
            'pppoe_accounts' => 'pppoe',
            'pppoe_servers' => 'pppoe',
            'ip_pools' => 'pppoe',
            'routers' => 'network',
            'network' => 'network',
            'network_sites' => 'network',
            'hotspot' => 'hotspot',
            'billing' => 'billing',
            'invoices' => 'invoices',
            'payments' => 'payments',
            'receipts' => 'billing',
            'transactions' => 'billing',
            'expenses' => 'billing',
            'revenue' => 'billing',
            'reports' => 'reports',
            'communication' => 'communication',
            'ai' => 'ai',
            'staffs' => 'staff',
            'users' => 'users',
            'roles' => 'roles',
            'sessions' => 'sessions',
            'settings' => 'settings',
            'operations' => 'operations',
            'wallet' => 'wallet',
            'platform' => 'platform',
            'tenants' => 'platform',
            'platform_plans' => 'platform',
            'platform_subscriptions' => 'platform',
            'platform_wallets' => 'platform',
            'platform_transactions' => 'platform',
            'platform_revenue' => 'platform',
            'platform_users' => 'platform',
            'support' => 'support',
            'system_events' => 'platform',
            'audit_logs' => 'platform',
            'platform_settings' => 'platform',
        ];
    }
}
