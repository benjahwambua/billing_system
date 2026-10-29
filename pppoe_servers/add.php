<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
if (isTenantUser()) requireTenant();
requireModulePermission('pppoe', 'create');
global $conn;

$cols = [];
$q = $conn->query("SHOW COLUMNS FROM pppoe_servers");
if (!$q) die('Unable to read PPPoE server schema.');
while ($x = $q->fetch_assoc()) $cols[] = $x['Field'];
$has = fn($c) => in_array($c, $cols, true);
$tid = (int) getCurrentTenantId();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $name = trim((string)($_POST['name'] ?? ''));
    if (($has('name') || $has('server_name')) && $name === '') {
        $errors[] = 'Server name is required.';
    }
    $status = (string)($_POST['status'] ?? 'active');
    if (!in_array($status, ['active', 'inactive'], true)) {
        $errors[] = 'Invalid server status.';
    }

    if (!$errors) {
        $map = [
            'name' => $name,
            'server_name' => $name,
            'router_id' => (int)($_POST['router_id'] ?? 0),
            'interface' => trim((string)($_POST['interface'] ?? '')),
            'service_name' => trim((string)($_POST['service_name'] ?? '')),
            'status' => $status,
        ];
        $fields = [];
        $values = [];
        $types = '';
        foreach ($map as $column => $value) {
            if ($has($column) && !in_array($column, $fields, true)) {
                $fields[] = $column;
                $values[] = $value;
                $types .= is_int($value) ? 'i' : 's';
            }
        }
        if ($has('tenant_id')) {
            if ($tid <= 0) {
                $errors[] = 'A valid tenant context is required.';
            } else {
                $fields[] = 'tenant_id';
                $values[] = $tid;
                $types .= 'i';
            }
        }

        if (!$errors && !$fields) {
            $errors[] = 'No compatible PPPoE server fields were found.';
        }

        if (!$errors) {
            $stmt = $conn->prepare(
                'INSERT INTO pppoe_servers (' . implode(',', $fields) . ')
                 VALUES (' . implode(',', array_fill(0, count($fields), '?')) . ')'
            );
            if (!$stmt) {
                $errors[] = 'Unable to prepare PPPoE server save.';
            } else {
                $stmt->bind_param($types, ...$values);
                if ($stmt->execute()) {
                    redirect('index.php');
                }
                $errors[] = 'Unable to save PPPoE server: ' . $stmt->error;
                $stmt->close();
            }
        }
    }
}
?>
<?php require '../includes/header.php'; ?>
<div class="page-content">
    <div class="page-header">
        <div><h1>Add PPPoE Server</h1></div>
        <a class="btn btn-secondary" href="index.php">Back</a>
    </div>
    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <ul><?php foreach (array_unique($errors) as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>
    <div class="card" style="max-width:760px;padding:22px">
        <form method="post">
            <?= csrfField() ?>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                <?php if ($has('name') || $has('server_name')): ?>
                    <label>Name<input name="name" required value="<?= e((string)($_POST['name'] ?? '')) ?>"></label>
                <?php endif; ?>
                <?php if ($has('router_id')): ?>
                    <label>Router ID<input type="number" min="1" name="router_id" value="<?= e((string)($_POST['router_id'] ?? '')) ?>"></label>
                <?php endif; ?>
                <?php if ($has('interface')): ?>
                    <label>Interface<input name="interface" placeholder="ether1" value="<?= e((string)($_POST['interface'] ?? '')) ?>"></label>
                <?php endif; ?>
                <?php if ($has('service_name')): ?>
                    <label>Service Name<input name="service_name" value="<?= e((string)($_POST['service_name'] ?? '')) ?>"></label>
                <?php endif; ?>
                <?php if ($has('status')): ?>
                    <label>Status
                        <select name="status">
                            <option value="active" <?= (($_POST['status'] ?? 'active') === 'active') ? 'selected' : '' ?>>active</option>
                            <option value="inactive" <?= (($_POST['status'] ?? 'active') === 'inactive') ? 'selected' : '' ?>>inactive</option>
                        </select>
                    </label>
                <?php endif; ?>
            </div>
            <button class="btn btn-primary" style="margin-top:18px">Save Server</button>
        </form>
    </div>
</div>
<?php require '../includes/footer.php'; ?>
