<?php
require_once '../includes/auth.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('hotspot','create');
header('Location: packages_add.php');
exit;
