<?php
require_once '../includes/auth.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('payments','view');
require_once '../includes/functions.php';
require_once '../includes/billing_workflow.php';

global $conn;
$tenantId=(int)getCurrentTenantId();
if($tenantId<=0){http_response_code(403);exit('A valid tenant context is required.');}
$gatewayRows=[];$callbackRows=[];$warnings=[];
$gatewayCheck=$conn->query("SHOW TABLES LIKE 'payment_gateway_transactions'");
if($gatewayCheck && $gatewayCheck->num_rows>0){
 $stmt=$conn->prepare("SELECT * FROM payment_gateway_transactions WHERE tenant_id=? AND status IN ('confirmed','failed') ORDER BY id DESC LIMIT 200");
 if($stmt){$stmt->bind_param('i',$tenantId);if($stmt->execute()){$result=$stmt->get_result();while($row=$result->fetch_assoc())$gatewayRows[]=$row;}else $warnings[]='Gateway transactions could not be loaded.';$stmt->close();}
 else $warnings[]='Gateway transaction storage could not be queried.';
}else $warnings[]='Gateway transaction storage is not installed.';
$callbackCheck=$conn->query("SHOW TABLES LIKE 'payment_callbacks'");
if($callbackCheck && $callbackCheck->num_rows>0){
 $stmt=$conn->prepare("SELECT * FROM payment_callbacks WHERE tenant_id=? AND processing_status IN ('received','failed') ORDER BY id DESC LIMIT 200");
 if($stmt){$stmt->bind_param('i',$tenantId);if($stmt->execute()){$result=$stmt->get_result();while($row=$result->fetch_assoc())$callbackRows[]=$row;}else $warnings[]='Callback events could not be loaded.';$stmt->close();}
 else $warnings[]='Callback event storage could not be queried.';
}else $warnings[]='Callback event storage is not installed.';
$allocatableGatewayIds=[];$gatewayAssessments=[];$exceptionCount=0;$excessCount=0;$unappliedCount=0;
$paymentCols=flexihubTableColumns('payments');
$paymentReferenceColumn=in_array('reference',$paymentCols,true)?'reference':(in_array('reference_number',$paymentCols,true)?'reference_number':null);
foreach($gatewayRows as $gatewayRow){
 $gatewayId=(int)($gatewayRow['id']??0);
 if(($gatewayRow['status']??'')!=='confirmed' || !in_array((string)($gatewayRow['flow']??''),['customer','internet','billing'],true))continue;
 $ref=trim((string)($gatewayRow['account_reference']??''));
 $matched=$ref!==''?dbFetchOne("SELECT * FROM invoices WHERE tenant_id=? AND invoice_number=? LIMIT 1",'is',$tenantId,$ref):null;
 if(!$matched && $ref!=='' && ctype_digit($ref))$matched=dbFetchOne("SELECT * FROM invoices WHERE tenant_id=? AND id=? LIMIT 1",'ii',$tenantId,(int)$ref);
 if(!$matched){
  $allocatableGatewayIds[]=$gatewayId;
  $gatewayAssessments[$gatewayId]=['kind'=>'unapplied','label'=>'Unapplied — invoice reference not matched','recover'=>false];
  $unappliedCount++;$exceptionCount++;
  continue;
 }
 $payload=json_decode((string)($gatewayRow['callback_payload']??''),true);
 $stk=$payload['Body']['stkCallback']??[];
 $receipt=trim((string)($gatewayRow['provider_receipt']??''));
 if($receipt==='' && is_array($stk)){
  foreach(($stk['CallbackMetadata']['Item']??[]) as $item)if(is_array($item)&&($item['Name']??'')==='MpesaReceiptNumber'){$receipt=trim((string)($item['Value']??''));break;}
 }
 $existingPayment=null;
 if($receipt!=='' && $paymentReferenceColumn){
  $existingPayment=dbFetchOne("SELECT id,invoice_id,amount FROM payments WHERE tenant_id=? AND `".$paymentReferenceColumn."`=? LIMIT 1",'is',$tenantId,$receipt);
 }
 if($existingPayment){
  $sameInvoice=(int)($existingPayment['invoice_id']??0)===(int)$matched['id'];
  $sameAmount=abs((float)($existingPayment['amount']??0)-(float)($gatewayRow['amount']??0))<=0.01;
  if($sameInvoice && $sameAmount){
   $gatewayAssessments[$gatewayId]=['kind'=>'recover','label'=>'Payment exists — finalize safely','recover'=>true];
  }else{
   $gatewayAssessments[$gatewayId]=['kind'=>'conflict','label'=>'Receipt conflict — finance review','recover'=>false];
   $exceptionCount++;
  }
  continue;
 }
 $invoiceTotal=flexihubInvoiceTotal($matched);
 $invoicePaid=flexihubInvoicePaid((int)$matched['id'],$tenantId);
 $balance=max(0,$invoiceTotal-$invoicePaid);
 $amount=(float)($gatewayRow['amount']??0);
 if($amount>$balance+0.01){
  $gatewayAssessments[$gatewayId]=['kind'=>'excess','label'=>'Excess collection — finance review','recover'=>false,'balance'=>$balance];
  $excessCount++;$exceptionCount++;
 }else{
  $gatewayAssessments[$gatewayId]=['kind'=>'recover','label'=>'Within outstanding balance','recover'=>true,'balance'=>$balance];
 }
}
$pageTitle='Payment Reconciliation';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<div class="main-content">
 <div class="page-header"><div><h1>Payment Reconciliation</h1><p>Review confirmed gateway payments awaiting finalization, failed transactions and callback events that need investigation.</p></div><a class="btn btn-secondary" href="index.php">Back to Payments</a></div>
 <?php foreach($warnings as $warning):?><div class="alert alert-warning"><?=e($warning)?></div><?php endforeach;?>
 <div class="stats-grid" style="margin-bottom:20px">
  <div class="card" style="padding:16px"><div class="stat-label">Gateway items to review</div><div class="stat-value"><?=count($gatewayRows)?></div></div>
  <div class="card" style="padding:16px"><div class="stat-label">Callback events to review</div><div class="stat-value"><?=count($callbackRows)?></div></div>
  <div class="card" style="padding:16px"><div class="stat-label">Unapplied receipts</div><div class="stat-value"><?=e((string)$unappliedCount)?></div></div>
  <div class="card" style="padding:16px"><div class="stat-label">Excess / conflicts</div><div class="stat-value"><?=e((string)$excessCount)?></div></div>
 </div>
 <div class="card" style="padding:20px;margin-bottom:20px">
  <h2>Gateway transactions requiring review</h2>
  <p>Confirmed transactions have a successful provider response but have not completed the billing workflow. Failed transactions require investigation; do not assume they represent settled funds.</p>
  <div class="table-responsive"><table><thead><tr><th>Transaction</th><th>Flow</th><th>Reference</th><th>Amount</th><th>Receipt</th><th>Status</th><th>Assessment</th><th>Reason</th><th>Created</th><th>Action</th></tr></thead><tbody>
  <?php foreach($gatewayRows as $row):?><tr>
   <td>#<?=e((string)($row['id']??''))?></td><td><?=e((string)($row['flow']??''))?></td>
   <td><?=e((string)($row['account_reference']??$row['checkout_request_id']??''))?></td>
   <td><?=e(formatMoney($row['amount']??0))?></td><td><?=e((string)($row['provider_receipt']??''))?></td>
   <td><span class="status-badge <?=($row['status']??'')==='confirmed'?'warning':'danger'?>"><?=e((string)($row['status']??''))?></span></td>
   <td><?php $assessment=$gatewayAssessments[(int)$row['id']]??null; if($assessment):?><span class="status-badge <?=in_array($assessment['kind'],['excess','unapplied','conflict'],true)?'warning':'success'?>"><?=e($assessment['label'])?></span><?php else:?>—<?php endif;?></td>
   <td><?=e((string)($row['failure_reason']??$row['result_description']??''))?></td><td><?=e((string)($row['created_at']??''))?></td><td><?php if(($row['status']??'')==='confirmed' && in_array((string)($row['flow']??''),['customer','internet','billing'],true)):?><?php if(!empty($assessment['recover'])):?><a class="btn btn-sm btn-primary" href="recover_mpesa.php?id=<?=e((string)$row['id'])?>">Recover</a><?php elseif(in_array((int)$row['id'],$allocatableGatewayIds,true)):?><a class="btn btn-sm btn-secondary" href="allocate_mpesa.php?id=<?=e((string)$row['id'])?>">Allocate</a><?php else:?>Finance review required<?php endif;?><?php else:?>—<?php endif;?></td>
  </tr><?php endforeach;?>
  <?php if(!$gatewayRows):?><tr><td colspan="10">No confirmed or failed gateway transactions require review.</td></tr><?php endif;?>
  </tbody></table></div>
 </div>
 <div class="card" style="padding:20px">
  <h2>Callback events requiring review</h2>
  <p>Received events may not yet have completed processing. Failed events retain the recorded error for investigation.</p>
  <div class="table-responsive"><table><thead><tr><th>Event</th><th>Provider reference</th><th>Amount</th><th>Phone</th><th>Status</th><th>Error</th><th>Received</th></tr></thead><tbody>
  <?php foreach($callbackRows as $row):?><tr>
   <td>#<?=e((string)($row['id']??''))?></td><td><?=e((string)($row['external_transaction_id']??$row['transaction_reference']??''))?></td>
   <td><?=e(isset($row['amount'])?formatMoney($row['amount']):'—')?></td><td><?=e((string)($row['phone_number']??''))?></td>
   <td><?=e((string)($row['processing_status']??''))?></td><td><?=e((string)($row['error_message']??''))?></td><td><?=e((string)($row['created_at']??''))?></td>
  </tr><?php endforeach;?>
  <?php if(!$callbackRows):?><tr><td colspan="7">No unprocessed or failed callback events require review.</td></tr><?php endif;?>
  </tbody></table></div>
 </div>
 <div class="alert alert-info" style="margin-top:18px">Confirmed customer-billing transactions can be recovered only when the stored callback and receipt are verified and the payment fits the invoice balance. Unmatched receipts are routed to controlled allocation. Excess collections and receipt conflicts are held for finance review; this screen does not split, post, refund or write off excess funds. Hotspot/SaaS flows remain investigation-only.</div>
</div>
<?php require_once '../includes/footer.php';?>
