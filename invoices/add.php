<?php
require_once '../includes/auth.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('invoices','create');
require_once '../includes/billing_workflow.php';

global $conn;
$tid=(int)getCurrentTenantId();
$errors=[];
$cols=flexihubTableColumns('invoices');

$customers=[];
$stmt=$conn->prepare("SELECT id,customer_number,first_name,last_name FROM customers WHERE tenant_id=? ORDER BY first_name,last_name");
if($stmt){
    $stmt->bind_param('i',$tid);
    $stmt->execute();
    $result=$stmt->get_result();
    while($row=$result->fetch_assoc())$customers[]=$row;
    $stmt->close();
}

$accounts=[];
$stmt=$conn->prepare("SELECT ia.id,ia.customer_id,ia.username,ia.account_number,ip.name AS plan_name
                      FROM internet_accounts ia
                      LEFT JOIN internet_plans ip ON ip.id=ia.plan_id AND ip.tenant_id=ia.tenant_id
                      WHERE ia.tenant_id=? ORDER BY ia.id DESC");
if($stmt){
    $stmt->bind_param('i',$tid);
    $stmt->execute();
    $result=$stmt->get_result();
    while($row=$result->fetch_assoc())$accounts[]=$row;
    $stmt->close();
}

$v=['customer_id'=>'','account_id'=>'','amount'=>'','invoice_date'=>date('Y-m-d'),'due_date'=>date('Y-m-d'),'description'=>''];

if($_SERVER['REQUEST_METHOD']==='POST'){
    requireCsrf();
    foreach($v as $key=>$default)$v[$key]=trim((string)($_POST[$key]??$default));

    $cid=(int)$v['customer_id'];
    $aid=(int)$v['account_id'];
    $amount=(float)$v['amount'];

    if(!$cid || $amount<=0)$errors[]='Customer and a positive amount are required.';
    if($v['due_date'] && $v['invoice_date'] && $v['due_date']<$v['invoice_date'])$errors[]='Due date cannot be before the invoice date.';

    if(!$errors){
        $stmt=$conn->prepare("SELECT id FROM customers WHERE id=? AND tenant_id=? LIMIT 1");
        if($stmt){
            $stmt->bind_param('ii',$cid,$tid);
            $stmt->execute();
            $ok=(bool)$stmt->get_result()->fetch_assoc();
            $stmt->close();
            if(!$ok)$errors[]='Customer not found.';
        }else $errors[]='Unable to validate customer.';
    }

    if(!$errors && $aid){
        $stmt=$conn->prepare("SELECT id,customer_id FROM internet_accounts WHERE id=? AND tenant_id=? LIMIT 1");
        if($stmt){
            $stmt->bind_param('ii',$aid,$tid);
            $stmt->execute();
            $account=$stmt->get_result()->fetch_assoc();
            $stmt->close();
            if(!$account)$errors[]='Internet account not found.';
            elseif((int)$account['customer_id']!==$cid)$errors[]='The selected internet account does not belong to the selected customer.';
        }else $errors[]='Unable to validate internet account.';
    }

    if(!$errors){
        $invoiceNumber='';
        for($attempt=0;$attempt<5;$attempt++){
            $candidate='INV-'.date('YmdHis').'-'.random_int(100000,999999);
            if(in_array('invoice_number',$cols,true)){
                $check=$conn->prepare("SELECT id FROM invoices WHERE invoice_number=? AND tenant_id=? LIMIT 1");
                if($check){
                    $check->bind_param('si',$candidate,$tid);
                    $check->execute();
                    $exists=(bool)$check->get_result()->fetch_assoc();
                    $check->close();
                    if($exists)continue;
                }
            }
            $invoiceNumber=$candidate;
            break;
        }
        if(!$invoiceNumber)$errors[]='Unable to generate a unique invoice number.';
    }

    if(!$errors){
        $data=[
            'tenant_id'=>$tid,
            'customer_id'=>$cid,
            'invoice_date'=>$v['invoice_date'],
            'due_date'=>$v['due_date'],
            'description'=>$v['description'],
            'total_amount'=>$amount,
            'amount'=>$amount,
            'total'=>$amount,
            'status'=>'unpaid',
            'paid_amount'=>0,
            'balance'=>$amount,
            'invoice_number'=>$invoiceNumber
        ];
        if($aid && in_array('account_id',$cols,true))$data['account_id']=$aid;

        $id=flexihubWorkflowInsert('invoices',$data);
        if($id){
            if(function_exists('logAudit'))logAudit('invoice_created',(int)$id,'invoices',['customer_id'=>$cid,'account_id'=>$aid,'amount'=>$amount]);
            setFlash('success','Invoice created successfully.');
            redirect('view.php?id='.(int)$id);
        }
        $errors[]='Unable to create invoice. Please check the invoice table fields and try again.';
    }
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<div class="main-content">
<div class="page-header"><div><h1>Create Invoice</h1><p>Issue a tenant-scoped customer invoice.</p></div><a class="btn btn-secondary" href="index.php">Back</a></div>
<div class="card" style="padding:20px">
<?php if($errors): ?><div class="alert alert-danger"><?=e(implode(' ',$errors))?></div><?php endif; ?>
<form method="post"><?=csrfField()?>
<div class="form-grid">
<label>Customer *
<select name="customer_id" id="customer_id" required>
<option value="">Select customer</option>
<?php foreach($customers as $c): ?><option value="<?=$c['id']?>" <?=$v['customer_id']==$c['id']?'selected':''?>><?=e($c['customer_number'].' — '.trim($c['first_name'].' '.$c['last_name']))?></option><?php endforeach; ?>
</select></label>
<label>Internet Account
<select name="account_id" id="account_id">
<option value="">No linked service</option>
<?php foreach($accounts as $a): ?><option value="<?=$a['id']?>" data-customer="<?=$a['customer_id']?>" <?=$v['account_id']==$a['id']?'selected':''?>><?=e(($a['account_number']??$a['username']??$a['id']).' — '.($a['plan_name']??'Internet service'))?></option><?php endforeach; ?>
</select></label>
<label>Amount *
<input type="number" name="amount" step="0.01" min="0.01" value="<?=e($v['amount'])?>" required></label>
<label>Invoice Date
<input type="date" name="invoice_date" value="<?=e($v['invoice_date'])?>" required></label>
<label>Due Date
<input type="date" name="due_date" value="<?=e($v['due_date'])?>" required></label>
<label style="grid-column:1/-1">Description
<textarea name="description"><?=e($v['description'])?></textarea></label>
</div>
<button class="btn btn-primary">Create Invoice</button>
</form>
</div></div>
<script>
document.getElementById('customer_id').addEventListener('change',function(){
    const customer=this.value;
    const select=document.getElementById('account_id');
    Array.from(select.options).forEach(function(option){
        if(!option.value)return;
        const match=option.dataset.customer===customer;
        option.hidden=!match;
        if(!match && option.selected)select.value='';
    });
});
document.getElementById('customer_id').dispatchEvent(new Event('change'));
</script>
<?php require_once '../includes/footer.php'; ?>