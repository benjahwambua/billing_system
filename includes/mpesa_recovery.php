<?php
require_once __DIR__ . '/billing_workflow.php';
require_once __DIR__ . '/mpesa_callback_policy.php';

/**
 * Recover only a confirmed customer-billing M-Pesa transaction.
 * Platform SaaS and hotspot flows are deliberately excluded because their
 * finalization has separate lifecycle side effects.
 */
function flexihubRecoverConfirmedCustomerMpesa($tenantId, $gatewayTransactionId, $targetInvoiceId = 0, $allocationReason = '')
{
    global $conn;
    $tenantId=(int)$tenantId;
    $gatewayTransactionId=(int)$gatewayTransactionId;
    $targetInvoiceId=(int)$targetInvoiceId;
    $allocationReason=trim((string)$allocationReason);
    if($tenantId<=0 || $gatewayTransactionId<=0) throw new InvalidArgumentException('A valid tenant and gateway transaction are required.');
    if($targetInvoiceId>0 && (strlen($allocationReason)<10 || strlen($allocationReason)>500)) throw new InvalidArgumentException('A clear allocation reason of 10 to 500 characters is required.');

    $conn->begin_transaction();
    try {
        $q=$conn->prepare("SELECT * FROM payment_gateway_transactions WHERE id=? AND tenant_id=? AND status='confirmed' AND flow IN ('customer','internet','billing') LIMIT 1 FOR UPDATE");
        if(!$q) throw new RuntimeException('Unable to lock the confirmed customer transaction.');
        $q->bind_param('ii',$gatewayTransactionId,$tenantId);
        if(!$q->execute()) { $q->close(); throw new RuntimeException('Unable to load the confirmed customer transaction.'); }
        $tx=$q->get_result()->fetch_assoc(); $q->close();
        if(!$tx) throw new RuntimeException('Only confirmed customer-billing M-Pesa transactions can be recovered here.');

        $payload=json_decode((string)($tx['callback_payload']??''),true);
        $storedCheckout=trim((string)($tx['checkout_request_id']??''));
        $storedStk=$payload['Body']['stkCallback']??null;
        if(!is_array($storedStk) || ($storedCheckout!=='' && trim((string)($storedStk['CheckoutRequestID']??''))!==$storedCheckout)) {
            // The callback payload is persisted before the financial transaction.
            // A rolled-back finalization can therefore leave it on payment_callbacks
            // even when the gateway row's in-transaction payload update rolled back.
            $events=$conn->prepare("SELECT callback_payload FROM payment_callbacks WHERE tenant_id=? AND transaction_reference=? AND provider='mpesa' AND event_type='stk_callback' ORDER BY id DESC LIMIT 20");
            if(!$events) throw new RuntimeException('The stored M-Pesa callback could not be located.');
            $accountReference=(string)($tx['account_reference']??'');
            $events->bind_param('is',$tenantId,$accountReference);
            if(!$events->execute()) { $events->close(); throw new RuntimeException('The stored M-Pesa callback could not be loaded.'); }
            $eventRows=$events->get_result();
            $matchedPayload=null;
            while($eventRow=$eventRows->fetch_assoc()) {
                $candidate=json_decode((string)($eventRow['callback_payload']??''),true);
                $candidateStk=$candidate['Body']['stkCallback']??null;
                if(is_array($candidateStk) && ($storedCheckout==='' || trim((string)($candidateStk['CheckoutRequestID']??''))===$storedCheckout)) { $matchedPayload=$candidate; break; }
            }
            $events->close();
            if($matchedPayload===null) throw new RuntimeException('No stored successful callback matches this gateway transaction.');
            $payload=$matchedPayload;
        }
        $stk=$payload['Body']['stkCallback']??null;
        if(!is_array($stk) || (string)($stk['ResultCode']??'')!=='0') throw new RuntimeException('The stored callback is not a successful M-Pesa STK response.');
        $meta=[];
        foreach(($stk['CallbackMetadata']['Item']??[]) as $item) if(is_array($item)&&isset($item['Name'])) $meta[(string)$item['Name']]=$item['Value']??null;
        $receipt=trim((string)($meta['MpesaReceiptNumber']??''));
        $paidAmount=isset($meta['Amount'])?(float)$meta['Amount']:null;
        $paidPhone=(string)($meta['PhoneNumber']??'');
        if(!flexihubMpesaCallbackMatchesTransaction($receipt,$paidAmount,$tx['amount'],$paidPhone,$tx['phone_number']??'')) throw new RuntimeException('Stored M-Pesa receipt, amount or phone does not match the initiated transaction.');

        $claims=$conn->prepare("SELECT tenant_id,source_type,source_transaction_id FROM mpesa_receipt_claims WHERE receipt=? LIMIT 1 FOR UPDATE");
        if(!$claims) throw new RuntimeException('Receipt claim storage could not be checked.');
        $claims->bind_param('s',$receipt);
        if(!$claims->execute()) { $claims->close(); throw new RuntimeException('Receipt uniqueness could not be verified.'); }
        $claim=$claims->get_result()->fetch_assoc(); $claims->close();
        if($claim) {
            if((int)$claim['tenant_id']!==$tenantId || (string)$claim['source_type']!=='gateway_transaction' || (int)$claim['source_transaction_id']!==$gatewayTransactionId) throw new RuntimeException('This M-Pesa receipt is already claimed by another transaction.');
        } else {
            $sourceType='gateway_transaction';
            $insertClaim=$conn->prepare("INSERT INTO mpesa_receipt_claims (receipt,tenant_id,source_type,source_transaction_id) VALUES (?,?,?,?)");
            if(!$insertClaim) throw new RuntimeException('Unable to reserve the M-Pesa receipt.');
            $insertClaim->bind_param('sisi',$receipt,$tenantId,$sourceType,$gatewayTransactionId);
            if(!$insertClaim->execute()) { $insertClaim->close(); throw new RuntimeException('The M-Pesa receipt could not be reserved; it may already be in use.'); }
            $insertClaim->close();
        }

        $reference=trim((string)($tx['account_reference']??''));
        if($reference==='' && $targetInvoiceId<=0) throw new RuntimeException('The transaction has no invoice reference. Use controlled allocation only after verifying the payer/customer match.');
        $matchedReferenceInvoice=$reference!==''?dbFetchOne("SELECT id FROM invoices WHERE tenant_id=? AND invoice_number=? LIMIT 1",'is',$tenantId,$reference):null;
        if(!$matchedReferenceInvoice && $reference!=='' && ctype_digit($reference)) $matchedReferenceInvoice=dbFetchOne("SELECT id FROM invoices WHERE tenant_id=? AND id=? LIMIT 1",'ii',$tenantId,(int)$reference);
        if($targetInvoiceId>0) {
            if($matchedReferenceInvoice) throw new RuntimeException('The original invoice reference resolves correctly. Use normal recovery instead of manual allocation.');
            $invoice=dbFetchOne("SELECT * FROM invoices WHERE id=? AND tenant_id=? LIMIT 1 FOR UPDATE",'ii',$targetInvoiceId,$tenantId);
            if(!$invoice) throw new RuntimeException('The selected invoice does not belong to this tenant.');
            $invoiceStatus=strtolower(trim((string)($invoice['status']??'')));
            if(in_array($invoiceStatus,['paid','cancelled','canceled','void','draft'],true)) throw new RuntimeException('The selected invoice is not open for payment allocation.');
            $customerPhone=dbFetchOne("SELECT phone FROM customers WHERE id=? AND tenant_id=? LIMIT 1 FOR UPDATE",'ii',(int)($invoice['customer_id']??0),$tenantId);
            if(!$customerPhone || trim((string)($customerPhone['phone']??''))==='' || !hash_equals(trim((string)($tx['phone_number']??'')),trim((string)$customerPhone['phone']))) throw new RuntimeException('Manual allocation requires the invoice customer phone to match the verified M-Pesa payer phone.');
        } else {
            $invoice=$matchedReferenceInvoice?dbFetchOne("SELECT * FROM invoices WHERE id=? AND tenant_id=? LIMIT 1 FOR UPDATE",'ii',(int)$matchedReferenceInvoice['id'],$tenantId):null;
            if(!$invoice) throw new RuntimeException('The referenced customer invoice was not found in this tenant. Use the controlled allocation screen only after verifying the payer/customer match.');
        }

        $customerId=(int)($invoice['customer_id']??0);
        $accountId=(int)($invoice['internet_account_id']??$invoice['account_id']??0);
        if($accountId>0) {
            $check=$conn->prepare("SELECT id FROM internet_accounts WHERE id=? AND tenant_id=? AND customer_id=? LIMIT 1");
            if(!$check) throw new RuntimeException('Unable to validate the invoice service account.');
            $check->bind_param('iii',$accountId,$tenantId,$customerId);
            if(!$check->execute()) { $check->close(); throw new RuntimeException('Unable to validate the invoice service account.'); }
            $valid=(bool)$check->get_result()->fetch_assoc(); $check->close();
            if(!$valid) $accountId=0;
        }
        if($accountId<=0) {
            $lookup=$conn->prepare("SELECT id FROM internet_accounts WHERE tenant_id=? AND customer_id=? ORDER BY CASE WHEN status IN ('active','pending_activation','suspended') THEN 0 ELSE 1 END,id DESC LIMIT 1");
            if(!$lookup) throw new RuntimeException('Unable to resolve the customer service account.');
            $lookup->bind_param('ii',$tenantId,$customerId);
            if(!$lookup->execute()) { $lookup->close(); throw new RuntimeException('Unable to resolve the customer service account.'); }
            $accountId=(int)($lookup->get_result()->fetch_assoc()['id']??0); $lookup->close();
        }
        if($customerId<=0 || $accountId<=0) throw new RuntimeException('The invoice customer or service account is missing; recovery stopped.');

        $paymentColumns=flexihubTableColumns('payments');
        $referenceColumn=in_array('reference',$paymentColumns,true)?'reference':(in_array('reference_number',$paymentColumns,true)?'reference_number':null);
        if(!$referenceColumn) throw new RuntimeException('Payment storage has no supported receipt-reference column.');
        $duplicate=dbFetchOne("SELECT * FROM payments WHERE tenant_id=? AND `{$referenceColumn}`=? LIMIT 1 FOR UPDATE",'is',$tenantId,$receipt);
        $paymentAmount=(float)$tx['amount'];
        if($duplicate) {
            if((int)($duplicate['invoice_id']??0)!==(int)$invoice['id'] || abs((float)($duplicate['amount']??0)-$paymentAmount)>0.01) throw new RuntimeException('This receipt is already attached to a different invoice or amount.');
            $paymentId=(int)$duplicate['id'];
            $payment=$duplicate;
        } else {
            $total=flexihubInvoiceTotal($invoice);
            $paid=flexihubInvoicePaid((int)$invoice['id'],$tenantId);
            $balance=max(0,$total-$paid);
            if($balance<=0.0001) throw new RuntimeException('The invoice is already fully paid; no new payment was created.');
            if($paymentAmount>$balance+0.01) throw new RuntimeException('The confirmed payment exceeds the remaining invoice balance. It requires manual finance review.');
            $data=[
                'tenant_id'=>$tenantId,'invoice_id'=>(int)$invoice['id'],'customer_id'=>$customerId,
                'internet_account_id'=>$accountId,'amount'=>$paymentAmount,'payment_method'=>'M-Pesa',
                'payment_date'=>date('Y-m-d H:i:s'),'reference'=>$receipt,'reference_number'=>$receipt,
                'payment_number'=>'PAY-'.date('YmdHis').'-'.random_int(100,999)
            ];
            $paymentId=flexihubWorkflowInsert('payments',$data);
            if(!$paymentId) throw new RuntimeException('The customer payment record could not be created.');
            $payment=['id'=>$paymentId]+$data;
        }

        if(!flexihubRefreshInvoiceStatus((int)$invoice['id'],$tenantId)) throw new RuntimeException('The invoice status could not be refreshed.');
        flexihubCreatePaymentArtifacts($paymentId,$payment,$invoice,$tenantId);
        $paidAfter=flexihubInvoicePaid((int)$invoice['id'],$tenantId);
        $fullyPaid=flexihubInvoiceIsFullyPaid(flexihubInvoiceTotal($invoice),$paidAfter);
        if($fullyPaid) {
            if(flexihubAccountHasOverdueBalance($accountId,$tenantId)) throw new RuntimeException('The invoice is settled but the account has another overdue balance; service activation was not queued.');
            $subscriptionId=flexihubFindServiceSubscriptionForPayment($accountId,$tenantId,$paymentId);
            if($subscriptionId===false) throw new RuntimeException('The existing service-subscription record could not be checked safely.');
            if($subscriptionId===null) {
                if(!flexihubRenewInternetAccount($accountId,$tenantId)) throw new RuntimeException('Payment was recorded but the account renewal failed.');
                $subscriptionId=flexihubCreateServiceSubscription($accountId,$tenantId,(int)$invoice['id'],$paymentId);
                if(!$subscriptionId) throw new RuntimeException('Payment was recorded but a deduplicated service subscription could not be created.');
            }
            if(!flexihubQueueServiceActivation($accountId,$tenantId,$paymentId,$subscriptionId,'activate')) throw new RuntimeException('Payment was recorded but service activation could not be queued.');
        }

        $update=$conn->prepare("UPDATE payment_gateway_transactions SET status='completed',provider_receipt=?,provider_transaction_id=?,failure_reason=NULL,confirmed_at=COALESCE(confirmed_at,NOW()),expires_at=NULL WHERE id=? AND tenant_id=? AND status='confirmed'");
        if(!$update) throw new RuntimeException('Unable to mark the gateway transaction as completed.');
        $update->bind_param('ssii',$receipt,$receipt,$gatewayTransactionId,$tenantId);
        if(!$update->execute() || $update->affected_rows!==1) { $update->close(); throw new RuntimeException('Gateway transaction state changed; recovery was rolled back.'); }
        $update->close();

        $callback=$conn->prepare("UPDATE payment_callbacks SET processing_status='processed',processed_at=NOW(),error_message=NULL WHERE tenant_id=? AND external_transaction_id=?");
        if($callback) { $callback->bind_param('is',$tenantId,$receipt); $callback->execute(); $callback->close(); }
        $auditOk=false;
        if(function_exists('logAudit')) {
            try {
                $auditOk=logAudit($targetInvoiceId>0?'MPESA_MANUAL_ALLOCATION':'MPESA_RECOVERY','PAYMENTS',($targetInvoiceId>0?'Manually allocated':'Recovered').' confirmed customer M-Pesa transaction #'.$gatewayTransactionId.' using receipt '.$receipt,'payment_gateway_transaction',$gatewayTransactionId,null,['payment_id'=>$paymentId,'invoice_id'=>(int)$invoice['id'],'amount'=>$paymentAmount,'allocation_reason'=>$targetInvoiceId>0?$allocationReason:null]);
            } catch(Throwable $auditError) {
                if($targetInvoiceId>0) throw new RuntimeException('The allocation audit record could not be written; no allocation was committed.');
            }
        }
        if($targetInvoiceId>0 && !$auditOk) throw new RuntimeException('The allocation audit record could not be written; no allocation was committed.');
        $conn->commit();
        return ['payment_id'=>$paymentId,'invoice_id'=>(int)$invoice['id'],'receipt'=>$receipt,'amount'=>$paymentAmount,'invoice_fully_paid'=>$fullyPaid,'existing_payment'=>(bool)$duplicate];
    } catch(Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}
