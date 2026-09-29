<?php

/*
 * Public M-Pesa callback endpoint.
 *
 * This endpoint intentionally does not require a logged-in user.
 * It records the callback and leaves the transaction in callback_received
 * until provider reconciliation confirms the transaction.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$gatewayId = isset($_GET['gateway']) ? (int)$_GET['gateway'] : 0;
$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);

if ($gatewayId <= 0 || !is_array($payload)) {
    http_response_code(400);
    echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Invalid callback request']);
    exit;
}

$stk = $payload['Body']['stkCallback'] ?? null;
$checkoutRequestId = is_array($stk) ? trim((string)($stk['CheckoutRequestID'] ?? '')) : '';

if ($checkoutRequestId === '') {
    http_response_code(400);
    echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Missing CheckoutRequestID']);
    exit;
}

$stmt = $conn->prepare("
    SELECT id, tenant_id, gateway_id, status
    FROM payment_gateway_transactions
    WHERE gateway_id = ?
      AND checkout_request_id = ?
    LIMIT 1
");

if (!$stmt) {
    http_response_code(500);
    echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Unable to process callback']);
    exit;
}

$stmt->bind_param('is', $gatewayId, $checkoutRequestId);
$stmt->execute();
$transaction = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$transaction) {
    http_response_code(404);
    echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Transaction not found']);
    exit;
}

$tenantId = (int)$transaction['tenant_id'];
$eventKey = 'mpesa:stk:' . $gatewayId . ':' . $checkoutRequestId;
$callbackJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$eventStmt = $conn->prepare("
    INSERT INTO payment_gateway_events
        (tenant_id, gateway_id, gateway_transaction_id, event_type, event_key, payload, processed)
    VALUES (?, ?, ?, 'stk_callback', ?, ?, 0)
    ON DUPLICATE KEY UPDATE id = id
");

if ($eventStmt) {
    $txId = (int)$transaction['id'];
    $eventStmt->bind_param('iiiss', $tenantId, $gatewayId, $txId, $eventKey, $callbackJson);
    $eventStmt->execute();
    $eventStmt->close();
}

$resultCode = isset($stk['ResultCode']) ? (string)$stk['ResultCode'] : null;
$resultDescription = isset($stk['ResultDesc']) ? (string)$stk['ResultDesc'] : null;

$newStatus = $resultCode === '0'
    ? 'callback_received'
    : 'failed';

$update = $conn->prepare("
    UPDATE payment_gateway_transactions
    SET status = ?,
        result_code = ?,
        result_description = ?,
        callback_payload = ?,
        updated_at = CURRENT_TIMESTAMP
    WHERE id = ?
      AND tenant_id = ?
");

if ($update) {
    $txId = (int)$transaction['id'];
    $update->bind_param(
        'ssssii',
        $newStatus,
        $resultCode,
        $resultDescription,
        $callbackJson,
        $txId,
        $tenantId
    );
    $update->execute();
    $update->close();
}

http_response_code(200);
echo json_encode([
    'ResultCode' => 0,
    'ResultDesc' => 'Callback received'
]);
