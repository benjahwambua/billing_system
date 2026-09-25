<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();
requireTenant();
requirePermission('customers.edit');

$id = (int)($_GET['id'] ?? 0);
$customer = getCustomer($id);

if (!$customer) {
    http_response_code(404);
    die('Customer not found.');
}

$errors = [];
$fields = ['first_name','last_name','phone','alternative_phone','email','customer_type','id_number','address','county','town','notes'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    foreach ($fields as $field) {
        $customer[$field] = trim($_POST[$field] ?? '');
    }

    if ($customer['first_name'] === '') $errors[] = 'First name is required.';
    if ($customer['phone'] === '') $errors[] = 'Phone number is required.';
    if ($customer['email'] !== '' && !filter_var($customer['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }

    if (!$errors) {
        $stmt = $conn->prepare("UPDATE customers SET
            first_name=?, last_name=?, phone=?, alternative_phone=?, email=?, customer_type=?,
            id_number=?, address=?, county=?, town=?, notes=?, updated_at=NOW()
            WHERE id=? AND tenant_id=?");

        if (!$stmt) {
            $errors[] = 'Unable to prepare update: ' . $conn->error;
        } else {
            $stmt->bind_param(
                'sssssssssssii',
                $customer['first_name'],
                $customer['last_name'],
                $customer['phone'],
                $customer['alternative_phone'],
                $customer['email'],
                $customer['customer_type'],
                $customer['id_number'],
                $customer['address'],
                $customer['county'],
                $customer['town'],
                $customer['notes'],
                $id,
                getCurrentTenantId()
            );

            if ($stmt->execute()) {
                $stmt->close();
                logAudit('UPDATE','CUSTOMER','Updated customer ' . $customer['customer_number'],'customer',$id);
                $_SESSION['flash_success'] = 'Customer updated successfully.';
                redirect('view.php?id=' . $id);
            }

            $errors[] = 'Unable to update customer: ' . $stmt->error;
            $stmt->close();
        }
    }
}

$pageTitle = 'Edit Customer';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <h2>Edit Customer <?= e($customer['customer_number']) ?></h2>

    <?php if ($errors): ?>
        <div class="alert alert-danger"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <form method="post">
        <?= csrfField() ?>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
            <div><label>First Name *</label><input name="first_name" value="<?= e($customer['first_name']) ?>" required></div>
            <div><label>Last Name</label><input name="last_name" value="<?= e($customer['last_name']) ?>"></div>
            <div><label>Phone *</label><input name="phone" value="<?= e($customer['phone']) ?>" required></div>
            <div><label>Alternative Phone</label><input name="alternative_phone" value="<?= e($customer['alternative_phone']) ?>"></div>
            <div><label>Email</label><input type="email" name="email" value="<?= e($customer['email']) ?>"></div>
            <div><label>Customer Type</label><input name="customer_type" value="<?= e($customer['customer_type']) ?>"></div>
            <div><label>ID / Registration No.</label><input name="id_number" value="<?= e($customer['id_number']) ?>"></div>
            <div><label>Town</label><input name="town" value="<?= e($customer['town']) ?>"></div>
            <div><label>County</label><input name="county" value="<?= e($customer['county']) ?>"></div>
            <div><label>Address</label><input name="address" value="<?= e($customer['address']) ?>"></div>
            <div style="grid-column:1/-1;"><label>Notes</label><textarea name="notes" rows="4"><?= e($customer['notes']) ?></textarea></div>
        </div>
        <div style="margin-top:20px;">
            <button class="btn btn-primary" type="submit">Save Changes</button>
            <a class="btn btn-secondary" href="view.php?id=<?= $id ?>">Cancel</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
