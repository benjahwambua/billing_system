<?php
require_once '../includes/auth.php';
requireActiveUser();
requireTenantContext();
requireModulePermission('hotspot','view');
require_once '../includes/billing_workflow.php';

global $conn;
$tenantId=(int)getCurrentTenantId();
$error=''; $success='';
$codes=[];
$q=$conn->prepare("SELECT c.id,c.code,c.package_id,c.status,c.expires_at,p.name package_name,p.price
                   FROM hotspot_access_codes c
                   JOIN hotspot_packages p ON p.id=c.package_id AND p.tenant_id=c.tenant_id
                   WHERE c.tenant_id=? AND c.status='unused'
                   ORDER BY c.id DESC LIMIT 300");
if($q){$q->bind_param('i',$tenantId);$q->execute();$r=$q->get_result();while($x=$r->fetch_assoc())$codes[]=$x;$q->close();}

if($_SERVER['REQUEST_METHOD']==='POST'){
    requireModulePermission('hotspot','create');
    requireCsrf();
    $codeId=(int)($_POST['access_code_id']??0);
    $method=trim((string)($_POST['payment_method']??'cash'));
    $reference=trim((string)($_POST['reference']??''));
    $allowed=['cash','bank','card'];
    if(!in_array($method,$allowed,true))$method='cash';

    $q=$conn->prepare("SELECT c.id,c.code,c.package_id,c.status,c.expires_at,p.price
                       FROM hotspot_access_codes c
                       JOIN hotspot_packages p ON p.id=c.package_id AND p.tenant_id=c.tenant_id
                       WHERE c.id=? AND c.tenant_id=? LIMIT 1");
    $code=$q?null:null;
    if($q){$q->bind_param('ii',$codeId,$tenantId);$q->execute();$code=$q->get_result()->fetch_assoc();$q->close();}
    if(!$code)$error='Access code not found.';
    elseif($code['status']!=='unused')$error='This access code is no longer available.';
    elseif(!empty($code['expires_at']) && strtotime($code['expires_at'])<time())$error='This access code has expired.';
    else{
        $amount=(float)($code['price']??0);
        if($amount<0)$amount=0;
        $conn->begin_transaction();
        try{
            $saleId=flexihubWorkflowInsert('hotspot_sales',[
                'tenant_id'=>$tenantId,'access_code_id'=>$codeId,'package_id'=>(int)$code['package_id'],
                'amount'=>$amount,'payment_method'=>$method,'reference'=>$reference,'status'=>'completed'
            ]);
            if(!$saleId)throw new Exception('Unable to record sale.');
            $u=$conn->prepare("UPDATE hotspot_access_codes SET status='sold' WHERE id=? AND tenant_id=? AND status='unused'");
            if(!$u)throw new Exception('Unable to reserve access code.');
            $u->bind_param('ii',$codeId,$tenantId);
            if(!$u->execute() || $u->affected_rows!==1)throw new Exception('Access code was already sold.');
            $u->close();
            $conn->commit();
            setFlash('success','Hotspot sale recorded for code '.($code['code']??'').'.');
            redirect('sales.php');
        }catch(Exception $e){
            $conn->rollback();
            $error=$e->getMessage();
        }
    }
}

$sales=[];
$q=$conn->prepare("SELECT s.id,s.amount,s.payment_method,s.reference,s.sold_at,s.status,c.code,p.name package_name
                   FROM hotspot_sales s
                   JOIN hotspot_access_codes c ON c.id=s.access_code_id AND c.tenant_id=s.tenant_id
                   JOIN hotspot_packages p ON p.id=s.package_id AND p.tenant_id=s.tenant_id
                   WHERE s.tenant_id=? ORDER BY s.id DESC LIMIT 200");
if($q){$q->bind_param('i',$tenantId);$q->execute();$r=$q->get_result();while($x=$r->fetch_assoc())$sales[]=$x;$q->close();}
$total=0;foreach($sales as $s){if(in_array($s['status'],['completed','paid_pending_access','access_active'],true))$total+=(float)$s['amount'];}

$pageTitle='Hotspot Sales';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<div class="main-content">
<div class="page-header"><div><h1>Hotspot Sales</h1><p>Track hotspot sales and payment status.</p></div><div><a class="btn btn-primary" href="mpesa.php">M-Pesa STK Push</a></div></div>
<?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?>
<div class="card" style="padding:20px;margin-bottom:20px">
<h3>Record Sale</h3>
<?php if($codes):?>
<form method="post"><?=csrfField()?>
<div class="form-grid">
<label>Access Code *<select name="access_code_id" required><option value="">Select code</option><?php foreach($codes as $c):?><option value="<?=$c['id']?>"><?=e($c['code'].' — '.$c['package_name'].' — '.formatMoney($c['price']))?></option><?php endforeach;?></select></label>
<label>Payment Method<select name="payment_method"><option value="cash">Cash</option><option value="bank">Bank</option><option value="card">Card</option></select></label>
<label>Reference<input name="reference" maxlength="100"></label>
</div><button class="btn btn-primary">Record Sale</button>
</form>
<?php else:?><p>No unused access codes are available for sale.</p><?php endif;?>
</div>
<div class="card" style="padding:20px">
<div style="display:flex;justify-content:space-between;gap:15px;flex-wrap:wrap"><div><h3>Sales History</h3><p>Recorded hotspot sales for this tenant.</p></div><strong>Displayed Revenue: <?=e(formatMoney($total))?></strong></div>
<div class="table-responsive"><table><thead><tr><th>Code</th><th>Plan</th><th>Amount</th><th>Method</th><th>Reference</th><th>Date</th><th>Status</th></tr></thead><tbody>
<?php foreach($sales as $s):?><tr><td><?=e($s['code'])?></td><td><?=e($s['package_name'])?></td><td><?=e(formatMoney($s['amount']))?></td><td><?=e(ucfirst($s['payment_method']))?></td><td><?=e($s['reference']?:'—')?></td><td><?=e($s['sold_at'])?></td><td><?=e($s['status'])?></td></tr><?php endforeach;?>
<?php if(!$sales):?><tr><td colspan="7">No hotspot sales recorded yet.</td></tr><?php endif;?>
</tbody></table></div></div></div>
<?php require_once '../includes/footer.php';?>