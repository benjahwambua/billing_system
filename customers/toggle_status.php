<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();
requireTenant();
requirePermission('customers.suspend');

$id = (int)($_GET['id'] ?? 0);
$customer = getCustomer($id);

if (!$customer) {
    http_response_code(404);
    die('Customer not found.');
}

$newStatus = strtolower($customer['status']) === 'active' ? 'suspended' : 'active';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    $stmt = $conn->prepare("UPDATE customers SET status=?, updated_at=NOW() WHERE id=? AND tenant_id=?");
    if (!$stmt) die('Unable to prepare status update.');

    $tenantId = getCurrentTenantId();
    $stmt->bind_param('sii', $newStatus, $id, $tenantId);

    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        die('Unable to update customer: ' . $error);
    }

    $stmt->close();

    logAudit(
        'STATUS_CHANGE',
        'CUSTOMER',
        'Changed customer ' . $customer['customer_number'] . ' status to ' . $newStatus,
        'customer',
        $id
    );

    $_SESSION['flash_success'] = 'Customer status changed to ' . ucfirst($newStatus) . '.';
    redirect('view.php?id=' . $id);
}

$pageTitle = 'Change Customer Status';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <h2>Change Customer Status</h2>
    <p>
        Customer: <strong><?= e($customer['customer_number']) ?></strong>
        — <?= e(trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''))) ?>
    </p>
    <p>Current status: <strong><?= e(ucfirst($customer['status'])) ?></strong></p>
    <p>This will change the customer to <strong><?= e(ucfirst($newStatus)) ?></strong>.</p>

    <form method="post">
        <?= csrfField() ?>
        <button class="btn btn-primary" type="submit">Confirm Change</button>
        <a class="btn btn-secondary" href="view.php?id=<?= $id ?>">Cancel</a>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
