<?php
require_once '../includes/auth.php';
requireLogin();
requireHostContext();

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
    setFlash('success', 'Platform settings updated successfully.');
    redirect('index.php');
}

$settings = [];
$result = $conn->query("SELECT setting_key, setting_value FROM platform_settings");
if ($result) while ($row = $result->fetch_assoc()) $settings[$row['setting_key']] = $row['setting_value'];

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
            <button type="submit" class="btn btn-primary">Save Settings</button>
        </form>
    </div>
</div>
<style>.main-content{padding:24px}.page-header{margin-bottom:24px}.page-header h1{margin:0 0 6px}.page-header p{margin:0;color:#6b7280}.card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:24px;max-width:800px}.form-row{display:grid;grid-template-columns:200px 1fr;gap:18px;align-items:center;margin-bottom:18px}.form-row label{font-weight:600;font-size:14px}.form-row input:not([type=checkbox]){width:100%;box-sizing:border-box;padding:10px 12px;border:1px solid #d1d5db;border-radius:7px}.toggle{display:flex;align-items:center;gap:8px;font-weight:500!important}.toggle input{width:auto!important}.notice{padding:12px 15px;border-radius:8px;margin:4px 0 20px;background:#fff7ed;color:#9a3412;border:1px solid #fed7aa}.btn{padding:10px 16px;border:0;border-radius:7px;cursor:pointer;font-weight:600}.btn-primary{background:#111827;color:#fff}.alert{padding:12px 16px;border-radius:8px;margin-bottom:18px;background:#dcfce7;color:#166534}@media(max-width:700px){.form-row{grid-template-columns:1fr;gap:7px}}
</style>
<?php require_once '../includes/footer.php'; ?>