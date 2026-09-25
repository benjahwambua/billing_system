<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();
requireTenant();
requirePermission('customers.view');

$id = (int)($_GET['id'] ?? 0);
$customer = getCustomer($id);

if (!$customer) {
    http_response_code(404);
    die('Customer not found.');
}

$accounts = getCustomerAccounts($id);
$pageTitle = 'Customer Profile';

require_once __DIR__ . '/../includes/header.php';
?>

<?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="alert alert-success"><?= e($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
<?php endif; ?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
    <div>
        <h2><?= e(trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''))) ?></h2>
        <p style="color:#6b7280;margin:0;"><?= e($customer['customer_number']) ?></p>
    </div>
    <?php if (userHasPermission('customers.edit')): ?>
        <a class="btn btn-primary" href="edit.php?id=<?= $id ?>">Edit Customer</a>
    <?php endif; ?>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
    <div class="card">
        <h3>Customer Information</h3>
        <p><strong>Phone:</strong> <?= e($customer['phone']) ?></p>
        <p><strong>Alternative:</strong> <?= e($customer['alternative_phone']) ?></p>
        <p><strong>Email:</strong> <?= e($customer['email']) ?></p>
        <p><strong>Type:</strong> <?= e($customer['customer_type']) ?></p>
        <p><strong>ID / Registration:</strong> <?= e($customer['id_number']) ?></p>
        <p><strong>Location:</strong> <?= e(trim(($customer['town'] ?? '') . ', ' . ($customer['county'] ?? ''), ', ')) ?></p>
        <p><strong>Address:</strong> <?= e($customer['address']) ?></p>
        <p><strong>Status:</strong> <?= e(ucfirst($customer['status'])) ?></p>
        <p><strong>Notes:</strong><br><?= nl2br(e($customer['notes'])) ?></p>
    </div>

    <div class="card">
        <h3>Internet Accounts</h3>
        <?php if (!$accounts): ?>
            <p style="color:#6b7280;">No internet accounts linked to this customer.</p>
        <?php else: ?>
            <table class="table">
                <thead><tr><th>Account</th><th>Status</th><th>Expiry</th></tr></thead>
                <tbody>
                <?php foreach ($accounts as $account): ?>
                    <tr>
                        <td><?= e($account['account_number'] ?? $account['id']) ?></td>
                        <td><?= e(ucfirst($account['status'] ?? '')) ?></td>
                        <td><?= e($account['expiry_date'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<div style="margin-top:20px;">
    <a href="index.php">← Back to Customers</a>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
