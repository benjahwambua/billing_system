<?php
require_once '../includes/auth.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('payments','create');
require_once '../includes/billing_workflow.php';

global $conn;
$tid=(int)getCurrentTenantId();
$errors=[];

$invoices=[];
$q=$conn->prepare("SELECT * FROM invoices WHERE tenant_id=? ORDER BY id DESC LIMIT 300");
if($q){$q->bind_param('i',$tid);$q->execute();$z=$q->get_result();while($x=$z->fetch_assoc())$invoices[]=$x;$q->close();}

$accounts=[];
$q=$conn->prepare("SELECT ia.*,c.customer_number,c.first_name,c.last_name,ip.name plan_name FROM internet_accounts ia JOIN customers c ON c.id=ia.customer_id AND c.tenant_id=ia.tenant_id LEFT JOIN internet_plans ip ON ip.id=ia.plan_id AND ip.tenant_id=ia.tenant_id WHERE ia.tenant_id=? ORDER BY c.first_name,c.last_name");
if($q){$q->bind_param('i',$tid);$q->execute();$z=$q->get_result();while($x=$z->fetch_assoc())$accounts[]=$x;$q->close();}

$v=['invoice_id'=>'','account_id'=>'','amount'=>'','payment_method'=>'cash','payment_date'=>date('Y-m-d'),'reference'=>''];
if($_SERVER['REQUEST_METHOD']==='POST'){
    requireCsrf();
    foreach($v as $k=>$d)$v[$k]=trim((string)($_POST[$k]??$d));
    $iid=(int)$v['invoice_id']; $aid=(int)$v['account_id']; $amount=(float)$v['amount'];
    if(!$iid||$amount<=0)$errors[]='Invoice and positive payment amount are required.';

    $invoice=null;
    if(!$errors){
        $q=$conn->prepare("SELECT * FROM invoices WHERE id=? AND tenant_id=? LIMIT 1");
        if($q){$q->bind_param('ii',$iid,$tid);$q->execute();$invoice=$q->get_result()->fetch_assoc();$q->close();}
        if(!$invoice)$errors[]='Invoice not found.';
    }

    if(!$errors && $aid){
        $q=$conn->prepare("SELECT id,customer_id FROM internet_accounts WHERE id=? AND tenant_id=? LIMIT 1");
        if($q){$q->bind_param('ii',$aid,$tid);$q->execute();$accountCheck=$q->get_result()->fetch_assoc();$q->close();if(!$accountCheck)$errors[]='Internet account not found.';elseif((int)($accountCheck['customer_id']??0)!==(int)($invoice['customer_id']??0))$errors[]='The selected internet account does not belong to the invoice customer.';}
    }

    if(!$errors){
        $total=flexihubInvoiceTotal($invoice);
        $paid=flexihubInvoicePaid($iid,$tid);
        $balance=max(0,$total-$paid);
        if($balance<=0)$errors[]='This invoice is already fully paid.';
        elseif($amount>$balance+0.00001)$errors[]='Payment exceeds the outstanding invoice balance of '.formatMoney($balance).'.';
    }

    if(!$errors){
        $data=['tenant_id'=>$tid,'invoice_id'=>$iid,'amount'=>$amount,'payment_method'=>$v['payment_method'],
               'payment_date'=>$v['payment_date'],'reference'=>$v['reference'],
               'payment_number'=>'PAY-'.date('YmdHis').'-'.random_int(100,999)];
        if($aid)$data['account_id']=$aid;
        $paymentId=flexihubWorkflowInsert('payments',$data);
        if($paymentId){
            $payment=['id'=>$paymentId]+$data;
            $invoicePaidBefore=flexihubInvoicePaid($iid,$tid);
            $invoiceTotal=flexihubInvoiceTotal($invoice);
            flexihubRefreshInvoiceStatus($iid,$tid);
            $invoiceFullyPaid=($invoicePaidBefore+$amount+0.00001 >= $invoiceTotal);
            $receiptId=flexihubCreatePaymentArtifacts($paymentId,$payment,$invoice,$tid);
            if($aid && $invoiceFullyPaid){
                $renewed=flexihubRenewInternetAccount($aid,$tid);
                if($renewed) flexihubCreateServiceSubscription($aid,$tid,$iid,$paymentId);
            }
            setFlash('success','Payment recorded successfully'.($receiptId?' and receipt generated.':'.'));
            redirect('../payments/index.php');
        }
        $errors[]='Unable to record payment. Please check the payment table fields and try again.';
    }
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<div class="main-content">
<div class="page-header"><div><h1>Record Payment</h1><p>Record a tenant-scoped customer payment and update the billing workflow.</p></div></div>
<div class="card" style="padding:20px">
<?php if($errors):?><div class="alert alert-danger"><?=e(implode(' ',$errors))?></div><?php endif;?>
<form method="post"><?=csrfField()?>
<div class="form-grid">
<label>Invoice *<select name="invoice_id" required><option value="">Select invoice</option><?php foreach($invoices as $x):?><option value="<?=$x['id']?>" <?=$v['invoice_id']==$x['id']?'selected':''?>><?=e($x['invoice_number']??$x['id'])?> — <?=e(formatMoney(flexihubInvoiceTotal($x)-flexihubInvoicePaid($x['id'],$tid)))?> outstanding</option><?php endforeach;?></select></label>
<label>Internet Account (optional)<select name="account_id"><option value="">No service renewal</option><?php foreach($accounts as $x):?><option value="<?=$x['id']?>" <?=$v['account_id']==$x['id']?'selected':''?>><?=e(($x['customer_number']??'').' — '.trim(($x['first_name']??'').' '.($x['last_name']??'')).' — '.($x['plan_name']??($x['username']??$x['id'])))?></option><?php endforeach;?></select></label>
<label>Amount *<input type="number" name="amount" step="0.01" min="0.01" value="<?=e($v['amount'])?>" required></label>
<label>Payment Method<select name="payment_method"><option value="cash" <?=$v['payment_method']==='cash'?'selected':''?>>Cash</option><option value="mpesa" <?=$v['payment_method']==='mpesa'?'selected':''?>>M-Pesa</option><option value="bank" <?=$v['payment_method']==='bank'?'selected':''?>>Bank</option><option value="card" <?=$v['payment_method']==='card'?'selected':''?>>Card</option></select></label>
<label>Date<input type="date" name="payment_date" value="<?=e($v['payment_date'])?>"></label>
<label>Reference<input name="reference" value="<?=e($v['reference'])?>"></label>
</div><button class="btn btn-primary">Save Payment</button>
</form></div></div>
<?php require_once '../includes/footer.php';?>