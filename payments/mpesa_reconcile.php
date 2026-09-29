<?php

require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/payment_gateway.php';

requireActiveUser();
requireTenantContext();
requireModulePermission('payments', 'edit');

$tenantId = (int)getCurrentTenantId();
$transactionId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$error = '';
$transaction = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        requireCsrf();

        if ($transactionId <= 0) {
            throw new InvalidArgumentException('Invalid M-Pesa transaction.');
        }

        $transaction = flexihubReconcileMpesaTransaction($transactionId);

        if ((int)($transaction['tenant_id'] ?? 0) !== $tenantId) {
            throw new RuntimeException('Transaction does not belong to the current tenant.');
        }

        logAudit(
            'mpesa_transaction_reconciled',
            'payments',
            'M-Pesa gateway transaction reconciled',
            'payment_gateway_transaction',
            $transactionId
        );
        setFlash('success', 'M-Pesa transaction status refreshed: ' . ($transaction['status'] ?? 'unknown') . '.');
        redirect('mpesa_reconcile.php?id=' . $transactionId);
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

if ($transactionId > 0) {
    $transaction = dbFetchOne(
        "SELECT t.*, g.name gateway_name
         FROM payment_gateway_transactions t
         JOIN payment_gateways g ON g.id = t.gateway_id AND g.tenant_id = t.tenant_id
         WHERE t.id = ? AND t.tenant_id = ?
         LIMIT 1",
        'ii',
        $transactionId,
        $tenantId
    );
}

$pageTitle = 'M-Pesa Transaction Reconciliation';
require_once '../includes/header.php';
?>

<div class="dashboard-card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;">
        <div>
            <h2 style="margin-bottom:6px;">M-Pesa Transaction Reconciliation</h2>
            <p style="margin:0;color:#666;">Refresh a pending M-Pesa STK transaction against the provider before any payment or service is finalized.</p>
        </div>
        <a class="btn" href="index.php">Back to Payments</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger" style="margin-top:18px;"><?php echo e($error); ?></div>
    <?php endif; ?>

    <?php if (!$transaction): ?>
        <div class="alert alert-warning" style="margin-top:18px;">M-Pesa transaction not found.</div>
    <?php else: ?>
        <div class="table-responsive" style="margin-top:20px;">
            <table>
                <tbody>
                    <tr><th>ID</th><td><?php echo e($transaction['id']); ?></td></tr>
                    <tr><th>Gateway</th><td><?php echo e($transaction['gateway_name']); ?></td></tr>
                    <tr><th>Amount</th><td><?php echo e(formatMoney($transaction['amount'])); ?></td></tr>
                    <tr><th>Phone</th><td><?php echo e($transaction['phone_number']); ?></td></tr>
                    <tr><th>Reference</th><td><?php echo e($transaction['account_reference']); ?></td></tr>
                    <tr><th>Checkout Request ID</th><td><?php echo e($transaction['checkout_request_id']); ?></td></tr>
                    <tr><th>Status</th><td><?php echo e($transaction['status']); ?></td></tr>
                    <tr><th>Result</th><td><?php echo e($transaction['result_code'] ?? '—'); ?> — <?php echo e($transaction['result_description'] ?? '—'); ?></td></tr>
                    <tr><th>Initiated</th><td><?php echo e($transaction['initiated_at'] ?? '—'); ?></td></tr>
                    <tr><th>Confirmed</th><td><?php echo e($transaction['confirmed_at'] ?? '—'); ?></td></tr>
                </tbody>
            </table>
        </div>

        <?php if (!in_array($transaction['status'], ['completed', 'failed', 'cancelled', 'expired'], true)): ?>
            <form method="post" style="margin-top:20px;">
                <?php echo csrfField(); ?>
                <input type="hidden" name="id" value="<?php echo (int)$transaction['id']; ?>">
                <button type="submit" class="btn btn-primary">Reconcile with M-Pesa</button>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
