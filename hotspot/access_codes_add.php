<?php
require_once '../includes/auth.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('hotspot','create');
header('Location: vouchers.php');
exit;
