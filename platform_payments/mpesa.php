<?php
require_once '../includes/auth.php';requireActiveUser();requireTenantContext();require_once '../includes/platform_mpesa.php';
$tenantId=(int)getCurrentTenantId();$invoiceId=(int)($_GET['invoice_id']??$_POST['invoice_id']??0);
$invoice=dbFetchOne("SELECT pi.*,s.status subscription_status FROM platform_invoices pi JOIN tenant_platform_subscriptions s ON s.id=pi.subscription_id WHERE pi.id=? AND pi.tenant_id=? LIMIT 1",'ii',$invoiceId,$tenantId);
$error='';$resultTx=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
 requireCsrf();
 try{
  if(!$invoice)throw new RuntimeException('Invoice not found.');
  $phone=flexihubMpesaNormalizePhone($_POST['phone_number']??'');
  $callbackUrl=rtrim(baseUrl(),'/').'/payments/mpesa_callback.php';
  $resultTx=flexihubCreatePlatformMpesaPayment($tenantId,$invoiceId,$phone,$callbackUrl);
 }catch(Throwable $e){$error=$e->getMessage();}
}
$pageTitle='Pay SaaS Invoice';require_once '../includes/header.php';require_once '../includes/sidebar.php';?>
<div class="main-content"><div class="page-header"><div><h1>Pay Flexihub Subscription</h1><p>Pay your Flexihub hosting subscription invoice using M-Pesa.</p></div></div>
<div class="card"><?php if($error):?><div class="alert"><?=e($error)?></div><?php endif;?><?php if(!$invoice):?><div class="notice">Invoice not found.</div><?php else:$balance=max(0,(float)$invoice['total_amount']-(float)$invoice['paid_amount']);?><div class="summary"><strong><?=e($invoice['invoice_number'])?></strong><span>Amount: <?=e(formatMoney($invoice['total_amount']))?></span><span>Outstanding: <b><?=e(formatMoney($balance))?></b></span><span>Status: <?=e(ucfirst($invoice['status']))?></span></div><?php if($resultTx&&($resultTx['status']??'')==='pending'):?><div class="success">M-Pesa STK Push sent to the phone number provided. Complete the prompt to finish payment.</div><script>setTimeout(function(){location.reload()},5000)</script><?php elseif($balance>0):?><form method="post"><?=csrfField()?><input type="hidden" name="invoice_id" value="<?=e($invoiceId)?>"><label>M-Pesa Phone Number</label><input name="phone_number" placeholder="07XXXXXXXX" required><button type="submit">Pay <?=e(formatMoney($balance))?> with M-Pesa</button></form><?php else:?><div class="success">This invoice is fully paid.</div><?php endif;endif;?></div></div>
<style>.main-content{padding:24px}.page-header{margin-bottom:24px}.card{max-width:680px;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:24px}.summary{display:grid;gap:8px;background:#f9fafb;padding:16px;border-radius:8px;margin-bottom:18px}label{display:block;font-weight:600;margin-bottom:6px}input{width:100%;box-sizing:border-box;padding:11px;border:1px solid #d1d5db;border-radius:7px}button{margin-top:16px;padding:11px 16px;border:0;border-radius:7px;background:#111827;color:#fff;font-weight:600}.success,.alert,.notice{padding:13px;border-radius:8px}.success{background:#dcfce7;color:#166534}.alert{background:#fee2e2;color:#991b1b}.notice{background:#f3f4f6}</style>
<?php require_once '../includes/footer.php';?>