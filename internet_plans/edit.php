<?php
require_once '../includes/auth.php';

requireLogin();
$tenantId = requireTenant();

$pageTitle = 'Edit Internet Plan';

function internetPlanColumns()
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

$columns = internetPlanColumns();
$errors = [];

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);\nif ($id <= 0) { redirect('index.php'); }\n\n$lookupSql = 'SELECT * FROM internet_plans WHERE id = ?';\n$lookupTypes = 'i';\n$lookupParams = [$id];\nif (in_array('tenant_id', $columns, true)) { $lookupSql .= ' AND tenant_id = ?'; $lookupTypes .= 'i'; $lookupParams[] = $tenantId; }\n$lookupSql .= ' LIMIT 1';\n$lookup = $conn->prepare($lookupSql);\nif (!$lookup) { die('Unable to load the internet plan.'); }\n$lookup->bind_param($lookupTypes, ...$lookupParams);\n$lookup->execute();\n$existing = $lookup->get_result()->fetch_assoc();\n$lookup->close();\nif (!$existing) { die('Internet plan not found.'); }\n\n$values = [
    'name' => '',
    'price' => '',
    'billing_cycle' => 'monthly',
    'billing_days' => '',
    'download_speed' => '',
    'upload_speed' => '',
    'description' => '',
    'status' => 'active',
];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {\n    foreach ($values as $field => $default) {\n        if (array_key_exists($field, $existing)) { $values[$field] = (string)$existing[$field]; }\n    }\n}\n\nif ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

    if (in_array('billing_days', $columns, true) && $values['billing_days'] !== '' && (!ctype_digit($values['billing_days']) || (int)$values['billing_days'] < 1)) {
        $errors[] = 'Billing days must be a positive whole number.';
    }

    if (!$errors) {
        $allowed = ['name','price','billing_cycle','billing_days','download_speed','upload_speed','description','status'];
        $insert = [];
        foreach ($allowed as $field) {
            if (in_array($field, $columns, true)) {
                $insert[$field] = $values[$field];
            }
        }

        if (in_array('tenant_id', $columns, true)) {
            $insert['tenant_id'] = $tenantId;
        }

        $fields = array_keys($insert);
        $placeholders = implode(',', array_fill(0, count($fields), '?'));
        $types = '';
        $bindValues = [];

        foreach ($fields as $field) {
            $types .= $field === 'tenant_id' ? 'i' : 's';
            $bindValues[] = $insert[$field];
        }

        $setParts = [];\n        foreach ($fields as $field) { $setParts[] = $field . ' = ?'; }\n        $sql = 'UPDATE internet_plans SET ' . implode(',', $setParts) . ' WHERE id = ?';\n        $types .= 'i';\n        $bindValues[] = $id;
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $bind = [$types];
            foreach ($bindValues as $key => $value) {
                $bind[] = &$bindValues[$key];
            }
            call_user_func_array([$stmt, 'bind_param'], $bind);

            if ($stmt->execute()) {
                $stmt->close();
                redirect('index.php');
            }

            $errors[] = 'Unable to save the plan: ' . $stmt->error;
            $stmt->close();
        } else {
            $errors[] = 'Unable to prepare the plan.';
        }
    }
}

require_once '../includes/header.php';
?>

<div class="dashboard-card">
    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <?= e(implode(' ', $errors)) ?>
        </div>
    <?php endif; ?>

    <form method="post">
        <?= csrfField() ?>\n        <input type="hidden" name="id" value="<?= $id ?>">

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
                        <?php foreach (['daily'=>'Daily','weekly'=>'Weekly','monthly'=>'Monthly','quarterly'=>'Quarterly','yearly'=>'Yearly'] as $key=>$label): ?>
                            <option value="<?= $key ?>" <?= $values['billing_cycle'] === $key ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <?php if (in_array('billing_days', $columns, true)): ?>
                <div class="form-group">
                    <label>Billing Days</label>
                    <input type="number" min="1" name="billing_days" value="<?= e($values['billing_days']) ?>" placeholder="e.g. 30">
                </div>
            <?php endif; ?>

            <?php if (in_array('download_speed', $columns, true)): ?>
                <div class="form-group">
                    <label>Download Speed</label>
                    <input type="text" name="download_speed" value="<?= e($values['download_speed']) ?>" placeholder="e.g. 10 Mbps">
                </div>
            <?php endif; ?>

            <?php if (in_array('upload_speed', $columns, true)): ?>
                <div class="form-group">
                    <label>Upload Speed</label>
                    <input type="text" name="upload_speed" value="<?= e($values['upload_speed']) ?>" placeholder="e.g. 5 Mbps">
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
            <button type="submit" class="btn btn-primary">Save Plan</button>
            <a href="index.php" class="btn btn-light">Cancel</a>
        </div>
    </form>
</div>

<?php require_once '../includes/footer.php'; ?>
