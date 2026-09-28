<?php
require_once '../includes/auth.php'; requireLogin();$tenantId=getCurrentTenantId();$income=0;$expense=0;
$q=$conn->query("SHOW TABLES LIKE 'payments');");