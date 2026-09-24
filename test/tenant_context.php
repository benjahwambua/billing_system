<?php

require_once __DIR__ . '/../includes/auth.php';

requireLogin();

echo "<h2>Flexihub Authentication Test</h2>";

echo "<strong>User ID:</strong> ";
echo getCurrentUserId();

echo "<br><br>";

echo "<strong>Username:</strong> ";
echo e($_SESSION['username'] ?? 'Not set');

echo "<br><br>";

echo "<strong>User Scope:</strong> ";
echo e(getCurrentUserScope());

echo "<br><br>";

echo "<strong>Tenant ID:</strong> ";

$tenantId = getCurrentTenantId();

echo $tenantId
    ? $tenantId
    : 'NULL (Host User)';

echo "<br><br>";

echo "<strong>Is Host:</strong> ";
echo isHostUser() ? 'YES' : 'NO';

echo "<br><br>";

echo "<strong>Is Tenant:</strong> ";
echo isTenantUser() ? 'YES' : 'NO';

echo "<br><br>";

echo "<strong>Roles:</strong><br>";

$roles = getCurrentUserRoles($conn);

if (empty($roles)) {

    echo "No roles found.";

} else {

    foreach ($roles as $role) {

        echo e($role['code']);
        echo " — ";
        echo e($role['name']);
        echo "<br>";
    }
}