<?php
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/payment_gateway.php';
require_once __DIR__.'/../includes/hotspot_workflow.php';
require_once __DIR__.'/../includes/platform_billing.php';

header('Content-Type: application/json; charset=utf-8');
$gatewayId=(int)($_GET['gateway']??0);$token=trim((string)($_GET['token']??''));$raw=file_get_contents('php://input');$payload=json_decode($raw,true);
if($gatewayId<=0||$token===''||!is_array($payload)){http_response_code(400);echo json_encode(['ResultCode'=>1,'ResultDesc'=>'Invalid callback request']);exit;}

/* Platform SaaS callbacks are handled against the Flexihub-owned gateway and ledger. */
if((int)($_GET['platform']??0)===1){
 $pg=dbFetchOne("SELECT * FROM platform_payment_gateways WHERE id=? AND callback_token=? AND provider='mpesa' AND status='active' LIMIT 1",'is',$gatewayId,$token);
 if(!$pg){http_response_code(404);echo json_encode(['ResultCode'=>1,'ResultDesc'=>'Invalid platform callback endpoint']);exit;}
 $stk=$payload['Body']['stkCallback']??null;$checkout=is_array($stk)?trim((string)($stk['CheckoutRequestID']??'')):'';$rc=is_array($stk)?(string)($stk['ResultCode']??''):'';$rd=is_array($stk)?(string)($stk['ResultDesc']??''):'';
 if($checkout===''){http_response_code(400);echo json_encode(['ResultCode'=>1,'ResultDesc'=>'Missing CheckoutRequestID']);exit;}
 $payment=dbFetchOne("SELECT * FROM platform_payments WHERE gateway_id=? AND checkout_request_id=? LIMIT 1",'is',$gatewayId,$checkout);
 if(!$payment){http_response_code(404);echo json_encode(['ResultCode'=>1,'ResultDesc'=>'Platform payment transaction not found']);exit;}
 $items=$stk['CallbackMetadata']['Item']??[];$meta=[];if(is_array($items))foreach($items as $item){if(isset($item['Name']))$meta[(string)$item['Name']]=$item['Value']??null;}
 $receipt=(string)($meta['MpesaReceiptNumber']??'');$amount=isset($meta['Amount'])?(float)$meta['Amount']:null;$phone=(string)($meta['PhoneNumber']??'');$json=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$tenantId=(int)$payment['tenant_id'];$paymentId=(int)$payment['id'];
 if($payment['status']==='completed'){echo json_encode(['ResultCode'=>0,'ResultDesc'=>'Callback already processed']);exit;}
 if($rc!=='0'){$stmt=$conn->prepare("UPDATE platform_payments SET status='failed',result_code=?,result_description=?,callback_payload=?,failure_reason=? WHERE id=? AND tenant_id=?");if($stmt){$stmt->bind_param('ssssii',$rc,$rd,$json,$rd,$paymentId,$tenantId);$stmt->execute();$stmt->close();}echo json_encode(['ResultCode'=>0,'ResultDesc'=>'Callback received']);exit;}
 if($amount===null||abs($amount-(float)$payment['amount'])>0.01||($phone!==''&&$phone!==(string)$payment['phone_number'])){$err='Callback amount or phone does not match the platform payment.';$stmt=$conn->prepare("UPDATE platform_payments SET status='failed',result_code='VALIDATION_FAILED',result_description=?,callback_payload=?,failure_reason=? WHERE id=? AND tenant_id=?");if($stmt){$stmt->bind_param('sssii',$err,$json,$err,$paymentId,$tenantId);$stmt->execute();$stmt->close();}echo json_encode(['ResultCode'=>0,'ResultDesc'=>'Callback received']);exit;}
 $conn->begin_transaction();try{
  $p=dbFetchOne("SELECT * FROM platform_payments WHERE id=? AND tenant_id=? FOR UPDATE",'ii',$paymentId,$tenantId);if(!$p)throw new RuntimeException('Platform payment not found.');if($p['status']==='completed'){$conn->commit();echo json_encode(['ResultCode'=>0,'ResultDesc'=>'Callback already processed']);exit;}
  if($receipt!==''){ $dup=dbFetchOne("SELECT id FROM platform_payments WHERE provider='mpesa' AND external_transaction_id=? AND id<>? LIMIT 1",'si',$receipt,$paymentId);if($dup)throw new RuntimeException('M-Pesa receipt already used.'); }
  $invoice=dbFetchOne("SELECT * FROM platform_invoices WHERE id=? AND tenant_id=? FOR UPDATE",'ii',(int)$p['invoice_id'],$tenantId);if(!$invoice)throw new RuntimeException('SaaS invoice not found.');
  $balance=max(0,(float)$invoice['total_amount']-(float)$invoice['paid_amount']);if($balance<=0.0001)throw new RuntimeException('SaaS invoice is already paid.');if((float)$p['amount']>$balance+0.01)throw new RuntimeException('Payment exceeds SaaS invoice balance.');
  $newPaid=(float)$invoice['paid_amount']+(float)$p['amount'];$newStatus=$newPaid+0.0001>=(float)$invoice['total_amount']?'paid':'partial';
  $stmt=$conn->prepare("UPDATE platform_payments SET status='completed',external_transaction_id=?,reference=?,result_code=?,result_description=?,callback_payload=?,confirmed_at=NOW(),expires_at=NULL WHERE id=? AND tenant_id=?");if(!$stmt)throw new RuntimeException('Unable to finalize platform payment.');$stmt->bind_param('ssssssi',$receipt,$receipt,$rc,$rd,$json,$paymentId,$tenantId);$stmt->execute();$stmt->close();
  $stmt=$conn->prepare("UPDATE platform_invoices SET paid_amount=?,status=?,updated_at=NOW() WHERE id=? AND tenant_id=?");$stmt->bind_param('dsii',$newPaid,$newStatus,(int)$invoice['id'],$tenantId);$stmt->execute();$stmt->close();
  if($newStatus==='paid'){$sub=dbFetchOne("SELECT s.*,p.billing_cycle FROM tenant_platform_subscriptions s JOIN platform_plans p ON p.id=s.plan_id WHERE s.id=? FOR UPDATE",'i',(int)$invoice['subscription_id']);if($sub){$nextStart=$invoice['period_end'];$nextEnd=flexihubPlatformAddCycle($nextStart,$sub['billing_cycle']);$stmt=$conn->prepare("UPDATE tenant_platform_subscriptions SET status='active',current_period_start=?,current_period_end=?,grace_ends_at=NULL,updated_at=NOW() WHERE id=?");if($stmt){$stmt->bind_param('ssi',$nextStart,$nextEnd,$sub['id']);$stmt->execute();$stmt->close();}$conn->query("UPDATE tenants SET status='active' WHERE id=".$tenantId." AND status IN ('past_due','suspended','trial')");}}
  $conn->commit();echo json_encode(['ResultCode'=>0,'ResultDesc'=>'SaaS payment received and allocated']);exit;
 }catch(Throwable $e){$conn->rollback();$err=substr($e->getMessage(),0,500);$stmt=$conn->prepare("UPDATE platform_payments SET status='failed',failure_reason=? WHERE id=? AND tenant_id=? AND status<>'completed'");if($stmt){$stmt->bind_param('sii',$err,$paymentId,$tenantId);$stmt->execute();$stmt->close();}echo json_encode(['ResultCode'=>0,'ResultDesc'=>'Callback received']);exit;}
}

