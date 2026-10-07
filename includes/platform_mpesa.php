<?php
require_once __DIR__ . '/payment_gateway.php';
require_once __DIR__ . '/platform_billing.php';

if (!function_exists('flexihubCreatePlatformMpesaPayment')) {
    function flexihubCreatePlatformMpesaPayment($tenantId,$invoiceId,$phone,$callbackUrl) {
        global $conn;
        $tenantId=(int)$tenantId;$invoiceId=(int)$invoiceId;
        $invoice=dbFetchOne("SELECT pi.*,s.status subscription_status FROM platform_invoices pi JOIN tenant_platform_subscriptions s ON s.id=pi.subscription_id WHERE pi.id=? AND pi.tenant_id=? LIMIT 1",'ii',$invoiceId,$tenantId);
        if(!$invoice) throw new RuntimeException('SaaS invoice not found.');
        $balance=max(0,(float)$invoice['total_amount']-(float)$invoice['paid_amount']);
        if($balance<=0.0001) throw new RuntimeException('SaaS invoice is already paid.');
        $gateway=dbFetchOne("SELECT * FROM payment_gateways WHERE tenant_id=? AND provider='mpesa' AND status='active' ORDER BY is_default DESC,id DESC LIMIT 1",'i',$tenantId);
        if(!$gateway) throw new RuntimeException('M-Pesa is not configured for this tenant.');
        $phone=flexihubMpesaNormalizePhone($phone);
        $reference=(string)$invoice['invoice_number'];
        $idempotency='platform-'.$invoiceId.'-'.$phone.'-'.date('YmdHi');
        return flexihubCreateMpesaStkTransaction($tenantId,(int)$gateway['id'],$balance,$phone,'platform',$reference,'Flexihub SaaS '.$reference,(string)$callbackUrl,$idempotency,null);
    }
}
