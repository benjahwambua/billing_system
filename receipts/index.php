<?php
require_once '../includes/auth.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('payments', 'view');

$tenantId = (int)getCurrentTenantId();
if ($tenantId <= 0) {
    http_response_code(403);
    exit('A valid tenant context is required.');
}

$rows = [];
$tableCheck = $conn->query("SHOW TABLES LIKE 'receipts'");
if (!$tableCheck || $tableCheck->num_rows === 0) {
    http_response_code(503);
    exit('Receipts are not available because the required table is missing.');
}
$columnCheck = $conn->query("SHOW COLUMNS FROM receipts LIKE 'tenant_id'");
if (!$columnCheck || $columnCheck->num_rows === 0) {
    http_response_code(503);
    exit('Receipt tenant isolation is unavailable. Please contact the administrator.');
}

$stmt = $conn->prepare("SELECT * FROM receipts WHERE tenant_id = ? ORDER BY id DESC LIMIT 500");
if (!$stmt) {
    http_response_code(503);
    exit('Unable to load receipts safely.');
}
$stmt->bind_param('i', $tenantId);
if (!$stmt->execute()) {
    $stmt->close();
    http_response_code(503);
    exit('Unable to load receipts safely.');
}
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) $rows[] = $row;
$stmt->close();

$pageTitle = 'Receipts';
require_once '../includes/header.php';
?>
<div class="dashboard-card">
    <h2>Receipts</h2>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Receipt</th><th>Payment</th><th>Customer</th><th>Amount</th><th>Date</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $x): ?>
                <tr>
                    <td><?= e($x['receipt_number'] ?? $x['number'] ?? $x['id']) ?></td>
                    <td><?= e($x['payment_id'] ?? '') ?></td>
                    <td><?= e($x['customer_id'] ?? '') ?></td>
                    <td><?= e(formatMoney($x['amount'] ?? 0)) ?></td>
                    <td><?= e($x['receipt_date'] ?? $x['created_at'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="5">No receipts found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>