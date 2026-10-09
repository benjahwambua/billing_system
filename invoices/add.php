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

$customers=[];
$q=$conn->prepare("SELECT id,customer_number,first_name,last_name FROM customers WHERE tenant_id=? ORDER BY first_name,last_name");
if($q){$q->bind_param('i',$tid);$q->execute();$z=$q->get_result();while($x=$z->fetch_assoc())$customers[]=$x;$q->close();}

$v=['customer_id'=>'','amount'=>'','invoice_date'=>date('Y-m-d'),'due_date'=>date('Y-m-d'),'description'=>''];
if($_SERVER['REQUEST_METHOD']==='POST'){
    requireCsrf();
    foreach($v as $k=>$d)$v[$k]=trim((string)($_POST[$k]??$d));
    $cid=(int)$v['customer_id']; $amount=(float)$v['amount'];
    if(!$cid||$amount<=0)$errors[]='Customer and a positive amount are required.';

    if(!$errors){
        $stmt=$conn->prepare("SELECT id FROM customers WHERE id=? AND tenant_id=? LIMIT 1");
        if($stmt){$stmt->bind_param('ii',$cid,$tid);$stmt->execute();$ok=(bool)$stmt->get_result()->fetch_assoc();$stmt->close();if(!$ok)$errors[]='Customer not found.';}
        else $errors[]='Unable to validate customer.';
    }

    if(!$errors){
        $data=['tenant_id'=>$tid,'customer_id'=>$cid,'invoice_date'=>$v['invoice_date'],'due_date'=>$v['due_date'],'description'=>$v['description'],
               'total_amount'=>$amount,'amount'=>$amount,'total'=>$amount,'status'=>'unpaid','paid_amount'=>0,'balance'=>$amount,
               'invoice_number'=>'INV-'.date('YmdHis').'-'.random_int(100,999)];
        $id=flexihubWorkflowInsert('invoices',$data);
        if($id)redirect('index.php');
        $errors[]='Unable to create invoice. Please check the invoice table fields and try again.';
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
<label>Amount *<input type="number" name="amount" step="0.01" min="0.01" value="<?=e($v['amount'])?>" required></label>
<label>Invoice Date<input type="date" name="invoice_date" value="<?=e($v['invoice_date'])?>"></label>
<label>Due Date<input type="date" name="due_date" value="<?=e($v['due_date'])?>"></label>
<label style="grid-column:1/-1">Description<textarea name="description"><?=e($v['description'])?></textarea></label>
</div><button class="btn btn-primary">Create Invoice</button>
</form></div></div>
<?php require_once '../includes/footer.php';?>