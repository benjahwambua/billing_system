<?php

if (!isset($pageTitle)) {
    $pageTitle = 'Dashboard';
}

$companyName = getCompanyName($conn);

$userName = getLoggedInUserName($conn);
$tenantTheme = null;
if (function_exists('getCurrentTenantId')) {
    $themeTenantId = (int)getCurrentTenantId();
    if ($themeTenantId > 0) {
        $themeStmt = $conn->prepare("SELECT theme_name, primary_color, accent_color, logo_url, dark_mode FROM tenant_theme_settings WHERE tenant_id=? LIMIT 1");
        if ($themeStmt) {
            $themeStmt->bind_param('i', $themeTenantId);
            $themeStmt->execute();
            $tenantTheme = $themeStmt->get_result()->fetch_assoc();
            $themeStmt->close();
        }
    }
}
$themePrimary = preg_match('/^#[0-9A-Fa-f]{6}$/', (string)($tenantTheme['primary_color'] ?? '')) ? $tenantTheme['primary_color'] : '#2563eb';
$themeAccent = preg_match('/^#[0-9A-Fa-f]{6}$/', (string)($tenantTheme['accent_color'] ?? '')) ? $tenantTheme['accent_color'] : '#3b82f6';
$themeLogo = filter_var((string)($tenantTheme['logo_url'] ?? ''), FILTER_VALIDATE_URL) ? $tenantTheme['logo_url'] : '';

?>

<!DOCTYPE html>
<html lang="en">

<?php
$sidebarInitial = strtoupper(substr($userName ?: 'U', 0, 1));
$userRoleLabel = $_SESSION['user_role'] ?? ($_SESSION['role'] ?? 'User');
?>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | <?= e($companyName) ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/flexihub-dark.css">
    <link rel="stylesheet" href="../assets/css/sidebar-collapse.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script src="../assets/js/sidebar-collapse.js" defer></script>
    <style>
        :root{--fh-sidebar-width:260px;--fh-topbar-height:75px}
        .headbar{height:var(--fh-topbar-height);min-height:var(--fh-topbar-height);padding:0 28px;position:sticky;top:0;z-index:1001;display:flex;align-items:center;justify-content:space-between;background:rgba(7,11,18,.94);border-bottom:1px solid rgba(59,130,246,.14);box-shadow:0 6px 24px rgba(0,0,0,.2);backdrop-filter:blur(18px)}
        .headbar-left{display:flex;align-items:center;gap:14px;min-width:0}.headbar-brand{display:flex;align-items:center;gap:11px;min-width:0}.headbar-logo{width:40px;height:40px;display:flex;align-items:center;justify-content:center;border-radius:10px;background:linear-gradient(135deg,var(--fh-primary),var(--fh-accent));color:#fff;font-size:18px;font-weight:800;box-shadow:0 0 20px rgba(37,99,235,.28)}.headbar-brand-copy{display:flex;flex-direction:column;line-height:1.15}.headbar-brand-name{color:#f8fafc;font-size:15px;font-weight:800;letter-spacing:.2px}.headbar-brand-subtitle{color:#7f8da3;font-size:10px;margin-top:3px}.headbar-divider{width:1px;height:30px;background:rgba(148,163,184,.16);margin:0 3px}.page-title{min-width:0}.page-title h1{margin:0;color:#dbeafe;font-size:16px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.page-kicker{color:#64748b;font-size:9px;text-transform:uppercase;letter-spacing:1.2px;margin-bottom:3px}.headbar-right{display:flex;align-items:center;gap:12px}.notification-icon{width:38px;height:38px;display:flex;align-items:center;justify-content:center;border:1px solid rgba(148,163,184,.14);border-radius:10px;color:#94a3b8;background:rgba(255,255,255,.035)}.notification-icon:hover{color:#60a5fa;border-color:rgba(59,130,246,.28)}.user-profile{display:flex;align-items:center;gap:10px;padding:6px 10px;border:1px solid rgba(148,163,184,.13);border-radius:12px;background:rgba(255,255,255,.035)}.user-avatar{width:34px;height:34px;border-radius:10px;background:linear-gradient(135deg,#1d4ed8,#3b82f6);color:#fff;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;box-shadow:0 0 14px rgba(37,99,235,.2)}.user-info strong{display:block;color:#f8fafc;font-size:12px}.user-info small{display:block;color:#64748b;font-size:10px;margin-top:2px}.topbar-logout{display:inline-flex;align-items:center;gap:7px;padding:9px 12px;border-radius:9px;background:linear-gradient(135deg,#1d4ed8,#3b82f6);color:#fff;text-decoration:none;font-size:11px;font-weight:800}.topbar-logout:hover{color:#fff;transform:translateY(-1px);box-shadow:0 8px 20px rgba(37,99,235,.2)}
        @media(max-width:900px){.headbar{padding:0 18px}.headbar-brand-copy,.headbar-divider{display:none}.page-title h1{font-size:14px}.headbar-right{gap:7px}.user-info{display:none}.topbar-logout{padding:9px 10px}.topbar-logout span{display:none}}
        @media(max-width:600px){.headbar{height:65px;min-height:65px;padding:0 12px}.notification-icon{width:34px;height:34px}.user-profile{padding:4px}.headbar-logo{width:36px;height:36px}.sidebar-toggle{margin-right:0}}
    </style>
</head>

<body>

<div class="app-container">

    <!-- Sidebar -->
    <?php require_once __DIR__ . '/sidebar.php'; ?>

    <!-- Main Application -->
    <div class="main-wrapper">

        <!-- Headbar -->
        <header class="headbar" role="banner">
    <div class="headbar-left">
        <button class="sidebar-toggle" id="sidebarToggle" type="button" aria-label="Toggle navigation" aria-controls="flexihubSidebar">☰</button>
        <div class="headbar-brand">
            <div class="headbar-logo" aria-hidden="true"><?php if($themeLogo): ?><img src="<?=e($themeLogo)?>" alt="<?=e($companyName)?>" style="width:100%;height:100%;object-fit:contain;border-radius:10px"><?php else: ?>F<?php endif; ?></div>
            <div class="headbar-brand-copy">
                <span class="headbar-brand-name"><?= e($companyName ?: 'Flexihub') ?></span>
                <span class="headbar-brand-subtitle">ISP Management &amp; Billing Platform</span>
            </div>
        </div>
        <div class="headbar-divider" aria-hidden="true"></div>
        <div class="page-title">
            <div class="page-kicker">Current Module</div>
            <h1><?= e($pageTitle) ?></h1>
        </div>
    </div>
    <div class="headbar-right">
        <div class="notification-icon" title="Notifications" aria-label="Notifications"><i class="fa-solid fa-bell"></i></div>
        <div class="user-profile">
            <div class="user-avatar"><?= e($sidebarInitial) ?></div>
            <div class="user-info"><strong><?= e($userName ?: 'User') ?></strong><small><?= e($userRoleLabel) ?></small></div>
        </div>
        <a class="topbar-logout" href="../auth/logout.php"><i class="fa-solid fa-power-off"></i><span>Logout</span></a>
    </div>
</header>


        <!-- Page Content -->

        <main class="page-content">