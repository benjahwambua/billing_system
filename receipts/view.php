<?php
require_once '../includes/auth.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('receipts', 'view');

$tenantId = (int) getCurrentTenantId();
$receiptId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($tenantId <= 0 || !$receiptId || $receiptId <= 0) {
    http_response_code($tenantId <= 0 ? 403 : 400);
    exit($tenantId <= 0 ? 'A valid tenant context is required.' : 'A valid receipt ID is required.');
}

if (!function_exists('flexihubTableHasColumn') || !flexihubTableHasColumn('receipts', 'tenant_id')) {
    http_response_code(503);
    exit('Receipt tenant isolation is unavailable. Please contact the administrator.');
}

function receiptFetchTenantRow(mysqli $conn, string $table, int $id, int $tenantId): ?array
{
    $allowedTables = ['receipts', 'payments', 'invoices', 'customers'];
    if (!in_array($table, $allowedTables, true)) {
        return null;
    }
    if (!flexihubTableHasColumn($table, 'id') || !flexihubTableHasColumn($table, 'tenant_id')) {
        return null;
    }
    $stmt = $conn->prepare("SELECT * FROM `{$table}` WHERE id = ? AND tenant_id = ? LIMIT 1");
    if (!$stmt) {
        error_log('Flexihub receipt view: failed to prepare tenant-scoped lookup for ' . $table . '.');
        return null;
    }
    $stmt->bind_param('ii', $id, $tenantId);
    if (!$stmt->execute()) {
        error_log('Flexihub receipt view: failed to execute tenant-scoped lookup for ' . $table . '.');
        $stmt->close();
        return null;
    }
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    return $row ?: null;
}

$receipt = receiptFetchTenantRow($conn, 'receipts', (int) $receiptId, $tenantId);
if (!$receipt) {
    http_response_code(404);
    exit('Receipt not found.');
}

$payment = null;
$invoice = null;
$customer = null;
if (!empty($receipt['payment_id'])) {
    $payment = receiptFetchTenantRow($conn, 'payments', (int) $receipt['payment_id'], $tenantId);
}
$invoiceId = $receipt['invoice_id'] ?? ($payment['invoice_id'] ?? null);
if ($invoiceId && flexihubTableHasColumn('invoices', 'tenant_id')) {
    $invoice = receiptFetchTenantRow($conn, 'invoices', (int) $invoiceId, $tenantId);
}
$customerId = $receipt['customer_id'] ?? ($invoice['customer_id'] ?? null);
if ($customerId && flexihubTableHasColumn('customers', 'tenant_id')) {
    $customer = receiptFetchTenantRow($conn, 'customers', (int) $customerId, $tenantId);
}

