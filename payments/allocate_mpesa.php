<?php
require_once '../includes/auth.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('payments','edit');
require_once '../includes/mpesa_recovery.php';

global $conn;
$tenantId=(int)getCurrentTenantId();
$transactionId=(int)($_GET['id']??$_POST['transaction_id']??0);
if($tenantId<=0 || $transactionId<=0){http_response_code(400);exit('A valid gateway transaction is required.');}
$q=$conn->prepare("SELECT * FROM payment_gateway_transactions WHERE id=? AND tenant_id=? AND status='confirmed' AND flow IN ('customer','internet','billing') LIMIT 1");
if(!$q){http_response_code(500);exit('Unable to load the confirmed gateway transaction.');}
$q->bind_param('ii',$transactionId,$tenantId);
if(!$q->execute()){ $q->close();http_response_code(500);exit('Unable to load the confirmed gateway transaction.');}
$transaction=$q->get_result()->fetch_assoc();$q->close();
if(!$transaction){http_response_code(404);exit('Confirmed customer-billing transaction not found.');}

$reference=trim((string)($transaction['account_reference']??''));
$originalInvoice=null;
if($reference!==''){
 $originalInvoice=dbFetchOne("SELECT id FROM invoices WHERE tenant_id=? AND invoice_number=? LIMIT 1",'is',$tenantId,$reference);
 if(!$originalInvoice && ctype_digit($reference))$originalInvoice=dbFetchOne("SELECT id FROM invoices WHERE tenant_id=? AND id=? LIMIT 1",'ii',$tenantId,(int)$reference);
}
if($originalInvoice){http_response_code(409);exit('The original invoice reference is valid. Use the standard recovery action instead.');}

$eligibleInvoices=[];
$invoiceStmt=$conn->prepare("SELECT * FROM invoices WHERE tenant_id=? ORDER BY id DESC LIMIT 500");
if(!$invoiceStmt){http_response_code(500);exit('Unable to load tenant invoices.');}
$invoiceStmt->bind_param('i',$tenantId);
if(!$invoiceStmt->execute()){$invoiceStmt->close();http_response_code(500);exit('Unable to load tenant invoices.');}
$invoiceResult=$invoiceStmt->get_result();
$txPhone=trim((string)($transaction['phone_number']??''));
$txAmount=(float)($transaction['amount']??0);
while($invoice=$invoiceResult->fetch_assoc()){
 $customerId=(int)($invoice['customer_id']??0);
 if($customerId<=0)continue;
 $customer=dbFetchOne("SELECT first_name,last_name,phone FROM customers WHERE id=? AND tenant_id=? LIMIT 1",'ii',$customerId,$tenantId);
 if(!$customer || trim((string)($customer['phone']??''))==='' || !hash_equals($txPhone,trim((string)$customer['phone'])))continue;
 $total=flexihubInvoiceTotal($invoice);
 try{$paid=flexihubInvoicePaid((int)$invoice['id'],$tenantId);}catch(Throwable $e){continue;}
 $balance=max(0,$total-$paid);
 if($balance<=0.0001 || $txAmount>$balance+0.01)continue;
 $eligibleInvoices[]=['id'=>(int)$invoice['id'],'number'=>(string)($invoice['invoice_number']??$invoice['id']),'customer'=>trim((string)($customer['first_name']??'').' '.(string)($customer['last_name']??'')),'balance'=>$balance];
}
$invoiceStmt->close();

$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 requireCsrf();
 $targetInvoiceId=(int)($_POST['invoice_id']??0);
 $reason=trim((string)($_POST['allocation_reason']??''));
 $allowed=false;
 foreach($eligibleInvoices as $option)if($option['id']===$targetInvoiceId)$allowed=true;
 if(!$allowed)$error='Select an eligible invoice belonging to the verified payer.';
 else{
  try{
   $result=flexihubRecoverConfirmedCustomerMpesa($tenantId,$transactionId,$targetInvoiceId,$reason);
   $message='M-Pesa receipt allocated to invoice '.$result['invoice_id'].' and payment #'.$result['payment_id'].' recorded.';
   if(empty($result['invoice_fully_paid']))$message.=' The invoice remains partially paid; service renewal was not triggered.';
   if(function_exists('setFlash'))setFlash('success',$message);
   header('Location: reconciliation.php');exit;
  }catch(Throwable $e){$error=$e->getMessage();}
 }
}
$pageTitle='Allocate Unmatched M-Pesa Receipt';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<div class="main-content">
 <div class="page-header"><div><h1>Allocate Unmatched M-Pesa Receipt</h1><p>Assign a confirmed customer payment to the correct tenant invoice after verifying the payer.</p></div><a class="btn btn-secondary" href="reconciliation.php">Back to Reconciliation</a></div>
 <?php if($error!==''):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?>
 <div class="card" style="padding:20px;max-width:950px">
  <h2>Confirmed transaction #<?=e((string)$transactionId)?></h2>
  <div class="table-responsive"><table><tbody>
   <tr><th>Original invoice reference</th><td><?=e($reference)?></td></tr>
   <tr><th>M-Pesa receipt</th><td><?=e((string)($transaction['provider_receipt']??''))?></td></tr>
   <tr><th>Payer phone</th><td><?=e($txPhone)?></td></tr>
   <tr><th>Confirmed amount</th><td><?=e(formatMoney($txAmount))?></td></tr>
   <tr><th>Transaction status</th><td><span class="status-badge warning">confirmed</span></td></tr>
  </tbody></table></div>
  <div class="alert alert-warning" style="margin-top:16px">Only invoices belonging to a customer whose registered phone exactly matches the M-Pesa payer are listed. The receipt must be unique, and the payment cannot exceed the selected invoice's outstanding balance. This action is audited and cannot allocate hotspot or platform SaaS payments.</div>
  <?php if(!$eligibleInvoices):?><div class="alert alert-info">No eligible outstanding invoice was found for this verified payer. No payment has been changed.</div><?php else:?>
  <form method="post">
   <?=csrfField()?>
   <input type="hidden" name="transaction_id" value="<?=e((string)$transactionId)?>">
   <div class="form-group"><label for="invoice_id">Customer invoice</label><select id="invoice_id" name="invoice_id" required><option value="">Select an invoice</option><?php foreach($eligibleInvoices as $option):?><option value="<?=e((string)$option['id'])?>" <?=((int)($_POST['invoice_id']??0)===$option['id']?'selected':'')?>><?=e($option['number'].' — '.$option['customer'].' — balance '.formatMoney($option['balance']))?></option><?php endforeach;?></select></div>
   <div class="form-group"><label for="allocation_reason">Reason for manual allocation</label><textarea id="allocation_reason" name="allocation_reason" rows="3" minlength="10" maxlength="500" required><?=e((string)($_POST['allocation_reason']??''))?></textarea><small>Record why the original invoice reference could not be used and why this invoice is correct.</small></div>
   <button class="btn btn-primary" type="submit" onclick="return confirm('Allocate this confirmed receipt to the selected invoice? The payment ledger and invoice balance will be updated.');">Verify and Allocate Receipt</button>
  </form>
  <?php endif;?>
 </div>
</div>
<?php require_once '../includes/footer.php';?>
