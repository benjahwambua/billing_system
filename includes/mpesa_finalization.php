<?php
/**
 * Finalizes a confirmed M-Pesa gateway transaction into the existing billing ledger.
 * This is deliberately separate from the callback/reconciliation receiver.
 */
require_once __DIR__ . '/billing_workflow.php';

if (!function_exists('flexihubFinalizeMpesaTransaction')) {
    function flexihubFinalizeMpesaTransaction($gatewayTransactionId)
    {
        global $conn;

        $gatewayTransactionId = (int)$gatewayTransactionId;
        if ($gatewayTransactionId <= 0) throw new InvalidArgumentException('Invalid gateway transaction.');

        $conn->begin_transaction();

        try {
            $stmt = $conn->prepare("SELECT * FROM payment_gateway_transactions WHERE id=? FOR UPDATE");
            if (!$stmt) throw new RuntimeException('Unable to lock gateway transaction.');
            $stmt->bind_param('i', $gatewayTransactionId);
            $stmt->execute();
            $tx = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$tx) throw new RuntimeException('Gateway transaction not found.');

            $tenantId = (int)$tx['tenant_id'];

            if (($tx['status'] ?? '') === 'completed') {
                $conn->commit();
                return $tx;
            }

            if (($tx['status'] ?? '') !== 'confirmed') {
                throw new RuntimeException('Only a confirmed M-Pesa transaction can be finalized.');
            }

            $flow = strtolower((string)($tx['flow'] ?? ''));

            if ($flow === 'invoice' || $flow === 'customer_payment' || $flow === 'payment') {
                $invoiceId = (int)($tx['invoice_id'] ?? 0);
                if ($invoiceId <= 0) throw new RuntimeException('Confirmed M-Pesa transaction has no invoice.');

                $stmt = $conn->prepare("SELECT * FROM invoices WHERE id=? AND tenant_id=? FOR UPDATE");
                if (!$stmt) throw new RuntimeException('Unable to lock invoice.');
                $stmt->bind_param('ii', $invoiceId, $tenantId);
                $stmt->execute();
                $invoice = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if (!$invoice) throw new RuntimeException('Invoice does not belong to this tenant.');

                $amount = (float)$tx['amount'];
                $total = flexihubInvoiceTotal($invoice);
                $paid = flexihubInvoicePaid($invoiceId, $tenantId);
                $balance = max(0, $total - $paid);

                if ($balance <= 0) throw new RuntimeException('Invoice is already fully paid.');
                if ($amount > $balance + 0.00001) throw new RuntimeException('M-Pesa amount exceeds the invoice balance.');

                $paymentCols = flexihubTableColumns('payments');
                $data = [
                    'tenant_id' => $tenantId,
                    'invoice_id' => $invoiceId,
                    'amount' => $amount,
                    'payment_method' => 'mpesa',
                    'payment_date' => systemDate(),
                    'reference' => $tx['provider_receipt'] ?: ($tx['provider_transaction_id'] ?: $tx['checkout_request_id'])
                ];
                if (in_array('payment_number', $paymentCols, true)) $data['payment_number'] = generatePaymentNumber();
                if (in_array('account_id', $paymentCols, true)) {
                    $accountId = 0;
                    $stmt = $conn->prepare("SELECT id FROM internet_accounts WHERE tenant_id=? AND customer_id=? ORDER BY id DESC LIMIT 1");
                    if ($stmt) {
                        $customerId = (int)($invoice['customer_id'] ?? 0);
                        $stmt->bind_param('ii', $tenantId, $customerId);
                        $stmt->execute();
                        $row = $stmt->get_result()->fetch_assoc();
                        $stmt->close();
                        $accountId = (int)($row['id'] ?? 0);
                    }
                    if ($accountId) $data['account_id'] = $accountId;
                }

                $paymentId = flexihubWorkflowInsert('payments', $data);
                if (!$paymentId) throw new RuntimeException('Unable to create customer payment.');

                $payment = ['id'=>$paymentId] + $data;
                if (!flexihubCreatePaymentArtifacts($paymentId, $payment, $invoice, $tenantId)) {
                    throw new RuntimeException('Unable to create payment receipt/ledger artifacts.');
                }

                if (!flexihubRefreshInvoiceStatus($invoiceId, $tenantId)) {
                    throw new RuntimeException('Unable to refresh invoice status.');
                }

                $accountId = (int)($data['account_id'] ?? 0);
                if ($accountId) {
                    $newPaid = flexihubInvoicePaid($invoiceId, $tenantId);
                    if ($newPaid + 0.00001 >= $total) {
                        if (!flexihubRenewInternetAccount($accountId, $tenantId)) {
                            throw new RuntimeException('Payment recorded, but service renewal could not be completed.');
                        }
                        flexihubCreateServiceSubscription($accountId, $tenantId, $invoiceId, $paymentId);
                    }
                }

                $stmt = $conn->prepare("UPDATE payment_gateway_transactions SET payment_id=?, status='completed' WHERE id=? AND tenant_id=?");
                if (!$stmt) throw new RuntimeException('Unable to complete gateway transaction.');
                $stmt->bind_param('iii', $paymentId, $gatewayTransactionId, $tenantId);
                $stmt->execute();
                $stmt->close();
            } else {
                throw new RuntimeException('Gateway flow is not yet supported for automatic finalization: ' . $flow);
            }

            $conn->commit();

            $stmt = $conn->prepare("SELECT * FROM payment_gateway_transactions WHERE id=? AND tenant_id=? LIMIT 1");
            $stmt->bind_param('ii', $gatewayTransactionId, $tenantId);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $result ?: $tx;
        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        }
    }
}