$receiptNumber = $receipt['receipt_number'] ?? $receipt['number'] ?? ('RCT-' . $receipt['id']);
$paymentReference = $payment['reference'] ?? $receipt['reference'] ?? '—';
$paymentMethod = $payment['payment_method'] ?? $receipt['payment_method'] ?? '—';
$paymentDate = $payment['payment_date'] ?? $receipt['receipt_date'] ?? $receipt['created_at'] ?? '—';
$amount = $receipt['amount'] ?? $payment['amount'] ?? 0;
$customerName = trim((string) ($customer['first_name'] ?? '') . ' ' . (string) ($customer['last_name'] ?? ''));
$customerName = $customerName !== '' ? $customerName : ($customer['name'] ?? 'Customer');
$customerNumber = $customer['customer_number'] ?? '';
$customerPhone = $customer['phone'] ?? '';
$invoiceNumber = $invoice['invoice_number'] ?? $invoice['number'] ?? ($invoiceId ? ('Invoice #' . $invoiceId) : '—');
$pageTitle = 'Receipt ' . $receiptNumber;
require_once '../includes/header.php';
?>
<style>
.receipt-toolbar{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:18px}
.receipt-paper{max-width:820px;margin:0 auto;background:#fff;color:#172033;border-radius:10px;padding:42px;box-shadow:0 8px 28px rgba(0,0,0,.12);border-top:6px solid #1769d2}
.receipt-brand{display:flex;justify-content:space-between;gap:20px;border-bottom:1px solid #dbe3ee;padding-bottom:22px;margin-bottom:26px}
.receipt-brand h1{margin:0;color:#102a4c;font-size:28px}.receipt-brand p{margin:5px 0 0;color:#617087}
.receipt-title{color:#1769d2;letter-spacing:2px;font-size:13px;font-weight:800;text-transform:uppercase}
.receipt-number{font-size:19px;font-weight:700;color:#102a4c;margin-top:6px;overflow-wrap:anywhere}
.receipt-grid{display:grid;grid-template-columns:1fr 1fr;gap:24px;margin:22px 0}
.receipt-label{font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#68778d;margin-bottom:6px}
.receipt-value{font-size:15px;font-weight:600;overflow-wrap:anywhere}
.receipt-total{background:#eef5ff;border:1px solid #d3e4ff;border-radius:8px;padding:20px;margin:28px 0;display:flex;justify-content:space-between;align-items:center;gap:12px}
.receipt-total strong{font-size:25px;color:#0d4fa8}
.receipt-note{color:#526176;font-size:13px;margin-top:20px}
.receipt-signatures{display:grid;grid-template-columns:1fr 1fr;gap:45px;margin-top:58px}
.receipt-signatures div{border-top:1px solid #8794a6;padding-top:9px;color:#526176;font-size:12px}
@media(max-width:600px){.receipt-paper{padding:22px}.receipt-grid{grid-template-columns:1fr}.receipt-brand{flex-direction:column}.receipt-signatures{gap:22px}}
@media print{
  body{background:#fff!important;color:#172033!important}
  .sidebar,.topbar,header,footer,.receipt-toolbar,.page-header,.main-header,.navbar{display:none!important}
  .main-content,.content,.container,.dashboard-card{margin:0!important;padding:0!important;width:100%!important;max-width:none!important;box-shadow:none!important;background:#fff!important;border:0!important}
  .receipt-paper{max-width:none;margin:0;padding:12mm;border:0;border-top:4px solid #1769d2;border-radius:0;box-shadow:none}
  @page{size:A4;margin:12mm}
}
</style>
<div class="receipt-toolbar">
  <div><a href="index.php">← Back to receipts</a></div>
  <p>To print or save as PDF, use your browser's <strong>Ctrl+P</strong> command.</p>
</div>
<div class="receipt-paper">
  <div class="receipt-brand">
    <div>
      <h1>Flexihub</h1>
      <p>Billing &amp; Internet Services</p>
    </div>
    <div>
      <div class="receipt-title">Payment receipt</div>
      <div class="receipt-number"><?= e($receiptNumber) ?></div>
      <p><?= e((string) $paymentDate) ?></p>
    </div>
  </div>
  <div class="receipt-grid">
    <div>
      <div class="receipt-label">Received from</div>
      <div class="receipt-value"><?= e($customerName) ?></div>
      <?php if ($customerNumber !== ''): ?><div><?= e($customerNumber) ?></div><?php endif; ?>
      <?php if ($customerPhone !== ''): ?><div><?= e($customerPhone) ?></div><?php endif; ?>
    </div>
    <div>
      <div class="receipt-label">Invoice</div>
      <div class="receipt-value"><?= e($invoiceNumber) ?></div>
      <div class="receipt-label" style="margin-top:14px">Payment method</div>
      <div class="receipt-value"><?= e($paymentMethod) ?></div>
      <div class="receipt-label" style="margin-top:14px">Payment reference</div>
      <div class="receipt-value"><?= e($paymentReference) ?></div>
    </div>
  </div>
  <div class="receipt-total">
    <span><span class="receipt-label">Amount received</span><br><strong><?= e(formatMoney($amount)) ?></strong></span>
    <span>PAID</span>
  </div>
  <p class="receipt-note">Thank you for your payment. Please retain this receipt for your records. This receipt reflects the payment record available in the system.</p>
  <div class="receipt-signatures">
    <div>Received / authorised by</div>
    <div>Customer acknowledgement</div>
  </div>
</div>
<?php require_once '../includes/footer.php'; ?>
