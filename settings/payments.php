<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('settings', 'view');

global $conn;
$tenantId = (int) getCurrentTenantId();
$mpesaConfigured = false;
$mpesaStatus = 'Not configured';
$mpesaEnvironment = '—';

$stmt = $conn->prepare("SELECT status, environment FROM payment_gateways WHERE tenant_id = ? AND provider = 'mpesa' ORDER BY is_default DESC, id DESC LIMIT 1");
if ($stmt) {
    $stmt->bind_param('i', $tenantId);
    $stmt->execute();
    $gateway = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($gateway) {
        $mpesaConfigured = true;
        $mpesaStatus = ucfirst((string) $gateway['status']);
        $mpesaEnvironment = ucfirst((string) $gateway['environment']);
    }
}

$pageTitle = 'Payment Settings';
require_once '../includes/header.php';
?>
<div class="dashboard-card">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;">
        <div>
            <h2>Payment Settings</h2>
            <p>Configure how your customers pay for internet packages and services.</p>
        </div>
        <span class="status-badge"><?= e($mpesaStatus) ?></span>
    </div>

    <div class="dashboard-card" style="margin-top:20px;">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:18px;flex-wrap:wrap;">
            <div style="display:flex;align-items:center;gap:14px;">
                <div style="width:48px;height:48px;border-radius:12px;background:#e8f1ff;color:#1769aa;display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:700;">M</div>
                <div>
                    <h3 style="margin:0;">M-Pesa — Daraja API</h3>
                    <p style="margin:5px 0 0;">Safaricom STK Push collection for customer package purchases.</p>
                </div>
            </div>
            <a class="btn btn-primary" href="mpesa.php"><?= $mpesaConfigured ? 'Manage M-Pesa' : 'Configure M-Pesa' ?></a>
        </div>

        <div class="form-grid" style="margin-top:20px;">
            <div class="form-group">
                <label>Configuration status</label>
                <div><?= e($mpesaStatus) ?></div>
            </div>
            <div class="form-group">
                <label>Daraja environment</label>
                <div><?= e($mpesaEnvironment) ?></div>
            </div>
        </div>
        <p style="margin:16px 0 0;color:#64748b;font-size:13px;">
            Configure your Daraja Consumer Key, Consumer Secret, STK Passkey, business shortcode and callback URL on the M-Pesa gateway page. Credentials are stored encrypted and are not displayed after saving.
        </p>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
