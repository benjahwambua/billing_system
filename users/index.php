<?php
require_once __DIR__ . '/../includes/auth.php';

requireActiveUser();
requireTenantContext();
requireModulePermission('staff', 'view');

global $conn;

$tenantId = (int) getCurrentTenantId();
$canEditUsers = userCan('staff', 'edit');
$errorMessage = '';

/*
 * Assignments are always created through a tenant-scoped join. The target
 * user and role must both belong to the current tenant.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    if (!$canEditUsers) {
        http_response_code(403);
        exit('You do not have permission to assign user roles.');
    }

    $userId = (int) ($_POST['user_id'] ?? 0);
    $roleId = (int) ($_POST['role_id'] ?? 0);

    if ($userId <= 0 || $roleId <= 0) {
        $errorMessage = 'Select a valid user and role.';
    } else {
        $stmt = $conn->prepare(
            "INSERT IGNORE INTO access_user_roles (tenant_id, user_id, role_id)
             SELECT ?, u.id, ar.id
             FROM users u
             INNER JOIN access_roles ar
                 ON ar.id = ?
                AND ar.tenant_id = ?
                AND ar.status = 'active'
             WHERE u.id = ?
               AND u.tenant_id = ?
             LIMIT 1"
        );

        if (!$stmt) {
            error_log('Flexihub users: role assignment could not be prepared.');
            $errorMessage = 'Unable to prepare the role assignment. Please try again.';
        } else {
            $stmt->bind_param(
                'iiiii',
                $tenantId,
                $roleId,
                $tenantId,
                $userId,
                $tenantId
            );

            if (!$stmt->execute()) {
                error_log('Flexihub users: role assignment failed.');
                $errorMessage = 'Unable to assign this role. Please try again.';
            } elseif ($stmt->affected_rows > 0) {
                $stmt->close();
                header('Location: index.php?assigned=1');
                exit;
            } else {
                $stmt->close();
                header('Location: index.php?already_assigned=1');
                exit;
            }

            $stmt->close();
        }
    }
}

$users = [];
$stmt = $conn->prepare(
    "SELECT id, username, role, status
     FROM users
     WHERE tenant_id = ?
     ORDER BY username"
);

if ($stmt) {
    $stmt->bind_param('i', $tenantId);

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
    } else {
        error_log('Flexihub users: tenant user listing failed.');
    }

    $stmt->close();
}

$roles = [];
$stmt = $conn->prepare(
    "SELECT id, name
     FROM access_roles
     WHERE tenant_id = ?
       AND status = 'active'
     ORDER BY name"
);

if ($stmt) {
    $stmt->bind_param('i', $tenantId);

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $roles[] = $row;
        }
    } else {
        error_log('Flexihub users: role listing failed.');
    }

    $stmt->close();
}

$assignments = [];
$stmt = $conn->prepare(
    "SELECT aur.user_id, ar.id AS role_id, ar.name AS role_name
     FROM access_user_roles aur
     INNER JOIN access_roles ar
         ON ar.id = aur.role_id
        AND ar.tenant_id = aur.tenant_id
     WHERE aur.tenant_id = ?
       AND ar.status = 'active'
     ORDER BY ar.name"
);

if ($stmt) {
    $stmt->bind_param('i', $tenantId);

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $assignments[(int) $row['user_id']][] = $row;
        }
    } else {
        error_log('Flexihub users: role assignment listing failed.');
    }

    $stmt->close();
}

$pageTitle = 'Users & Access';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Users &amp; Access</h1>
        <p class="page-description">Manage organisation user accounts and their assigned access roles.</p>
    </div>
</div>

<?php if ($errorMessage !== ''): ?>
    <div class="alert alert-danger" role="alert"><?= e($errorMessage) ?></div>
<?php endif; ?>

<?php if (isset($_GET['assigned'])): ?>
    <div class="alert alert-success" role="status">Role assigned successfully.</div>
<?php elseif (isset($_GET['already_assigned'])): ?>
    <div class="alert alert-info" role="status">That role is already assigned to this user.</div>
<?php endif; ?>

<section class="dashboard-card">
    <div class="card-header">
        <div>
            <h2>Organisation users</h2>
            <p class="page-description">Only users belonging to your organisation are listed here.</p>
        </div>
        <span class="status-badge"><?= count($users) ?> users</span>
    </div>

    <?php if (!$users): ?>
        <div class="empty-state">
            <h3>No users found</h3>
            <p>There are currently no user accounts assigned to this organisation.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Account role</th>
                        <th>Status</th>
                        <th>Assigned access roles</th>
                        <?php if ($canEditUsers): ?><th>Assign role</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <?php
                        $userId = (int) $user['id'];
                        $userAssignments = $assignments[$userId] ?? [];
                        $status = strtolower(trim((string) ($user['status'] ?? 'unknown')));
                        $statusClass = $status === 'active' ? 'badge-success' : 'badge-muted';
                        ?>
                        <tr>
                            <td>
                                <strong><?= e($user['username']) ?></strong>
                                <div class="help-text">User #<?= e($userId) ?></div>
                            </td>
                            <td><?= e($user['role'] ?? '—') ?></td>
                            <td><span class="badge <?= e($statusClass) ?>"><?= e(ucfirst($status)) ?></span></td>
                            <td>
                                <?php if ($userAssignments): ?>
                                    <div class="table-actions">
                                        <?php foreach ($userAssignments as $assignment): ?>
                                            <span class="badge"><?= e($assignment['role_name']) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted">No access role assigned</span>
                                <?php endif; ?>
                            </td>
                            <?php if ($canEditUsers): ?>
                                <td>
                                    <?php if ($roles): ?>
                                        <form method="post" class="role-assignment-form">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="user_id" value="<?= e($userId) ?>">
                                            <select name="role_id" aria-label="Role for <?= e($user['username']) ?>" required>
                                                <option value="">Select role</option>
                                                <?php foreach ($roles as $role): ?>
                                                    <option value="<?= e($role['id']) ?>"><?= e($role['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="btn btn-primary btn-sm">Assign</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted">Create an access role first</span>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
