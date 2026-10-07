<?php
require_once __DIR__ . '/payment_gateway.php';
require_once __DIR__ . '/platform_billing.php';

if (!function_exists('flexihubCreatePlatformMpesaPayment')) {
    function flexihubCreatePlatformMpesaPayment($tenantId,$invoiceId,$phone,$callbackUrl) {
        global $conn;
        $tenantId=(int)$tenantId;$invoiceId=(int)$invoiceId;
        $invoice=dbFetchOne("SELECT pi.*,s.status subscription_status FROM platform_invoices pi JOIN tenant_platform_subscriptions s ON s.id=pi.subscription_id WHERE pi.id=? AND pi.tenant_id=? LIMIT 1",'ii',$invoiceId,$tenantId);
        if(!$invoice) throw new RuntimeException('SaaS invoice not found.');
        if(in_array((string)$invoice['status'], ['cancelled','draft'], true)) throw new RuntimeException('This SaaS invoice is not payable in its current status.');
        $balance=max(0,(float)$invoice['total_amount']-(float)$invoice['paid_amount']);
        if($balance<=0.0001) throw new RuntimeException('SaaS invoice is already paid.');

        $gateway=dbFetchOne("SELECT * FROM platform_payment_gateways WHERE provider='mpesa' AND status='active' ORDER BY is_default DESC,id DESC LIMIT 1");
        if(!$gateway) throw new RuntimeException('Flexihub platform M-Pesa gateway is not configured.');
        $phone=flexihubMpesaNormalizePhone($phone);
        $reference=(string)$invoice['invoice_number'];
        $idempotency='platform-'.$invoiceId.'-'.$phone;
        $existing=dbFetchOne("SELECT * FROM platform_payments WHERE tenant_id=? AND invoice_id=? AND idempotency_key=? LIMIT 1",'iis',$tenantId,$invoiceId,$idempotency);
        if($existing && in_array($existing['status'],['pending','completed'],true)) {
            if($existing['status']==='completed') return $existing;
            if(!empty($existing['expires_at']) && strtotime($existing['expires_at'])<=time()) {
                dbExecute("UPDATE platform_payments SET status='failed',failure_reason='M-Pesa payment request expired' WHERE id=? AND tenant_id=? AND status='pending'",'ii',(int)$existing['id'],$tenantId);
            } else {
                return $existing;
            }
        }

        if($existing){
            $paymentId=(int)$existing['id'];
            $stmt=$conn->prepare("SELECT id,status,expires_at FROM platform_payments WHERE id=? AND tenant_id=? FOR UPDATE");
            if(!$stmt) throw new RuntimeException('Unable to lock platform M-Pesa payment.');
            $stmt->bind_param('ii',$paymentId,$tenantId);
            $stmt->execute();
            $locked=$stmt->get_result()->fetch_assoc();
            $stmt->close();
            if(!$locked) throw new RuntimeException('Platform M-Pesa payment no longer exists.');
            if($locked['status']==='completed') return $locked;
            dbExecute("UPDATE platform_payments SET status='pending',amount=?,payment_date=NOW(),failure_reason=NULL,merchant_request_id=NULL,checkout_request_id=NULL,result_code=NULL,result_description=NULL,request_payload=NULL,callback_payload=NULL,confirmed_at=NULL,expires_at=DATE_ADD(NOW(),INTERVAL 5 MINUTE) WHERE id=? AND tenant_id=?",'dii',$balance,$paymentId,$tenantId);
        } else {
            $paymentId=(int)dbExecute("INSERT INTO platform_payments (tenant_id,invoice_id,gateway_id,payment_date,amount,payment_method,provider,status,idempotency_key,expires_at) VALUES (?,?,?,NOW(),?,'M-Pesa','mpesa','pending',?,DATE_ADD(NOW(),INTERVAL 5 MINUTE))",'iiids',$tenantId,$invoiceId,(int)$gateway['id'],$balance,$idempotency);
            if($paymentId<=0) throw new RuntimeException('Unable to create platform M-Pesa payment.');
        }

        $callbackUrl=rtrim((string)$callbackUrl,'/');
        $separator=strpos($callbackUrl,'?')===false?'?':'&';
        $callbackUrl.=$separator.'platform=1&gateway='.(int)$gateway['id'].'&token='.rawurlencode((string)$gateway['callback_token']);
        try {
            $response=flexihubMpesaStkPush($gateway,$balance,$phone,$reference,'Flexihub SaaS '.$reference,$callbackUrl);
        } catch(Throwable $e) {
            $reason=substr($e->getMessage(),0,500);
            dbExecute("UPDATE platform_payments SET status='failed',failure_reason=? WHERE id=? AND tenant_id=?",'sii',$reason,$paymentId,$tenantId);
            throw $e;
        }
        $merchant=(string)($response['MerchantRequestID']??'');
        $checkout=(string)($response['CheckoutRequestID']??'');
        $code=isset($response['ResponseCode'])?(string)$response['ResponseCode']:null;
        $desc=isset($response['ResponseDescription'])?(string)$response['ResponseDescription']:null;
        $status=($checkout!==''&&$code==='0')?'pending':'failed';
        $json=json_encode($response,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        dbExecute("UPDATE platform_payments SET phone_number=?,merchant_request_id=?,checkout_request_id=?,result_code=?,result_description=?,request_payload=?,status=?,expires_at=DATE_ADD(NOW(),INTERVAL 5 MINUTE) WHERE id=? AND tenant_id=?",'sssssssii',$phone,$merchant,$checkout,$code,$desc,$json,$status,$paymentId,$tenantId);
        $fresh=dbFetchOne("SELECT * FROM platform_payments WHERE id=? AND tenant_id=? LIMIT 1",'ii',$paymentId,$tenantId);
        return $fresh?:[];
    }
}
