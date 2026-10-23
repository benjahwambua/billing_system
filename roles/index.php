<?php
require_once '../includes/auth.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('staff', 'view');

global $conn;
$tenantId = (int) getCurrentTenantId();

$modules = [
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
    'staff' => 'Staff & Access',
    'ai' => 'AI Assistant',
    'settings' => 'Settings',
    'audit_logs' => 'Audit Logs',
    'system_events' => 'System Events',
];

$roles = [];
if (flexihubTableHasColumn('access_roles', 'tenant_id')) {
    $q = $conn->prepare("SELECT id, name, description, is_system, status FROM access_roles WHERE tenant_id = ? ORDER BY name");
    if ($q) {
        $q->bind_param('i', $tenantId);
        if ($q->execute()) {
            $result = $q->get_result();
            while ($row = $result->fetch_assoc()) {
                $roles[] = $row;
            }
        }
        $q->close();
    }
}

$selected = (int) ($_GET['role'] ?? ($_POST['role_id'] ?? ($roles[0]['id'] ?? 0)));
$selectedRole = null;
foreach ($roles as $role) {
    if ((int) $role['id'] === $selected) {
        $selectedRole = $role;
        break;
    }
}

if ($selected > 0 && !$selectedRole) {
    http_response_code(404);
    exit('The selected role was not found in your organisation.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    requireModulePermission('staff', 'edit');

    $roleId = (int) ($_POST['role_id'] ?? 0);
    $name = trim((string) ($_POST['name'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));

    if ($name === '' || mb_strlen($name) > 100 || mb_strlen($description) > 255) {
        http_response_code(422);
        exit('Enter a role name (up to 100 characters) and a description no longer than 255 characters.');
    }

    if ($roleId > 0) {
        $roleCheck = $conn->prepare("SELECT id, is_system FROM access_roles WHERE id = ? AND tenant_id = ? LIMIT 1");
        if (!$roleCheck) {
            http_response_code(500);
            exit('Unable to verify the selected role.');
        }
        $roleCheck->bind_param('ii', $roleId, $tenantId);
        $roleCheck->execute();
        $ownedRole = $roleCheck->get_result()->fetch_assoc();
        $roleCheck->close();

        if (!$ownedRole) {
            http_response_code(404);
            exit('The selected role was not found in your organisation.');
        }

        if (!empty($ownedRole['is_system'])) {
            http_response_code(403);
            exit('System roles cannot be edited. Create a separate role for custom permissions.');
        }

        $q = $conn->prepare("UPDATE access_roles SET name = ?, description = ? WHERE id = ? AND tenant_id = ? AND is_system = 0");
        if (!$q) {
            http_response_code(500);
            exit('Unable to prepare the role update.');
        }
        $q->bind_param('ssii', $name, $description, $roleId, $tenantId);
        if (!$q->execute()) {
            $q->close();
            http_response_code(500);
            exit('Unable to save the role details.');
        }
        $q->close();
    } else {
        $q = $conn->prepare("INSERT INTO access_roles (tenant_id, name, description) VALUES (?, ?, ?)");
        if (!$q) {
            http_response_code(500);
            exit('Unable to prepare role creation.');
        }
        $q->bind_param('iss', $tenantId, $name, $description);
        if (!$q->execute()) {
            $q->close();
            http_response_code(500);
            exit('Unable to create the role. Check whether the role name already exists.');
        }
        $roleId = (int) $q->insert_id;
        $q->close();
    }

    $permission = $conn->prepare(
        "INSERT INTO access_role_permissions
            (tenant_id, role_id, module_key, can_view, can_create, can_edit, can_delete, can_approve)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            can_view = VALUES(can_view),
            can_create = VALUES(can_create),
            can_edit = VALUES(can_edit),
            can_delete = VALUES(can_delete),
            can_approve = VALUES(can_approve)"
    );

    if (!$permission) {
        http_response_code(500);
        exit('Role saved, but the permissions could not be prepared. Reopen the role and save permissions again.');
    }

    foreach ($modules as $key => $label) {
        $view = isset($_POST['p'][$key]['view']) ? 1 : 0;
        $create = isset($_POST['p'][$key]['create']) ? 1 : 0;
        $edit = isset($_POST['p'][$key]['edit']) ? 1 : 0;
        $delete = isset($_POST['p'][$key]['delete']) ? 1 : 0;
        $approve = isset($_POST['p'][$key]['approve']) ? 1 : 0;
        $permission->bind_param('iisiiiii', $tenantId, $roleId, $key, $view, $create, $edit, $delete, $approve);
        if (!$permission->execute()) {
            error_log('Flexihub role permission save failed for module ' . $key . ': ' . $permission->error);
            $permission->close();
            http_response_code(500);
            exit('Some permissions could not be saved. Reopen the role and try again.');
        }
    }
    $permission->close();

    header('Location: index.php?role=' . $roleId . '&saved=1');
    exit;
}

$selected = (int) ($_GET['role'] ?? ($roles[0]['id'] ?? 0));
$selectedRole = null;
foreach ($roles as $role) {
    if ((int) $role['id'] === $selected) {
        $selectedRole = $role;
        break;
    }
}

$perms = [];
if ($selectedRole && flexihubTableHasColumn('access_role_permissions', 'module_key')) {
    $q = $conn->prepare("SELECT * FROM access_role_permissions WHERE role_id = ? AND tenant_id = ?");
    if ($q) {
        $q->bind_param('ii', $selected, $tenantId);
        if ($q->execute()) {
            $result = $q->get_result();
            while ($row = $result->fetch_assoc()) {
                $perms[$row['module_key']] = $row;
            }
        }
        $q->close();
    }
}

$pageTitle = 'Roles & Permissions';
require_once '../includes/header.php';
?>
<div class="dashboard-card">
    <h2>Roles & Permissions</h2>
    <p>Create roles and control each module with View, Create, Edit, Delete and Approve.</p>
    <?php if (isset($_GET['saved'])): ?>
        <p role="status">Role and permissions saved successfully.</p>
    <?php endif; ?>
    <form method="get" class="form-group">
        <label for="role">Select role</label>
        <select id="role" name="role" onchange="this.form.submit()">
            <?php foreach ($roles as $role): ?>
                <option value="<?= e($role['id']) ?>" <?= (int) $role['id'] === $selected ? 'selected' : '' ?>>
                    <?= e($role['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>
    <?php if (!$selectedRole): ?>
        <p>No roles are configured for this organisation yet. Use the form below to create the first custom role.</p>
    <?php endif; ?>
    <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="role_id" value="<?= e($selectedRole['id'] ?? 0) ?>">
        <div class="form-group">
            <label for="role-name">Role name</label>
            <input id="role-name" name="name" required maxlength="100" value="<?= e($selectedRole['name'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label for="role-description">Description</label>
            <input id="role-description" name="description" maxlength="255" value="<?= e($selectedRole['description'] ?? '') ?>">
        </div>
        <div class="table-responsive">
            <table>
                <thead><tr><th>Module</th><th>View</th><th>Create</th><th>Edit</th><th>Delete</th><th>Approve</th></tr></thead>
                <tbody>
                <?php foreach ($modules as $key => $label): $p = $perms[$key] ?? []; ?>
                    <tr>
                        <td><?= e($label) ?></td>
                        <?php foreach (['view', 'create', 'edit', 'delete', 'approve'] as $action): ?>
                            <td><input type="checkbox" aria-label="<?= e($label . ' ' . $action) ?>" name="p[<?= e($key) ?>][<?= e($action) ?>]" <?= !empty($p['can_' . $action]) ? 'checked' : '' ?>></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (userCan('staff', 'edit')): ?>
            <button type="submit">Save Role & Permissions</button>
        <?php endif; ?>
    </form>
</div>
<?php require_once '../includes/footer.php'; ?>
