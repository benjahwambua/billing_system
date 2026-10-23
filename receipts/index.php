<?php
require_once '../includes/auth.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('receipts', 'view');

$tenantId = (int) getCurrentTenantId();
if ($tenantId <= 0) {
    http_response_code(403);
    exit('A valid tenant context is required.');
}

if (!flexihubTableHasColumn('receipts', 'tenant_id')) {
    http_response_code(503);
    exit('Receipt tenant isolation is unavailable. Please contact the administrator.');
}

$rows = [];
$receiptExists = $conn->query("SHOW TABLES LIKE 'receipts'");
if (!$receiptExists || $receiptExists->num_rows === 0) {
    http_response_code(503);
    exit('Receipts are not available because the required table is missing.');
}

$select = ['r.*'];
$joins = '';
if (
    flexihubTableHasColumn('receipts', 'payment_id') &&
    flexihubTableHasColumn('payments', 'id') &&
    flexihubTableHasColumn('payments', 'tenant_id')
) {
    $paymentFields = [
        'payment_number' => 'payment_number',
        'payment_method' => 'payment_method',
        'payment_date' => 'payment_date',
        'reference' => 'reference',
    ];
    foreach ($paymentFields as $field => $alias) {
        if (flexihubTableHasColumn('payments', $field)) {
            $select[] = 'p.' . $field . ' AS joined_' . $alias;
        }
    }
    if (flexihubTableHasColumn('payments', 'amount')) {
        $select[] = 'p.amount AS joined_payment_amount';
    }
    $joins .= ' LEFT JOIN payments p ON p.id = r.payment_id AND p.tenant_id = r.tenant_id';
}

if (
    flexihubTableHasColumn('receipts', 'customer_id') &&
    flexihubTableHasColumn('customers', 'id') &&
    flexihubTableHasColumn('customers', 'tenant_id')
) {
    foreach (['customer_number', 'first_name', 'last_name', 'phone'] as $field) {
        if (flexihubTableHasColumn('customers', $field)) {
            $select[] = 'c.' . $field . ' AS joined_customer_' . $field;
        }
    }
    $joins .= ' LEFT JOIN customers c ON c.id = r.customer_id AND c.tenant_id = r.tenant_id';
}

$sql = 'SELECT ' . implode(', ', $select) . ' FROM receipts r' . $joins . ' WHERE r.tenant_id = ? ORDER BY r.id DESC LIMIT 500';
$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log('Flexihub receipts: failed to prepare tenant-scoped receipt query: ' . $conn->error);
    http_response_code(503);
    exit('Unable to load receipts safely.');
}
$stmt->bind_param('i', $tenantId);
if (!$stmt->execute()) {
    error_log('Flexihub receipts: failed to execute tenant-scoped receipt query: ' . $stmt->error);
    $stmt->close();
    http_response_code(503);
    exit('Unable to load receipts safely.');
}
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}
$stmt->close();

$pageTitle = 'Receipts';
require_once '../includes/header.php';
?>
<div class="dashboard-card">
    <div class="page-header">
        <div>
            <h2>Receipts</h2>
            <p>Payment receipts issued to customers in your organisation.</p>
        </div>
    </div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Receipt</th><th>Payment Reference</th><th>Customer</th><th>Amount</th><th>Method</th><th>Date</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $x): ?>
                <?php
                $customerName = trim(
                    (string) ($x['joined_customer_first_name'] ?? '') . ' ' .
                    (string) ($x['joined_customer_last_name'] ?? '')
                );
                $customerLabel = $x['joined_customer_customer_number'] ?? '';
                if ($customerName !== '') {
                    $customerLabel .= ($customerLabel !== '' ? ' — ' : '') . $customerName;
                }
                if ($customerLabel === '') {
                    $customerLabel = (string) ($x['customer_id'] ?? '—');
                }
                $receiptAmount = $x['amount'] ?? $x['joined_payment_amount'] ?? 0;
                $receiptDate = $x['receipt_date'] ?? $x['created_at'] ?? $x['joined_payment_date'] ?? '';
                ?>
                <tr>
                    <td><a href="view.php?id=<?= (int) $x['id'] ?>"><?= e($x['receipt_number'] ?? $x['number'] ?? $x['id']) ?></a></td>
                    <td><?= e($x['joined_payment_number'] ?? $x['reference'] ?? $x['payment_id'] ?? '—') ?></td>
                    <td><?= e($customerLabel) ?></td>
                    <td><?= e(formatMoney($receiptAmount)) ?></td>
                    <td><?= e($x['joined_payment_method'] ?? $x['payment_method'] ?? '—') ?></td>
                    <td><?= e($receiptDate) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="6">No receipts found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
