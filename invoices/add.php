<?php
require_once '../includes/auth.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('invoices','create');
require_once '../includes/billing_workflow.php';

global $conn;
$tid=(int)getCurrentTenantId();
if($tid<=0){http_response_code(403);exit('A valid tenant context is required.');}
if(!flexihubTableHasColumn('customers','tenant_id')||!flexihubTableHasColumn('invoices','tenant_id')){http_response_code(503);exit('Invoice tenant isolation is unavailable. Please contact the administrator.');}
$errors=[];
$cols=flexihubTableColumns('invoices');
$taxSettings=null;$taxStorageReady=false;
$taxTableCheck=$conn->query("SHOW TABLES LIKE 'tenant_tax_settings'");
$taxLineCheck=$conn->query("SHOW TABLES LIKE 'invoice_tax_lines'");
if($taxTableCheck && $taxLineCheck && $taxTableCheck->num_rows>0 && $taxLineCheck->num_rows>0){
 $taxStorageReady=true;
 $taxStmt=$conn->prepare("SELECT tax_name,rate_percent,is_active FROM tenant_tax_settings WHERE tenant_id=? LIMIT 1");
 if($taxStmt){$taxStmt->bind_param('i',$tid);$taxStmt->execute();$taxSettings=$taxStmt->get_result()->fetch_assoc();$taxStmt->close();}
}

$customers=[];
$q=$conn->prepare("SELECT id,customer_number,first_name,last_name FROM customers WHERE tenant_id=? ORDER BY first_name,last_name");
if($q){$q->bind_param('i',$tid);$q->execute();$z=$q->get_result();while($x=$z->fetch_assoc())$customers[]=$x;$q->close();}

$accounts=[];
$q=$conn->prepare("SELECT ia.*, c.customer_number, c.first_name, c.last_name, ip.price AS plan_price FROM internet_accounts ia INNER JOIN customers c ON c.id=ia.customer_id AND c.tenant_id=ia.tenant_id LEFT JOIN internet_plans ip ON ip.id=ia.plan_id AND ip.tenant_id=ia.tenant_id WHERE ia.tenant_id=? ORDER BY c.first_name,c.last_name,ia.id DESC");
if($q){$q->bind_param('i',$tid);$q->execute();$z=$q->get_result();while($x=$z->fetch_assoc())$accounts[]=$x;$q->close();}

