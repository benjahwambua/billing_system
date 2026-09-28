<?php
require_once '../includes/auth.php';

requireLogin();
$tenantId = requireTenant();

$pageTitle = 'Edit Internet Plan';

function internetPlanEditColumns()
{
    global $conn;
    $columns = [];
    $result = $conn->query("SHOW COLUMNS FROM internet_plans");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
    }
    return $columns;
}

$columns = internetPlanEditColumns();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if ($id <= 0) {
    redirect('index.php');
}

$sql = 'SELECT * FROM internet_plans WHERE id = ?';
$types = 'i';
$params = [$id];

if (in_array('tenant_id', $columns, true)) {
    $sql .= ' AND tenant_id = ?';
    $types .= 'i';
    $params[] = $tenantId;
}

$sql .= ' LIMIT 1';

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die('Unable to load the internet plan.');
}

$bind = [$types];
foreach ($params as $key => $value) {
    $bind[] = &$params[$key];
}
call_user_func_array([$stmt, 'bind_param'], $bind);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$existing) {
    die('Internet plan not found.');
}

$values = [
    'name' => $existing['name'] ?? '',
    'price' => $existing['price'] ?? '',
    'billing_cycle' => $existing['billing_cycle'] ?? 'monthly',
    'billing_days' => $existing['billing_days'] ?? '',
    'download_speed' => $existing['download_speed'] ?? '',
    'upload_speed' => $existing['upload_speed'] ?? '',
    'description' => $existing['description'] ?? '',
    'status' => $existing['status'] ?? 'active',
];

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    foreach ($values as $field => $default) {
        $values[$field] = trim((string)($_POST[$field] ?? $default));
    }

    if ($values['name'] === '') {
        $errors[] = 'Plan name is required.';
    }

    if (in_array('price', $columns, true) && (!is_numeric($values['price']) || (float)$values['price'] < 0)) {
        $errors[] = 'Price must be a valid amount of 0 or more.';
    }

    if (in_array('billing_days', $columns, true) && $values['billing_days'] !== '' &&
        (!ctype_digit($values['billing_days']) || (int)$values['billing_days'] < 1)) {
        $errors[] = 'Billing days must be a positive whole number.';
    }

    if (!$errors) {
        $allowed = ['name','price','billing_cycle','billing_days','download_speed','upload_speed','description','status'];
        $updates = [];

        foreach ($allowed as $field) {
            if (in_array($field, $columns, true)) {
                $updates[$field] = $values[$field];
            }
        }

        $set = [];
        $bindValues = [];
        $bindTypes = '';

        foreach ($updates as $field => $value) {
            $set[] = $field . ' = ?';
            $bindTypes .= 's';
            $bindValues[] = $value;
        }

        $sql = 'UPDATE internet_plans SET ' . implode(', ', $set) . ' WHERE id = ?';
        $bindTypes .= 'i';
        $bindValues[] = $id;

        if (in_array('tenant_id', $columns, true)) {
            $sql .= ' AND tenant_id = ?';
            $bindTypes .= 'i';
            $bindValues[] = $tenantId;
        }

        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $bind = [$bindTypes];
            foreach ($bindValues as $key => $value) {
                $bind[] = &$bindValues[$key];
            }
            call_user_func_array([$stmt, 'bind_param'], $bind);

            if ($stmt->execute()) {
                $stmt->close();
                redirect('index.php');
            }

            $errors[] = 'Unable to update the plan: ' . $stmt->error;
            $stmt->close();
        } else {
            $errors[] = 'Unable to prepare the update.';
        }
    }
}

require_once '../includes/header.php';
?>

<div class="dashboard-card">
    <?php if ($errors): ?>
        <div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div>
    <?php endif; ?>

    <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="id" value="<?= $id ?>">

        <div class="form-grid">
            <?php if (in_array('name', $columns, true)): ?>
                <div class="form-group">
                    <label>Plan Name *</label>
                    <input type="text" name="name" value="<?= e($values['name']) ?>" required>
                </div>
            <?php endif; ?>

            <?php if (in_array('price', $columns, true)): ?>
                <div class="form-group">
                    <label>Price *</label>
                    <input type="number" step="0.01" min="0" name="price" value="<?= e($values['price']) ?>" required>
                </div>
            <?php endif; ?>

            <?php if (in_array('billing_cycle', $columns, true)): ?>
                <div class="form-group">
                    <label>Billing Cycle</label>
                    <select name="billing_cycle">
                        <?php foreach (['daily'=>'Daily','weekly'=>'Weekly','monthly'=>'Monthly','quarterly'=>'Quarterly','yearly'=>'Yearly'] as $key => $label): ?>
                            <option value="<?= $key ?>" <?= $values['billing_cycle'] === $key ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <?php if (in_array('billing_days', $columns, true)): ?>
                <div class="form-group">
                    <label>Billing Days</label>
                    <input type="number" min="1" name="billing_days" value="<?= e($values['billing_days']) ?>">
                </div>
            <?php endif; ?>

            <?php if (in_array('download_speed', $columns, true)): ?>
                <div class="form-group">
                    <label>Download Speed</label>
                    <input type="text" name="download_speed" value="<?= e($values['download_speed']) ?>">
                </div>
            <?php endif; ?>

            <?php if (in_array('upload_speed', $columns, true)): ?>
                <div class="form-group">
                    <label>Upload Speed</label>
                    <input type="text" name="upload_speed" value="<?= e($values['upload_speed']) ?>">
                </div>
            <?php endif; ?>

            <?php if (in_array('status', $columns, true)): ?>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="active" <?= $values['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $values['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
            <?php endif; ?>
        </div>

        <?php if (in_array('description', $columns, true)): ?>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="4"><?= e($values['description']) ?></textarea>
            </div>
        <?php endif; ?>

        <div style="margin-top:20px;">
            <button type="submit" class="btn btn-primary">Update Plan</button>
            <a href="index.php" class="btn btn-light">Cancel</a>
        </div>
    </form>
</div>

<?php require_once '../includes/footer.php'; ?>
