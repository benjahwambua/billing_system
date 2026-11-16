<?php
require_once __DIR__ . '/../includes/auth.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('customers', 'edit');

$tenantId = (int)getCurrentTenantId();
if ($tenantId <= 0) { http_response_code(403); exit('A valid tenant context is required.'); }

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) { http_response_code(400); exit('Invalid customer.'); }

$columns = [];
$columnResult = $conn->query("SHOW COLUMNS FROM customers");
if ($columnResult) {
    while ($column = $columnResult->fetch_assoc()) $columns[$column['Field']] = true;
}
if (!isset($columns['tenant_id'])) { http_response_code(503); exit('Customer tenant isolation is unavailable.'); }

$stmt = $conn->prepare("SELECT * FROM customers WHERE id = ? AND tenant_id = ? LIMIT 1");
$stmt->bind_param('ii', $id, $tenantId);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$customer) { http_response_code(404); exit('Customer not found.'); }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $values = [
        'first_name' => trim($_POST['first_name'] ?? ''),
        'last_name' => trim($_POST['last_name'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'address' => trim($_POST['address'] ?? ''),
        'status' => strtolower(trim($_POST['status'] ?? 'active'))
    ];

    if ($values['first_name'] === '' && $values['last_name'] === '') $errors[] = 'Enter the customer first name or last name.';
    if ($values['phone'] !== '' && !isValidPhone($values['phone'])) $errors[] = 'Enter a valid phone number.';
    if ($values['email'] !== '' && !isValidEmail($values['email'])) $errors[] = 'Enter a valid email address.';
    if (!in_array($values['status'], ['active', 'inactive', 'suspended', 'expired'], true)) $errors[] = 'Select a valid customer status.';

    $updates = [];
    $params = [];
    $types = '';
    foreach ($values as $field => $value) {
        if (isset($columns[$field])) {
            $updates[] = "`$field` = ?";
            $params[] = $value;
            $types .= 's';
        }
    }
    if (isset($columns['updated_at'])) $updates[] = 'updated_at = NOW()';

    if (!$errors && $updates) {
        $params[] = $id;
        $params[] = $tenantId;
        $types .= 'ii';
        $sql = 'UPDATE customers SET ' . implode(', ', $updates) . ' WHERE id = ? AND tenant_id = ?';
        $update = $conn->prepare($sql);
        if (!$update) {
            $errors[] = 'Unable to prepare the customer update.';
        } else {
            $update->bind_param($types, ...$params);
            if ($update->execute()) {
                $update->close();
                redirect('view.php?id=' . $id);
            }
            $errors[] = 'Unable to save customer changes.';
            $update->close();
        }
    }
    if (!$errors) $customer = array_merge($customer, $values);
}

$pageTitle = 'Edit Customer';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="main-content customer-edit-page">
    <div class="page-header">
        <div><a href="view.php?id=<?=$id?>">← Customer Account</a><h1>Edit Customer</h1><p>Update subscriber contact details and account status.</p></div>
        <a class="btn btn-secondary" href="view.php?id=<?=$id?>">Cancel</a>
    </div>
    <section class="edit-card">
        <?php if ($errors): ?><div class="alert alert-danger"><?php foreach ($errors as $error): ?><div><?=e($error)?></div><?php endforeach; ?></div><?php endif; ?>
        <form method="post" class="edit-grid">
            <?=csrfField()?>
            <input type="hidden" name="id" value="<?=$id?>">
            <div><label>First Name</label><input class="form-control" name="first_name" value="<?=e($customer['first_name'] ?? '')?>"></div>
            <div><label>Last Name</label><input class="form-control" name="last_name" value="<?=e($customer['last_name'] ?? '')?>"></div>
            <div><label>Phone</label><input class="form-control" name="phone" value="<?=e($customer['phone'] ?? '')?>"></div>
            <div><label>Email</label><input class="form-control" type="email" name="email" value="<?=e($customer['email'] ?? '')?>"></div>
            <div class="full"><label>Address / Location</label><input class="form-control" name="address" value="<?=e($customer['address'] ?? '')?>"></div>
            <div><label>Status</label><select class="form-control" name="status"><?php foreach (['active','inactive','suspended','expired'] as $status): ?><option value="<?=$status?>" <?=strtolower((string)($customer['status'] ?? 'active')) === $status ? 'selected' : ''?>><?=ucfirst($status)?></option><?php endforeach; ?></select></div>
            <div class="full"><button class="btn btn-primary" type="submit">Save Changes</button></div>
        </form>
    </section>
</div>
<style>
.customer-edit-page{padding:26px;color:#e2e8f0}.customer-edit-page .page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}.customer-edit-page h1{margin:7px 0 4px;color:#f8fafc}.customer-edit-page .page-header p{margin:0;color:#94a3b8}.customer-edit-page .page-header a:first-child{color:#60a5fa}.edit-card{max-width:900px;padding:24px;border:1px solid rgba(148,163,184,.16);border-radius:16px;background:linear-gradient(145deg,#101827,#0b111b);box-shadow:0 14px 36px rgba(0,0,0,.2)}.edit-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.edit-grid .full{grid-column:1/-1}.edit-grid label{display:block;margin-bottom:7px;color:#cbd5e1;font-size:12px;font-weight:700}.edit-grid .form-control{width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid rgba(148,163,184,.2);border-radius:9px;background:#0b111b;color:#f8fafc}.edit-grid .form-control:focus{outline:none;border-color:#3b82f6;box-shadow:0 0 0 3px rgba(37,99,235,.14)}.customer-edit-page .btn{display:inline-flex;padding:10px 15px;border-radius:9px;text-decoration:none;font-weight:700;border:1px solid transparent}.customer-edit-page .btn-primary{background:#2563eb;color:white}.customer-edit-page .btn-secondary{background:#172235;color:#cbd5e1;border-color:rgba(148,163,184,.16)}.customer-edit-page .alert{padding:12px 14px;margin-bottom:18px;border-radius:8px;background:rgba(239,68,68,.13);color:#fca5a5}@media(max-width:700px){.customer-edit-page{padding:15px}.edit-grid{grid-template-columns:1fr}.edit-grid .full{grid-column:auto}.customer-edit-page .page-header{align-items:flex-start;gap:12px;flex-direction:column}}
</style>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
