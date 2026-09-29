<?php

require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/payment_gateway.php';

requireActiveUser();
requireTenantContext();
requireModulePermission('payments', 'edit');

$pageTitle = 'M-Pesa Gateway';
$tenantId = (int)getCurrentTenantId();
$message = '';
$error = '';

$gateway = null;
$stmt = $conn->prepare("
    SELECT *
    FROM payment_gateways
    WHERE tenant_id = ? AND provider = 'mpesa'
    ORDER BY is_default DESC, id ASC
    LIMIT 1
");
if ($stmt) {
    $stmt->bind_param('i', $tenantId);
    $stmt->execute();
    $gateway = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        requireCsrf();

        $environment = strtolower(trim((string)($_POST['environment'] ?? 'sandbox')));
        $shortcodeType = strtolower(trim((string)($_POST['shortcode_type'] ?? 'paybill')));
        $shortcode = trim((string)($_POST['shortcode'] ?? ''));
        $consumerKey = trim((string)($_POST['consumer_key'] ?? ''));
        $consumerSecret = trim((string)($_POST['consumer_secret'] ?? ''));
        $passkey = trim((string)($_POST['passkey'] ?? ''));
        $status = isset($_POST['status']) ? 'active' : 'inactive';

        if (!in_array($environment, ['sandbox', 'production'], true)) {
            throw new InvalidArgumentException('Invalid M-Pesa environment.');
        }

        if (!in_array($shortcodeType, ['paybill', 'till'], true)) {
            throw new InvalidArgumentException('Invalid shortcode type.');
        }

        if ($shortcode === '') {
            throw new InvalidArgumentException('Business shortcode/Till number is required.');
        }

        if (!$gateway && ($consumerKey === '' || $consumerSecret === '' || $passkey === '')) {
            throw new InvalidArgumentException('Consumer key, consumer secret and passkey are required for the first configuration.');
        }

        if ($gateway) {
            $consumerKeyEncrypted = $consumerKey !== ''
                ? flexihubGatewayEncrypt($consumerKey)
                : $gateway['consumer_key_encrypted'];
            $consumerSecretEncrypted = $consumerSecret !== ''
                ? flexihubGatewayEncrypt($consumerSecret)
                : $gateway['consumer_secret_encrypted'];
            $passkeyEncrypted = $passkey !== ''
                ? flexihubGatewayEncrypt($passkey)
                : $gateway['passkey_encrypted'];

            $stmt = $conn->prepare("
                UPDATE payment_gateways
                SET environment = ?,
                    shortcode_type = ?,
                    shortcode = ?,
                    consumer_key_encrypted = ?,
                    consumer_secret_encrypted = ?,
                    passkey_encrypted = ?,
                    status = ?,
                    callback_url = ?
                WHERE id = ? AND tenant_id = ?
            ");

            $callbackUrl = baseUrl() . '/payments/mpesa_callback.php?gateway=' . (int)$gateway['id'];

            $stmt->bind_param(
                'ssssssssii',
                $environment,
                $shortcodeType,
                $shortcode,
                $consumerKeyEncrypted,
                $consumerSecretEncrypted,
                $passkeyEncrypted,
                $status,
                $callbackUrl,
                $gateway['id'],
                $tenantId
            );
            $stmt->execute();
            $stmt->close();

            logAudit(
                'mpesa_gateway_updated',
                'payments',
                'Tenant M-Pesa gateway configuration updated',
                'payment_gateway',
                (int)$gateway['id']
            );

            $message = 'M-Pesa gateway configuration updated.';
        } else {
            $stmt = $conn->prepare("
                INSERT INTO payment_gateways
                    (tenant_id, provider, name, environment, shortcode_type, shortcode,
                     consumer_key_encrypted, consumer_secret_encrypted, passkey_encrypted,
                     status, is_default)
                VALUES (?, 'mpesa', 'M-Pesa', ?, ?, ?, ?, ?, ?, ?, 1)
            ");

            $consumerKeyEncrypted = flexihubGatewayEncrypt($consumerKey);
            $consumerSecretEncrypted = flexihubGatewayEncrypt($consumerSecret);
            $passkeyEncrypted = flexihubGatewayEncrypt($passkey);

            $stmt->bind_param(
                'isssssss',
                $tenantId,
                $environment,
                $shortcodeType,
                $shortcode,
                $consumerKeyEncrypted,
                $consumerSecretEncrypted,
                $passkeyEncrypted,
                $status
            );
            $stmt->execute();
            $gatewayId = (int)$conn->insert_id;
            $stmt->close();

            $callbackUrl = baseUrl() . '/payments/mpesa_callback.php?gateway=' . $gatewayId;

            $stmt = $conn->prepare("
                UPDATE payment_gateways
                SET callback_url = ?
                WHERE id = ? AND tenant_id = ?
            ");
            $stmt->bind_param('sii', $callbackUrl, $gatewayId, $tenantId);
            $stmt->execute();
            $stmt->close();

            logAudit(
                'mpesa_gateway_created',
                'payments',
                'Tenant M-Pesa gateway configured',
                'payment_gateway',
                $gatewayId
            );

            $message = 'M-Pesa gateway configured.';
        }

        $stmt = $conn->prepare("
            SELECT *
            FROM payment_gateways
            WHERE tenant_id = ? AND provider = 'mpesa'
            ORDER BY is_default DESC, id ASC
            LIMIT 1
        ");
        $stmt->bind_param('i', $tenantId);
        $stmt->execute();
        $gateway = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

require_once '../includes/header.php';
?>

<div class="dashboard-card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;">
        <div>
            <h2 style="margin-bottom:6px;">M-Pesa Gateway</h2>
            <p style="margin:0;color:#666;">Configure this ISP's own M-Pesa collection credentials. Credentials are encrypted before storage.</p>
        </div>
        <span class="status-badge"><?php echo e($gateway['status'] ?? 'not configured'); ?></span>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success" style="margin-top:18px;"><?php echo e($message); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger" style="margin-top:18px;"><?php echo e($error); ?></div>
    <?php endif; ?>

    <form method="post" style="margin-top:24px;">
        <?php echo csrfField(); ?>

        <div class="form-grid">
            <div class="form-group">
                <label>Environment</label>
                <select name="environment" required>
                    <option value="sandbox" <?php echo (($gateway['environment'] ?? 'sandbox') === 'sandbox') ? 'selected' : ''; ?>>Sandbox</option>
                    <option value="production" <?php echo (($gateway['environment'] ?? '') === 'production') ? 'selected' : ''; ?>>Production</option>
                </select>
            </div>

            <div class="form-group">
                <label>Shortcode Type</label>
                <select name="shortcode_type" required>
                    <option value="paybill" <?php echo (($gateway['shortcode_type'] ?? 'paybill') === 'paybill') ? 'selected' : ''; ?>>PayBill</option>
                    <option value="till" <?php echo (($gateway['shortcode_type'] ?? '') === 'till') ? 'selected' : ''; ?>>Till</option>
                </select>
            </div>

            <div class="form-group">
                <label>Business Shortcode / Till</label>
                <input type="text" name="shortcode" value="<?php echo e($gateway['shortcode'] ?? ''); ?>" required>
            </div>

            <div class="form-group">
                <label>Status</label>
                <label style="display:flex;align-items:center;gap:8px;margin-top:10px;">
                    <input type="checkbox" name="status" value="active" <?php echo (($gateway['status'] ?? '') === 'active') ? 'checked' : ''; ?>>
                    Active
                </label>
            </div>

            <div class="form-group">
                <label>Consumer Key</label>
                <input type="password" name="consumer_key" autocomplete="new-password" placeholder="<?php echo $gateway ? 'Leave blank to keep current' : ''; ?>">
            </div>

            <div class="form-group">
                <label>Consumer Secret</label>
                <input type="password" name="consumer_secret" autocomplete="new-password" placeholder="<?php echo $gateway ? 'Leave blank to keep current' : ''; ?>">
            </div>

            <div class="form-group">
                <label>STK Passkey</label>
                <input type="password" name="passkey" autocomplete="new-password" placeholder="<?php echo $gateway ? 'Leave blank to keep current' : ''; ?>">
            </div>
        </div>

        <?php if ($gateway && !empty($gateway['callback_url'])): ?>
            <div class="dashboard-card" style="margin-top:20px;background:#f8f9fa;">
                <strong>Callback URL</strong>
                <div style="margin-top:8px;word-break:break-all;"><?php echo e($gateway['callback_url']); ?></div>
                <small style="display:block;margin-top:8px;color:#666;">This URL must be publicly reachable over HTTPS in production.</small>
            </div>
        <?php endif; ?>

        <div style="margin-top:24px;">
            <button type="submit" class="btn btn-primary">Save M-Pesa Configuration</button>
        </div>
    </form>
</div>

<?php require_once '../includes/footer.php'; ?>
