<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();
$tenantId = requireTenant();
requirePermission('customers.create');

$errors = [];
$data = [
    'first_name' => '',
    'last_name' => '',
    'phone' => '',
    'alternative_phone' => '',
    'email' => '',
    'customer_type' => 'Individual',
    'id_number' => '',
    'address' => '',
    'county' => '',
    'town' => '',
    'notes' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    foreach ($data as $key => $value) {
        $data[$key] = trim($_POST[$key] ?? $value);
    }

    if ($data['first_name'] === '') $errors[] = 'First name is required.';
    if ($data['phone'] === '') $errors[] = 'Phone number is required.';

    if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }

    if (!$errors) {
        $customerNumber = generateCustomerNumber();

        $stmt = $conn->prepare("INSERT INTO customers
            (tenant_id, customer_number, first_name, last_name, phone, alternative_phone, email,
             customer_type, id_number, address, county, town, notes, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())");

        if (!$stmt) {
            $errors[] = 'Unable to prepare customer record: ' . $conn->error;
        } else {
            $stmt->bind_param(
                'issssssssssss',
                $tenantId,
                $customerNumber,
                $data['first_name'],
                $data['last_name'],
                $data['phone'],
                $data['alternative_phone'],
                $data['email'],
                $data['customer_type'],
                $data['id_number'],
                $data['address'],
                $data['county'],
                $data['town'],
                $data['notes']
            );

            if ($stmt->execute()) {
                $customerId = $stmt->insert_id;
                $stmt->close();

                logAudit(
                    'CREATE',
                    'CUSTOMER',
                    'Created customer ' . $customerNumber,
                    'customer',
                    $customerId
                );

                $_SESSION['flash_success'] = 'Customer ' . $customerNumber . ' created successfully.';
                redirect('view.php?id=' . $customerId);
            }

            $errors[] = 'Unable to create customer: ' . $stmt->error;
            $stmt->close();
        }
    }
}

$pageTitle = 'Add Customer';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <h2>Add Customer</h2>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <form method="post">
        <?= csrfField() ?>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
            <div><label>First Name *</label><input name="first_name" value="<?= e($data['first_name']) ?>" required></div>
            <div><label>Last Name</label><input name="last_name" value="<?= e($data['last_name']) ?>"></div>
            <div><label>Phone *</label><input name="phone" value="<?= e($data['phone']) ?>" required></div>
            <div><label>Alternative Phone</label><input name="alternative_phone" value="<?= e($data['alternative_phone']) ?>"></div>
            <div><label>Email</label><input type="email" name="email" value="<?= e($data['email']) ?>"></div>
            <div>
                <label>Customer Type</label>
                <select name="customer_type">
                    <option <?= $data['customer_type']==='Individual'?'selected':'' ?>>Individual</option>
                    <option <?= $data['customer_type']==='Business'?'selected':'' ?>>Business</option>
                    <option <?= $data['customer_type']==='Organization'?'selected':'' ?>>Organization</option>
                </select>
            </div>
            <div><label>ID / Registration No.</label><input name="id_number" value="<?= e($data['id_number']) ?>"></div>
            <div><label>Town</label><input name="town" value="<?= e($data['town']) ?>"></div>
            <div><label>County</label><input name="county" value="<?= e($data['county']) ?>"></div>
            <div><label>Address</label><input name="address" value="<?= e($data['address']) ?>"></div>
            <div style="grid-column:1/-1;"><label>Notes</label><textarea name="notes" rows="4"><?= e($data['notes']) ?></textarea></div>
        </div>
        <div style="margin-top:20px;">
            <button class="btn btn-primary" type="submit">Create Customer</button>
            <a class="btn btn-secondary" href="index.php">Cancel</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