$v=['customer_id'=>trim((string)($_GET['customer_id']??'')),'account_id'=>trim((string)($_GET['account_id']??'')),'amount'=>'','invoice_date'=>date('Y-m-d'),'due_date'=>date('Y-m-d'),'description'=>''];
if ($v['account_id'] !== '') {
    foreach ($accounts as $accountOption) {
        if ((int)$accountOption['id'] === (int)$v['account_id']) {
            $v['customer_id'] = (string)$accountOption['customer_id'];
            $v['amount'] = is_numeric($accountOption['plan_price'] ?? null) ? number_format((float)$accountOption['plan_price'], 2, '.', '') : '';
            break;
        }
    }
}
if($_SERVER['REQUEST_METHOD']==='POST'){
    requireCsrf();
    foreach($v as $k=>$d)$v[$k]=trim((string)($_POST[$k]??$d));
    $cid=(int)$v['customer_id']; $accountId=(int)$v['account_id']; $amount=(float)$v['amount'];
    if (!$cid || !is_finite($amount) || $amount <= 0) {
        $errors[] = 'Customer and a positive amount are required.';
    }

    $isValidDate = static function ($value) {
        if (!is_string($value) || $value === '') {
            return false;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date instanceof DateTimeImmutable && $date->format('Y-m-d') === $value;
    };

    if (!$isValidDate($v['invoice_date'])) {
        $errors[] = 'Enter a valid invoice date.';
    }
    if (!$isValidDate($v['due_date'])) {
        $errors[] = 'Enter a valid due date.';
    }
    if ($isValidDate($v['invoice_date']) && $isValidDate($v['due_date']) && $v['due_date'] < $v['invoice_date']) {
        $errors[] = 'The due date cannot be earlier than the invoice date.';
    }

    if(!$errors){
        $stmt=$conn->prepare("SELECT id FROM customers WHERE id=? AND tenant_id=? LIMIT 1");
        if($stmt){$stmt->bind_param('ii',$cid,$tid);$stmt->execute();$ok=(bool)$stmt->get_result()->fetch_assoc();$stmt->close();if(!$ok)$errors[]='Customer not found.';}
        else $errors[]='Unable to validate customer.';
    }

    if(!$errors && $accountId > 0){
        $accountCheck=$conn->prepare("SELECT id FROM internet_accounts WHERE id=? AND customer_id=? AND tenant_id=? LIMIT 1");
        if($accountCheck){$accountCheck->bind_param('iii',$accountId,$cid,$tid);$accountCheck->execute();$validAccount=(bool)$accountCheck->get_result()->fetch_assoc();$accountCheck->close();if(!$validAccount)$errors[]='The selected internet account does not belong to this customer.';}
        else $errors[]='Unable to verify the selected internet account.';
    }

    if(!$errors){
        $taxActive=$taxStorageReady && $taxSettings && (int)$taxSettings['is_active']===1;
        $taxRate=$taxActive?(float)$taxSettings['rate_percent']:0.0;
        $taxName=$taxActive?(string)$taxSettings['tax_name']:'';
        $taxAmount=round($amount*$taxRate/100,2);
        $grandTotal=round($amount+$taxAmount,2);
        $data=['tenant_id'=>$tid,'customer_id'=>$cid,'internet_account_id'=>$accountId>0?$accountId:null,'account_id'=>$accountId>0?$accountId:null,'invoice_date'=>$v['invoice_date'],'due_date'=>$v['due_date'],'description'=>$v['description'],
               'total_amount'=>$grandTotal,'amount'=>$grandTotal,'subtotal'=>$amount,'tax_amount'=>$taxAmount,'tax_rate'=>$taxRate,'total'=>$grandTotal,'status'=>'Unpaid','paid_amount'=>0,'amount_paid'=>0,'balance'=>$grandTotal,
               'invoice_number'=>'INV-'.date('YmdHis').'-'.random_int(100,999)];
        $conn->begin_transaction();
        try{
            $id=flexihubWorkflowInsert('invoices',$data);
            if(!$id)throw new RuntimeException('Unable to create invoice. Please check the invoice table fields and try again.');
            if($taxActive){
                $taxInsert=$conn->prepare("INSERT INTO invoice_tax_lines (tenant_id,invoice_id,tax_name,rate_percent,taxable_amount,tax_amount) VALUES (?,?,?,?,?,?)");
                if(!$taxInsert)throw new RuntimeException('Unable to prepare the invoice tax snapshot.');
                $taxInsert->bind_param('iisddd',$tid,$id,$taxName,$taxRate,$amount,$taxAmount);
                if(!$taxInsert->execute()){$taxInsert->close();throw new RuntimeException('Unable to save the invoice tax snapshot.');}
                $taxInsert->close();
            }
            $conn->commit();
            redirect('index.php');
        }catch(Throwable $e){$conn->rollback();$errors[]=$e->getMessage();}
    }
}
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<div class="main-content">
<div class="page-header"><div><h1>Create Invoice</h1><p>Issue a tenant-scoped customer invoice.</p></div><a class="btn btn-secondary" href="index.php">Back</a></div>
<div class="card" style="padding:20px">
<?php if($errors):?><div class="alert alert-danger"><?=e(implode(' ',$errors))?></div><?php endif;?>
<form method="post"><?=csrfField()?>
<div class="form-grid">
<label>Customer *<select name="customer_id" required><option value="">Select customer</option><?php foreach($customers as $c):?><option value="<?=$c['id']?>" <?=$v['customer_id']==$c['id']?'selected':''?>><?=e($c['customer_number'].' — '.trim($c['first_name'].' '.$c['last_name']))?></option><?php endforeach;?></select></label>
<label>Internet Account (optional)<select name="account_id"><option value="">No specific account</option><?php foreach($accounts as $a): ?><option value="<?=(int)$a['id']?>" <?=((int)$v['account_id']===(int)$a['id'])?'selected':''?>><?=e(($a['customer_number']??'').' — '.trim(($a['first_name']??'').' '.($a['last_name']??'')).' · '.($a['account_number']??$a['username']??('Account #'.$a['id'])))?></option><?php endforeach; ?></select></label>
<label>Subtotal (before tax) *<input type="number" name="amount" step="0.01" min="0.01" value="<?=e($v['amount'])?>" required></label>
<label>Invoice Date<input type="date" name="invoice_date" value="<?=e($v['invoice_date'])?>"></label>
<label>Due Date<input type="date" name="due_date" value="<?=e($v['due_date'])?>"></label>
<label style="grid-column:1/-1">Description<textarea name="description"><?=e($v['description'])?></textarea></label>
</div>
<?php if($taxSettings && (int)$taxSettings['is_active']===1):?><p class="alert alert-info"><?=e($taxSettings['tax_name'])?> of <?=e(number_format((float)$taxSettings['rate_percent'],4))?>% will be added to the subtotal.</p><?php elseif(!$taxStorageReady):?><p class="alert alert-warning">Tax migration 045 is not installed yet. Invoices will continue without tax until tax settings are enabled.</p><?php endif;?>
<button class="btn btn-primary">Create Invoice</button>
</form></div></div>
<?php require_once '../includes/footer.php';?>