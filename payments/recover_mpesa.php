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
$q=$conn->prepare("SELECT id,flow,account_reference,amount,phone_number,provider_receipt,status,failure_reason,created_at FROM payment_gateway_transactions WHERE id=? AND tenant_id=? LIMIT 1");
if(!$q){http_response_code(500);exit('Unable to load the gateway transaction.');}
$q->bind_param('ii',$transactionId,$tenantId);
if(!$q->execute()){ $q->close();http_response_code(500);exit('Unable to load the gateway transaction.');}
$transaction=$q->get_result()->fetch_assoc();$q->close();
if(!$transaction){http_response_code(404);exit('Gateway transaction not found.');}
if(($transaction['status']??'')!=='confirmed' || !in_array((string)($transaction['flow']??''),['customer','internet','billing'],true)){http_response_code(409);exit('Only confirmed customer-billing M-Pesa transactions can be recovered here.');}

$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    requireCsrf();
    try{
        $result=flexihubRecoverConfirmedCustomerMpesa($tenantId,$transactionId);
        $message='M-Pesa transaction recovered. Payment #'.$result['payment_id'].' was matched to invoice #'.$result['invoice_id'].'.';
        if(!empty($result['existing_payment'])) $message.=' The matching payment record already existed and was reused.';
        if(empty($result['invoice_fully_paid'])) $message.=' The invoice remains partially paid; no service renewal was triggered.';
        if(function_exists('setFlash')) setFlash('success',$message);
        header('Location: reconciliation.php');exit;
    }catch(Throwable $e){
        $error=$e->getMessage();
    }
}
$pageTitle='Recover Confirmed M-Pesa Payment';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<div class="main-content">
 <div class="page-header"><div><h1>Recover Confirmed M-Pesa Payment</h1><p>Verify and finalize a provider-confirmed customer payment without replaying the public callback.</p></div><a class="btn btn-secondary" href="reconciliation.php">Back to Reconciliation</a></div>
 <?php if($error!==''):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?>
 <div class="card" style="padding:20px;max-width:900px">
  <h2>Transaction #<?=e((string)$transaction['id'])?></h2>
  <div class="table-responsive"><table><tbody>
   <tr><th>Flow</th><td><?=e((string)$transaction['flow'])?></td></tr>
   <tr><th>Invoice reference</th><td><?=e((string)$transaction['account_reference'])?></td></tr>
   <tr><th>Amount</th><td><?=e(formatMoney($transaction['amount']))?></td></tr>
   <tr><th>Initiated phone</th><td><?=e((string)$transaction['phone_number'])?></td></tr>
   <tr><th>Provider receipt</th><td><?=e((string)($transaction['provider_receipt']??''))?></td></tr>
   <tr><th>Status</th><td><span class="status-badge warning">confirmed</span></td></tr>
   <tr><th>Previous failure</th><td><?=e((string)($transaction['failure_reason']??''))?></td></tr>
   <tr><th>Created</th><td><?=e((string)($transaction['created_at']??''))?></td></tr>
  </tbody></table></div>
  <div class="alert alert-warning" style="margin-top:16px">Recovery is limited to customer invoices. The stored successful callback must pass receipt, amount and phone validation. Existing matching payment records are reused; duplicate receipts, excess payments, missing account links and unsafe service-subscription states are rejected. Hotspot and SaaS transactions are not eligible.</div>
  <form method="post" onsubmit="return confirm('Recover this confirmed M-Pesa customer payment? This will update the payment ledger and may queue service activation if the invoice becomes fully paid.');">
   <?=csrfField()?>
   <input type="hidden" name="transaction_id" value="<?=e((string)$transactionId)?>">
   <button class="btn btn-primary" type="submit">Verify and Recover Payment</button>
  </form>
 </div>
</div>
<?php require_once '../includes/footer.php';?>