$q=$conn->prepare("SELECT * FROM payment_gateways WHERE id=? AND callback_token=? AND provider='mpesa' LIMIT 1");if(!$q){http_response_code(500);echo json_encode(['ResultCode'=>1,'ResultDesc'=>'Gateway lookup failed']);exit;}$q->bind_param('is',$gatewayId,$token);$q->execute();$gateway=$q->get_result()->fetch_assoc();$q->close();
if(!$gateway){http_response_code(404);echo json_encode(['ResultCode'=>1,'ResultDesc'=>'Invalid callback endpoint']);exit;}
$stk=$payload['Body']['stkCallback']??null;$checkout=is_array($stk)?trim((string)($stk['CheckoutRequestID']??'')):'';$resultCode=is_array($stk)?(string)($stk['ResultCode']??''):'';$resultDesc=is_array($stk)?(string)($stk['ResultDesc']??''):'';if($checkout===''){http_response_code(400);echo json_encode(['ResultCode'=>1,'ResultDesc'=>'Missing CheckoutRequestID']);exit;}
$q=$conn->prepare("SELECT * FROM payment_gateway_transactions WHERE tenant_id=? AND gateway_id=? AND checkout_request_id=? LIMIT 1");$q->bind_param('iis',$gateway['tenant_id'],$gatewayId,$checkout);$q->execute();$tx=$q->get_result()->fetch_assoc();$q->close();
if(!$tx){http_response_code(404);echo json_encode(['ResultCode'=>1,'ResultDesc'=>'Transaction not found']);exit;}
$txStatus=(string)($tx['status']??'');
// A completed transaction must never be downgraded by a replayed or tampered callback.
if($txStatus==='completed'){
 echo json_encode(['ResultCode'=>0,'ResultDesc'=>'Callback already processed']);exit;
}

