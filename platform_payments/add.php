<?php
require_once '../includes/auth.php';requireLogin();requireHostContext();require_once '../includes/platform_billing.php';
$invoiceId=(int)($_GET['invoice_id']??$_POST['invoice_id']??0);
$invoice=$invoiceId?dbFetchOne("SELECT pi.*,t.tenant_code,t.name tenant_name FROM platform_invoices pi JOIN tenants t ON t.id=pi.tenant_id WHERE pi.id=? LIMIT 1",'i',$invoiceId):null;
if($_SERVER['REQUEST_METHOD']==='POST'){
 requireCsrf();
 $invoiceId=(int)($_POST['invoice_id']??0);$invoice=dbFetchOne("SELECT pi.*,t.tenant_code,t.name tenant_name FROM platform_invoices pi JOIN tenants t ON t.id=pi.tenant_id WHERE pi.id=? LIMIT 1",'i',$invoiceId);
 $result=$invoice?flexihubRecordPlatformPayment((int)$invoice['tenant_id'],$invoiceId,(float)($_POST['amount']??0),trim($_POST['payment_method']??'manual'),trim($_POST['reference']??'')?:null,'manual',null):['ok'=>false,'message'=>'Invoice not found.'];
 if($result['ok']){setFlash('success','SaaS payment recorded and invoice updated.');redirect('add.php?invoice_id='.$invoiceId);} setFlash('error',$result['message']);redirect('add.php?invoice_id='.$invoiceId);
}
$pageTitle='Record SaaS Payment';require_once '../includes/header.php';require_once '../includes/sidebar.php';$flash=getFlash();?>
<div class="main-content"><div class="page-header"><div><h1>Record SaaS Payment</h1><p>Record a payment against a Flexihub hosting subscription invoice.</p></div></div>
<?php if($flash):?><div class="alert"><?=e($flash['message'])?></div><?php endif;?>
<div class="card"><?php if(!$invoice):?><div class="notice">Select a valid SaaS invoice first.</div><?php else:$balance=max(0,(float)$invoice['total_amount']-(float)$invoice['paid_amount']);?>
<div class="summary"><b><?=e($invoice['invoice_number'])?></b><span><?=e($invoice['tenant_name'])?> (<?=e($invoice['tenant_code'])?>)</span><span>Outstanding: <strong><?=e(formatMoney($balance))?></strong></span></div>
<?php if($balance>0):?><form method="post"><?=csrfField()?><input type="hidden" name="invoice_id" value="<?=e($invoiceId)?>"><label>Amount</label><input type="number" name="amount" step="0.01" min="0.01" max="<?=e($balance)?>" required><label>Payment Method</label><select name="payment_method"><option value="manual">Manual</option><option value="mpesa">M-Pesa</option><option value="bank">Bank</option><option value="cash">Cash</option></select><label>Reference</label><input name="reference" maxlength="150"><button type="submit">Record Payment</button></form><?php else:?><div class="success">This invoice is fully paid.</div><?php endif;endif;?></div></div>
<style>.main-content{padding:24px}.page-header{margin-bottom:24px}.card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:24px;max-width:720px}.summary{display:grid;gap:8px;padding:15px;background:#f9fafb;border-radius:8px;margin-bottom:20px}label{display:block;font-weight:600;margin:14px 0 6px}input,select{width:100%;box-sizing:border-box;padding:10px;border:1px solid #d1d5db;border-radius:7px}button{margin-top:18px;padding:10px 16px;border:0;border-radius:7px;background:#111827;color:#fff;font-weight:600}.notice,.success,.alert{padding:12px 15px;border-radius:8px}.notice{background:#f3f4f6}.success{background:#dcfce7;color:#166534}.alert{background:#fee2e2;color:#991b1b;margin-bottom:18px}</style>
<?php require_once '../includes/footer.php';?>