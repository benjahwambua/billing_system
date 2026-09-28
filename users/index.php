<?php
require_once '../includes/auth.php'; requireLogin(); requireTenantContext(); $pageTitle='Users'; require_once '../includes/header.php';
?><div class="dashboard-card"><h2>Users</h2><p>Manage tenant system users and their module access.</p><a class="btn" href="../roles/index.php">Roles & Permissions</a></div><?php require_once '../includes/footer.php';?>