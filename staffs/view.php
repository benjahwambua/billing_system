<?php
require_once __DIR__ . '/../includes/auth.php';
requireActiveUser();
$tenantId = (int) requireTenantContext();
requireModulePermission('staff', 'view');

$staffId = (int)($_GET['id'] ?? 0);
if ($staffId <= 0) {
    http_response_code(404);
    exit('Staff record not found.');
}

$stmt = $conn->prepare('SELECT * FROM staffs WHERE id = ? AND tenant_id = ? LIMIT 1');
if (!$stmt) {
    http_response_code(503);
    exit('Unable to load the staff record.');
}
$stmt->bind_param('ii', $staffId, $tenantId);
if (!$stmt->execute()) {
    $stmt->close();
    http_response_code(503);
    exit('Unable to load the staff record.');
}
$staff = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$staff) {
    http_response_code(404);
    exit('Staff record not found.');
}

$pageTitle = 'Staff Details';
require_once __DIR__ . '/../includes/header.php';

$fields = [
    'staff_code' => 'Staff Code',
    'first_name' => 'First Name',
    'last_name' => 'Last Name',
    'phone' => 'Phone',
    'email' => 'Email',
    'department' => 'Department',
    'position' => 'Position',
    'date_joined' => 'Date Joined',
    'status' => 'Status',
];
?>
<div class="dashboard-card">
    <div class="page-header">
        <div>
            <h2>Staff Details</h2>
            <p>View staff information belonging to your organisation.</p>
        </div>
        <a class="btn btn-secondary" href="list.php">Back to Staff Directory</a>
    </div>
    <?php if (($_GET['created'] ?? '') === '1'): ?>
        <p class="alert alert-success">Staff record created successfully.</p>
    <?php endif; ?>
    <div class="table-responsive">
        <table>
            <tbody>
                <?php foreach ($fields as $key => $label): ?>
                    <tr>
                        <th scope="row"><?= e($label) ?></th>
                        <td><?= e($staff[$key] ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
