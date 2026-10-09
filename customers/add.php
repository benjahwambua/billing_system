<?php
require_once __DIR__ . '/../includes/auth.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('customers', 'create');
$tenantId = (int)getCurrentTenantId();
if ($tenantId <= 0) { http_response_code(403); exit('A valid tenant context is required.'); }
$pageTitle = 'Add Customer';
$errors = [];

function customerAddColumns() {
    global $conn;
    $allowed = [];
    $r = $conn->query("SHOW COLUMNS FROM customers");
    if ($r) while ($row = $r->fetch_assoc()) $allowed[$row['Field']] = $row;
    return $allowed;
}

$columns = customerAddColumns();
if (!isset($columns['tenant_id'])) { http_response_code(503); exit('Customer tenant isolation is unavailable. Please contact the administrator.'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $first = trim($_POST['first_name'] ?? '');
    $last = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $status = trim($_POST['status'] ?? 'active');

    if ($first === '' && $last === '') $errors[] = 'Enter the customer first name or last name.';
    if ($phone !== '' && !isValidPhone($phone)) $errors[] = 'Enter a valid phone number.';
    if ($email !== '' && !isValidEmail($email)) $errors[] = 'Enter a valid email address.';

    if (!$errors) {
        $values = [];
        $fields = [];

        $set = function($field,$value) use (&$fields,&$values,$columns) {
            if (isset($columns[$field])) { $fields[]=$field; $values[]=$value; }
        };

        $set('tenant_id',$tenantId);
        if (isset($columns['customer_number'])) $set('customer_number',generateCustomerNumber());
        $set('first_name',$first); $set('last_name',$last); $set('phone',$phone);
        $set('email',$email); $set('address',$address); $set('status',$status);

        if (!$fields) $errors[] = 'The customers table does not contain writable fields.';
        else {
            $marks = implode(',',array_fill(0,count($fields),'?'));
            $sql = 'INSERT INTO customers (' . implode(',',$fields) . ') VALUES (' . $marks . ')';
            $stmt = $conn->prepare($sql);
            if (!$stmt) $errors[] = 'Unable to prepare customer record: ' . $conn->error;
            else {
                $types = '';
                foreach ($values as $v) $types .= is_int($v) ? 'i' : 's';
                $stmt->bind_param($types,...$values);
                if ($stmt->execute()) { $id=$stmt->insert_id; $stmt->close(); redirect('view.php?id='.(int)$id); }
                $errors[] = 'Unable to save customer: ' . $stmt->error;
                $stmt->close();
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="main-content">
    <div class="page-header"><div><h1>Add Customer</h1><p>Create a subscriber record before assigning an internet plan.</p></div><a class="btn btn-secondary" href="index.php">Back</a></div>
    <div class="card">
        <?php if ($errors): ?><div class="alert alert-danger"><?php foreach($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div><?php endif; ?>
        <form method="post" class="form-grid">
            <?= csrfField() ?>
            <div><label>First Name</label><input class="form-control" name="first_name" value="<?= e($_POST['first_name'] ?? '') ?>"></div>
            <div><label>Last Name</label><input class="form-control" name="last_name" value="<?= e($_POST['last_name'] ?? '') ?>"></div>
            <div><label>Phone</label><input class="form-control" name="phone" value="<?= e($_POST['phone'] ?? '') ?>" placeholder="+254..."></div>
            <div><label>Email</label><input class="form-control" type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>"></div>
            <div class="full"><label>Address / Location</label><input class="form-control" name="address" value="<?= e($_POST['address'] ?? '') ?>"></div>
            <div><label>Status</label><select class="form-control" name="status"><option value="active">Active</option><option value="inactive">Inactive</option><option value="suspended">Suspended</option></select></div>
            <div class="full"><button class="btn btn-primary" type="submit">Save Customer</button></div>
        </form>
    </div>
</div>
<style>
.main-content{padding:24px}.page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px}.page-header h1{margin:0 0 5px}.page-header p{margin:0;color:#6b7280}.card{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:22px}.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}.form-grid .full{grid-column:1/-1}label{display:block;font-size:13px;font-weight:600;margin-bottom:7px}.form-control{width:100%;box-sizing:border-box;padding:10px 12px;border:1px solid #d1d5db;border-radius:6px}.btn{display:inline-block;padding:9px 14px;border-radius:6px;border:1px solid transparent;text-decoration:none;font-size:13px;font-weight:600;cursor:pointer}.btn-primary{background:#111827;color:#fff}.btn-secondary{background:#f3f4f6;color:#111827}.alert{padding:12px 14px;border-radius:7px;margin-bottom:18px}.alert-danger{background:#fee2e2;color:#991b1b}@media(max-width:700px){.main-content{padding:15px}.form-grid{grid-template-columns:1fr}}
</style>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>