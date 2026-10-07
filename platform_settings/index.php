<?php
require_once '../includes/auth.php';
requireLogin();
requireHostContext();
require_once '../includes/payment_gateway.php';

$pageTitle = 'Platform Settings';
$allowed = [
    'company_name' => 'Company Name',
    'timezone' => 'Timezone',
    'currency' => 'Default Currency',
    'default_trial_days' => 'Default Trial Days',
    'support_email' => 'Support Email',
    'support_phone' => 'Support Phone'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    foreach ($allowed as $key => $label) {
        $value = trim((string)($_POST[$key] ?? ''));
        if ($key === 'default_trial_days') $value = (string)max(0, min(365, (int)$value));
        $stmt = $conn->prepare("INSERT INTO platform_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
        if ($stmt) { $stmt->bind_param('ss', $key, $value); $stmt->execute(); $stmt->close(); }
    }
    $saasEnabled = isset($_POST['saas_billing_enabled']) && $_POST['saas_billing_enabled'] === '1' ? '1' : '0';
    $stmt = $conn->prepare("INSERT INTO platform_settings (setting_key, setting_value) VALUES ('saas_billing_enabled', ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
    if ($stmt) { $stmt->bind_param('s', $saasEnabled); $stmt->execute(); $stmt->close(); }

    $environment = in_array(($_POST['mpesa_environment'] ?? 'sandbox'), ['sandbox','production'], true) ? $_POST['mpesa_environment'] : 'sandbox';
    $shortcodeType = in_array(($_POST['mpesa_shortcode_type'] ?? 'paybill'), ['paybill','till'], true) ? $_POST['mpesa_shortcode_type'] : 'paybill';
    $shortcode = trim((string)($_POST['mpesa_shortcode'] ?? ''));
    $name = trim((string)($_POST['mpesa_name'] ?? 'Flexihub M-Pesa')) ?: 'Flexihub M-Pesa';
    $consumerKey = trim((string)($_POST['mpesa_consumer_key'] ?? ''));
    $consumerSecret = trim((string)($_POST['mpesa_consumer_secret'] ?? ''));
    $passkey = trim((string)($_POST['mpesa_passkey'] ?? ''));
    $gateway = $conn->query("SELECT * FROM platform_payment_gateways WHERE provider='mpesa' LIMIT 1")->fetch_assoc();
    $token = $gateway['callback_token'] ?? bin2hex(random_bytes(32));
    if ($consumerKey !== '') $consumerKey = flexihubGatewayEncrypt($consumerKey);
    else $consumerKey = $gateway['consumer_key_encrypted'] ?? null;
    if ($consumerSecret !== '') $consumerSecret = flexihubGatewayEncrypt($consumerSecret);
    else $consumerSecret = $gateway['consumer_secret_encrypted'] ?? null;
    if ($passkey !== '') $passkey = flexihubGatewayEncrypt($passkey);
    else $passkey = $gateway['passkey_encrypted'] ?? null;
    $callbackUrl = rtrim(baseUrl(), '/') . '/payments/mpesa_callback.php?platform=1&gateway=' . (int)($gateway['id'] ?? 0) . '&token=' . rawurlencode($token);
    if ($gateway) {
        $stmt = $conn->prepare("UPDATE platform_payment_gateways SET name=?,environment=?,shortcode_type=?,shortcode=?,consumer_key_encrypted=?,consumer_secret_encrypted=?,passkey_encrypted=?,callback_token=?,callback_url=? WHERE id=? AND provider='mpesa'");
        if ($stmt) { $gid=(int)$gateway['id']; $stmt->bind_param('sssssssssi',$name,$environment,$shortcodeType,$shortcode,$consumerKey,$consumerSecret,$passkey,$token,$callbackUrl,$gid); $stmt->execute(); $stmt->close(); }
    } elseif ($shortcode !== '') {
        $stmt = $conn->prepare("INSERT INTO platform_payment_gateways (provider,name,environment,shortcode_type,shortcode,consumer_key_encrypted,consumer_secret_encrypted,passkey_encrypted,callback_token,callback_url,status,is_default) VALUES ('mpesa',?,?,?,?,?,?,?,?,?,'inactive',1)");
        if ($stmt) {
            $stmt->bind_param('sssssssss',$name,$environment,$shortcodeType,$shortcode,$consumerKey,$consumerSecret,$passkey,$token,$callbackUrl);
            $stmt->execute();
            $newGatewayId=(int)$conn->insert_id;
            $stmt->close();
            if($newGatewayId>0){
                $callbackUrl=rtrim(baseUrl(),'/').'/payments/mpesa_callback.php?platform=1&gateway='.$newGatewayId.'&token='.rawurlencode($token);
                $u=$conn->prepare("UPDATE platform_payment_gateways SET callback_url=? WHERE id=?");
                if($u){$u->bind_param('si',$callbackUrl,$newGatewayId);$u->execute();$u->close();}
            }
        }
    }
    if (isset($_POST['mpesa_status'])) {
        $status = $_POST['mpesa_status'] === 'active' ? 'active' : 'inactive';
        $conn->query("UPDATE platform_payment_gateways SET status='".$conn->real_escape_string($status)."' WHERE provider='mpesa'");
    }
    setFlash('success', 'Platform settings updated successfully.');
    redirect('index.php');
}

$settings = [];
$result = $conn->query("SELECT setting_key, setting_value FROM platform_settings");
if ($result) while ($row = $result->fetch_assoc()) $settings[$row['setting_key']] = $row['setting_value'];

$platformGateway = $conn->query("SELECT * FROM platform_payment_gateways WHERE provider='mpesa' LIMIT 1")->fetch_assoc();
$flash = getFlash();
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<div class="main-content">
    <div class="page-header"><div><h1>Platform Settings</h1><p>Global configuration used by the Flexihub platform.</p></div></div>
    <?php if ($flash): ?><div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>
    <div class="card">
        <form method="post">
            <?= csrfField() ?>
            <?php foreach ($allowed as $key => $label): ?>
                <div class="form-row"><label for="<?= e($key) ?>"><?= e($label) ?></label><input id="<?= e($key) ?>" name="<?= e($key) ?>" value="<?= e($settings[$key] ?? ($key === 'timezone' ? 'Africa/Nairobi' : ($key === 'currency' ? 'KES' : ''))) ?>"></div>
            <?php endforeach; ?>
            <div class="form-row"><label for="saas_billing_enabled">SaaS Billing Engine</label><label class="toggle"><input id="saas_billing_enabled" type="checkbox" name="saas_billing_enabled" value="1" <?= (($settings['saas_billing_enabled'] ?? '0') === '1') ? 'checked' : '' ?>> Enable Flexihub tenant subscription billing</label></div>
            <div class="notice">Keep this disabled until the SaaS plan, invoice and payment flow has been configured and tested.</div>
            <hr>
            <h2>Platform M-Pesa Gateway</h2>
            <p>These credentials belong to Flexihub and are used only for tenant SaaS subscription payments. Tenant M-Pesa gateways remain separate for customer collections.</p>
            <div class="form-row"><label>Name</label><input name="mpesa_name" value="<?= e($platformGateway['name'] ?? 'Flexihub M-Pesa') ?>"></div>
            <div class="form-row"><label>Environment</label><select name="mpesa_environment"><option value="sandbox" <?= (($platformGateway['environment'] ?? 'sandbox') === 'sandbox') ? 'selected' : '' ?>>Sandbox</option><option value="production" <?= (($platformGateway['environment'] ?? '') === 'production') ? 'selected' : '' ?>>Production</option></select></div>
            <div class="form-row"><label>Shortcode Type</label><select name="mpesa_shortcode_type"><option value="paybill" <?= (($platformGateway['shortcode_type'] ?? 'paybill') === 'paybill') ? 'selected' : '' ?>>PayBill</option><option value="till" <?= (($platformGateway['shortcode_type'] ?? '') === 'till') ? 'selected' : '' ?>>Till</option></select></div>
            <div class="form-row"><label>Shortcode</label><input name="mpesa_shortcode" value="<?= e($platformGateway['shortcode'] ?? '') ?>" required></div>
            <div class="form-row"><label>Consumer Key</label><input type="password" name="mpesa_consumer_key" autocomplete="new-password" placeholder="<?= !empty($platformGateway['consumer_key_encrypted']) ? 'Configured — leave blank to keep' : '' ?>"></div>
            <div class="form-row"><label>Consumer Secret</label><input type="password" name="mpesa_consumer_secret" autocomplete="new-password" placeholder="<?= !empty($platformGateway['consumer_secret_encrypted']) ? 'Configured — leave blank to keep' : '' ?>"></div>
            <div class="form-row"><label>Passkey</label><input type="password" name="mpesa_passkey" autocomplete="new-password" placeholder="<?= !empty($platformGateway['passkey_encrypted']) ? 'Configured — leave blank to keep' : '' ?>"></div>
            <div class="form-row"><label>Status</label><select name="mpesa_status"><option value="inactive" <?= (($platformGateway['status'] ?? 'inactive') !== 'active') ? 'selected' : '' ?>>Inactive</option><option value="active" <?= (($platformGateway['status'] ?? '') === 'active') ? 'selected' : '' ?>>Active</option></select></div>
            <div class="notice">Callback URL: <?= e($platformGateway['callback_url'] ?? (rtrim(baseUrl(), '/') . '/payments/mpesa_callback.php?platform=1')) ?></div>
            <button type="submit" class="btn btn-primary">Save Settings</button>
        </form>
    </div>
</div>
<style>.main-content{padding:24px}.page-header{margin-bottom:24px}.page-header h1{margin:0 0 6px}.page-header p{margin:0;color:#6b7280}.card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:24px;max-width:800px}.form-row{display:grid;grid-template-columns:200px 1fr;gap:18px;align-items:center;margin-bottom:18px}.form-row label{font-weight:600;font-size:14px}.form-row input:not([type=checkbox]),.form-row select{width:100%;box-sizing:border-box;padding:10px 12px;border:1px solid #d1d5db;border-radius:7px}.toggle{display:flex;align-items:center;gap:8px;font-weight:500!important}.toggle input{width:auto!important}.notice{padding:12px 15px;border-radius:8px;margin:4px 0 20px;background:#fff7ed;color:#9a3412;border:1px solid #fed7aa}.btn{padding:10px 16px;border:0;border-radius:7px;cursor:pointer;font-weight:600}.btn-primary{background:#111827;color:#fff}.alert{padding:12px 16px;border-radius:8px;margin-bottom:18px;background:#dcfce7;color:#166534}@media(max-width:700px){.form-row{grid-template-columns:1fr;gap:7px}}
</style>
<?php require_once '../includes/footer.php'; ?>