$tenantId=(int)$tx['tenant_id'];$txId=(int)$tx['id'];$meta=[];$items=$stk['CallbackMetadata']['Item']??[];if(is_array($items))foreach($items as $item){if(isset($item['Name']))$meta[(string)$item['Name']]=$item['Value']??null;}
$receipt=(string)($meta['MpesaReceiptNumber']??'');$paidAmount=isset($meta['Amount'])?(float)$meta['Amount']:null;$paidPhone=(string)($meta['PhoneNumber']??'');
$callbackJson=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$external=$receipt!==''?$receipt:$checkout;
$stmt=$conn->prepare("INSERT INTO payment_callbacks (tenant_id,provider,event_type,transaction_reference,external_transaction_id,phone_number,amount,callback_payload,processing_status) VALUES (?,'mpesa','stk_callback',?,?,?,?,?,'received') ON DUPLICATE KEY UPDATE callback_payload=VALUES(callback_payload),processing_status='received',error_message=NULL");if($stmt){$ref=$tx['account_reference'];$stmt->bind_param('isssds',$tenantId,$ref,$external,$paidPhone,$paidAmount,$callbackJson);$stmt->execute();$stmt->close();}
$eventKey='mpesa:'.$gatewayId.':'.$checkout;$stmt=$conn->prepare("INSERT INTO payment_gateway_events (tenant_id,gateway_id,gateway_transaction_id,event_type,event_key,payload,processed) VALUES (?,?,?,'stk_callback',?,?,0) ON DUPLICATE KEY UPDATE id=id");if($stmt){$stmt->bind_param('iiiss',$tenantId,$gatewayId,$txId,$eventKey,$callbackJson);$stmt->execute();$stmt->close();}
if($resultCode!=='0'){
 $stmt=$conn->prepare("UPDATE payment_gateway_transactions SET status='failed',result_code=?,result_description=?,callback_payload=? WHERE id=? AND tenant_id=?");if($stmt){$stmt->bind_param('sssii',$resultCode,$resultDesc,$callbackJson,$txId,$tenantId);$stmt->execute();$stmt->close();}
 $stmt=$conn->prepare("UPDATE payment_callbacks SET processing_status='processed',processed_at=NOW(),error_message=? WHERE tenant_id=? AND external_transaction_id=? LIMIT 1");if($stmt){$msg=$resultDesc!==''?$resultDesc:'M-Pesa payment failed.';$stmt->bind_param('sis',$msg,$tenantId,$external);$stmt->execute();$stmt->close();}
 echo json_encode(['ResultCode'=>0,'ResultDesc'=>'Callback received']);exit;
}
if($receipt!==''){
 $dup=$conn->prepare("SELECT id FROM payment_gateway_transactions WHERE tenant_id=? AND provider='mpesa' AND provider_receipt=? AND id<>? LIMIT 1");
 if($dup){$dup->bind_param('isi',$tenantId,$receipt,$txId);$dup->execute();$duplicateReceipt=$dup->get_result()->fetch_assoc();$dup->close();
  if($duplicateReceipt){
   $err='M-Pesa receipt has already been associated with another transaction.';
   $stmt=$conn->prepare("UPDATE payment_gateway_transactions SET status='failed',result_code='DUPLICATE_RECEIPT',result_description=?,callback_payload=?,failure_reason=? WHERE id=? AND tenant_id=? AND status<>'completed'");
   if($stmt){$stmt->bind_param('sssii',$err,$callbackJson,$err,$txId,$tenantId);$stmt->execute();$stmt->close();}
   echo json_encode(['ResultCode'=>0,'ResultDesc'=>'Callback received']);exit;
  }
 }
}
if($paidAmount===null||abs($paidAmount-(float)$tx['amount'])>0.01||($paidPhone!==''&&$paidPhone!==(string)$tx['phone_number'])){
 $err='Callback amount or phone does not match the initiated transaction.';
 $stmt=$conn->prepare("UPDATE payment_gateway_transactions SET status='failed',result_code=?,result_description=?,callback_payload=?,failure_reason=? WHERE id=? AND tenant_id=? AND status<>'completed'");if($stmt){$code='VALIDATION_FAILED';$stmt->bind_param('ssssii',$code,$resultDesc,$callbackJson,$err,$txId,$tenantId);$stmt->execute();$stmt->close();}
 $stmt=$conn->prepare("UPDATE payment_callbacks SET processing_status='failed',error_message=? WHERE tenant_id=? AND external_transaction_id=? LIMIT 1");if($stmt){$stmt->bind_param('sis',$err,$tenantId,$external);$stmt->execute();$stmt->close();}
 echo json_encode(['ResultCode'=>0,'ResultDesc'=>'Callback received']);exit;
}
$conn->begin_transaction();
try{
 $stmt=$conn->prepare("SELECT * FROM payment_gateway_transactions WHERE id=? AND tenant_id=? FOR UPDATE");$stmt->bind_param('ii',$txId,$tenantId);$stmt->execute();$tx=$stmt->get_result()->fetch_assoc();$stmt->close();
 if(($tx['status']??'')==='completed'){$conn->commit();$stmt=$conn->prepare("UPDATE payment_callbacks SET processing_status='processed',processed_at=NOW() WHERE tenant_id=? AND external_transaction_id=? LIMIT 1");if($stmt){$stmt->bind_param('is',$tenantId,$external);$stmt->execute();$stmt->close();}echo json_encode(['ResultCode'=>0,'ResultDesc'=>'Callback already processed']);exit;}
 $stmt=$conn->prepare("UPDATE payment_gateway_transactions SET status='confirmed',provider_receipt=?,provider_transaction_id=?,result_code=?,result_description=?,callback_payload=?,confirmed_at=NOW() WHERE id=? AND tenant_id=?");$stmt->bind_param('sssssii',$receipt,$receipt,$resultCode,$resultDesc,$callbackJson,$txId,$tenantId);$stmt->execute();$stmt->close();
 if (($tx['flow']??'')==='platform') {
  $platformInvoiceId=(int)($tx['platform_invoice_id']??0);$invoice=$platformInvoiceId>0?dbFetchOne("SELECT * FROM platform_invoices WHERE id=? AND tenant_id=? FOR UPDATE",'ii',$platformInvoiceId,$tenantId):dbFetchOne("SELECT * FROM platform_invoices WHERE tenant_id=? AND invoice_number=? FOR UPDATE",'is',$tenantId,$tx['account_reference']);
  if(!$invoice) throw new RuntimeException('Flexihub SaaS invoice is not linked to this payment.');
  $balance=max(0,(float)$invoice['total_amount']-(float)$invoice['paid_amount']);
  if($balance<=0.0001) throw new RuntimeException('Flexihub SaaS invoice is already paid.');
  if((float)$tx['amount']>$balance+0.01) throw new RuntimeException('SaaS payment exceeds the invoice balance.');
  $existingPlatformPayment=dbFetchOne("SELECT * FROM platform_payments WHERE tenant_id=? AND invoice_id=? AND provider='mpesa' AND external_transaction_id=? LIMIT 1",'iis',$tenantId,(int)$invoice['id'],$receipt);
  if($existingPlatformPayment){
   $stmt=$conn->prepare("UPDATE payment_gateway_transactions SET status='completed',provider_receipt=?,provider_transaction_id=?,result_code=?,result_description=?,callback_payload=?,confirmed_at=NOW() WHERE id=? AND tenant_id=?");
   if($stmt){$stmt->bind_param('sssssii',$receipt,$receipt,$resultCode,$resultDesc,$callbackJson,$txId,$tenantId);$stmt->execute();$stmt->close();}
   $conn->commit();
   $stmt=$conn->prepare("UPDATE payment_callbacks SET processing_status='processed',processed_at=NOW() WHERE tenant_id=? AND external_transaction_id=? LIMIT 1");
   if($stmt){$stmt->bind_param('is',$tenantId,$external);$stmt->execute();$stmt->close();}
   echo json_encode(['ResultCode'=>0,'ResultDesc'=>'SaaS payment already processed']);exit;
  }
  $stmt=$conn->prepare("INSERT INTO platform_payments (tenant_id,invoice_id,amount,payment_method,reference,provider,external_transaction_id,status) VALUES (?,? ,?,'mpesa',?,'mpesa',?,'completed')");
  if(!$stmt) throw new RuntimeException('Unable to record SaaS payment.');
  $invoiceId=(int)$invoice['id'];$amount=(float)$tx['amount'];$stmt->bind_param('iidss',$tenantId,$invoiceId,$amount,$receipt,$receipt);
  if(!$stmt->execute()) { $stmt->close(); throw new RuntimeException('Unable to record SaaS payment.'); } $stmt->close();
  $newPaid=(float)$invoice['paid_amount']+(float)$tx['amount'];$newStatus=$newPaid+0.0001>=(float)$invoice['total_amount']?'paid':'partial';
  $stmt=$conn->prepare("UPDATE platform_invoices SET paid_amount=?,status=?,updated_at=NOW() WHERE id=? AND tenant_id=?");
  if(!$stmt) throw new RuntimeException('Unable to update SaaS invoice.');
  $stmt->bind_param('dsii',$newPaid,$newStatus,$invoiceId,$tenantId);$stmt->execute();$stmt->close();
  if($newStatus==='paid'){
    $sub=dbFetchOne("SELECT s.*,p.billing_cycle FROM tenant_platform_subscriptions s JOIN platform_plans p ON p.id=s.plan_id WHERE s.id=? FOR UPDATE",'i',(int)$invoice['subscription_id']);
    if($sub){
      $nextStart=$invoice['period_end'];$nextEnd=flexihubPlatformAddCycle($nextStart,$sub['billing_cycle']);
      $stmt=$conn->prepare("UPDATE tenant_platform_subscriptions SET status='active',current_period_start=?,current_period_end=?,grace_ends_at=NULL,updated_at=NOW() WHERE id=?");
      if($stmt){$stmt->bind_param('ssi',$nextStart,$nextEnd,$sub['id']);$stmt->execute();$stmt->close();}
      $conn->query("UPDATE tenants SET status='active' WHERE id=".$tenantId." AND status IN ('past_due','suspended','trial')");
    }
  }
  $stmt=$conn->prepare("UPDATE payment_gateway_transactions SET status='completed',provider_receipt=?,provider_transaction_id=?,result_code=?,result_description=?,callback_payload=?,confirmed_at=NOW() WHERE id=? AND tenant_id=?");
  if($stmt){$stmt->bind_param('sssssii',$receipt,$receipt,$resultCode,$resultDesc,$callbackJson,$txId,$tenantId);$stmt->execute();$stmt->close();}
  $conn->commit();
  $stmt=$conn->prepare("UPDATE payment_callbacks SET processing_status='processed',processed_at=NOW() WHERE tenant_id=? AND external_transaction_id=? LIMIT 1");
  if($stmt){$stmt->bind_param('is',$tenantId,$external);$stmt->execute();$stmt->close();}
  echo json_encode(['ResultCode'=>0,'ResultDesc'=>'SaaS payment received and allocated']);exit;
}
$saleId=(int)($tx['hotspot_sale_id']??0);if($saleId<=0)throw new RuntimeException('Hotspot sale is not linked.');
 $stmt=$conn->prepare("SELECT * FROM hotspot_sales WHERE id=? AND tenant_id=? FOR UPDATE");$stmt->bind_param('ii',$saleId,$tenantId);$stmt->execute();$sale=$stmt->get_result()->fetch_assoc();$stmt->close();if(!$sale)throw new RuntimeException('Hotspot sale not found.');
 if(in_array($sale['status'],['access_active','completed'],true)){$conn->commit();$stmt=$conn->prepare("UPDATE payment_callbacks SET processing_status='processed',processed_at=NOW() WHERE tenant_id=? AND external_transaction_id=? LIMIT 1");if($stmt){$stmt->bind_param('is',$tenantId,$external);$stmt->execute();$stmt->close();}echo json_encode(['ResultCode'=>0,'ResultDesc'=>'Payment already finalized']);exit;}
 $phone=(string)($sale['phone_number']??$tx['phone_number']??'');$mac=trim((string)($sale['device_mac']??''));
 $previous=null;$previousSessionId=0;
 $sql="SELECT hs.*,hss.id AS previous_session_id,hss.expires_at AS previous_expires_at
       FROM hotspot_sales hs
       LEFT JOIN hotspot_sessions hss ON hss.id=(
         SELECT x.id FROM hotspot_sessions x
         WHERE x.tenant_id=hs.tenant_id AND x.username=hs.hotspot_username AND x.status IN ('authorized','active')
         ORDER BY x.id DESC LIMIT 1
       )
       WHERE hs.tenant_id=? AND hs.id<>? AND hs.status='access_active' AND hs.hotspot_username IS NOT NULL
         AND hs.hotspot_username<>'' AND hs.expires_at IS NOT NULL AND hs.expires_at>NOW()
         AND ((?<>'' AND hs.device_mac=?) OR (?<>'' AND hs.phone_number=?))
       ORDER BY CASE WHEN ?<>'' AND hs.device_mac=? THEN 0 ELSE 1 END, hs.id DESC LIMIT 1";
 $stmt=$conn->prepare($sql);$stmt->bind_param('iissssss',$tenantId,$saleId,$mac,$mac,$phone,$phone,$mac,$mac);$stmt->execute();$previous=$stmt->get_result()->fetch_assoc();$stmt->close();
 $password=substr(strtoupper(bin2hex(random_bytes(6))),0,12);$username=$previous?(string)$previous['hotspot_username']:'HS'.strtoupper(substr(hash('sha256',$tenantId.':'.$saleId),0,10));
 if($previous){$previousSessionId=(int)($previous['previous_session_id']??0);$previousExpiresAt=$previous['previous_expires_at']??$previous['expires_at'];$auth=flexihubRenewHotspotAuthorization($tenantId,(int)$sale['package_id'],$username,$password,$mac,$previousSessionId,$previousExpiresAt);}
 else{$auth=flexihubAuthorizeHotspot($tenantId,(int)$sale['package_id'],$username,$password,$mac);}
 $enc=flexihubGatewayEncrypt($password);$status='access_active';
 if($previous){
   $oldSaleId=(int)$previous['id'];
   $stmt=$conn->prepare("UPDATE hotspot_sales SET status='renewed' WHERE id=? AND tenant_id=? AND status='access_active'");if($stmt){$stmt->bind_param('ii',$oldSaleId,$tenantId);$stmt->execute();$stmt->close();}
 }
 $stmt=$conn->prepare("UPDATE hotspot_sales SET status=?,hotspot_username=?,hotspot_password_encrypted=?,started_at=NOW(),expires_at=?,renewed_from_sale_id=? WHERE id=? AND tenant_id=?");$stmt->bind_param('ssssiii',$status,$username,$enc,$auth['expires_at'],$previous?(int)$previous['id']:null,$saleId,$tenantId);$stmt->execute();$stmt->close();
 $conn->commit();
 $stmt=$conn->prepare("UPDATE payment_callbacks SET processing_status='processed',processed_at=NOW() WHERE tenant_id=? AND external_transaction_id=? LIMIT 1");if($stmt){$stmt->bind_param('is',$tenantId,$external);$stmt->execute();$stmt->close();}
 echo json_encode(['ResultCode'=>0,'ResultDesc'=>'Callback received and hotspot authorization completed']);exit;
}catch(Throwable $e){
 if($conn->errno===0){} $conn->rollback();$err=substr($e->getMessage(),0,500);flexihubQueueHotspotFinalization($tenantId,(int)($tx['hotspot_sale_id']??0),$txId);$stmt=$conn->prepare("UPDATE payment_gateway_transactions SET status='confirmed',failure_reason=? WHERE id=? AND tenant_id=?");if($stmt){$stmt->bind_param('sii',$err,$txId,$tenantId);$stmt->execute();$stmt->close();}
 $stmt=$conn->prepare("UPDATE payment_callbacks SET processing_status='failed',error_message=? WHERE tenant_id=? AND external_transaction_id=? LIMIT 1");if($stmt){$stmt->bind_param('sis',$err,$tenantId,$external);$stmt->execute();$stmt->close();}
 http_response_code(200);echo json_encode(['ResultCode'=>0,'ResultDesc'=>'Callback received; finalization pending']);exit;
}
